<?php
/**
 * Endpoint REST per leggere l'albero delle categorie e spostarne una sotto un'altra.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use PushContempt\ConfermaAzione;
use PushContempt\NuovaCategoria;
use PushContempt\SpostamentoCategoria;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET `/categorie` elenca le categorie prodotto con percorso e numero di prodotti.
 * POST `/categorie` ne crea una da sola (anteprima di default).
 * POST `/categorie/sposta` cambia la categoria madre, in due tempi: con
 * `solo_anteprima` (il predefinito) non cambia niente e dice cosa succederebbe,
 * compreso come cambia l'indirizzo, e restituisce un `codice_conferma`; l'azione
 * vera richiede quel codice (vedi ConfermaAzione) ed e' rifiutata se l'albero e'
 * cambiato dopo l'anteprima.
 *
 * Spostare una categoria cambia il suo indirizzo (e quello delle sottocategorie):
 * WordPress non crea un redirect, ma il vecchio indirizzo ha come canonical il nuovo.
 */
class CategorieController {

	const AZIONE = 'sposta_categoria';

	/**
	 * Registra le rotte REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/categorie',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'handle_elenco' ),
					'permission_callback' => array( self::class, 'permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'handle_crea' ),
					'permission_callback' => array( self::class, 'permission' ),
					'args'                => array(
						'nome'           => array(
							'type'     => 'string',
							'required' => true,
						),
						'madre'          => array(
							'type'    => 'integer',
							'default' => 0,
							'minimum' => 0,
						),
						'solo_anteprima' => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
				),
			)
		);
		register_rest_route(
			'push-contempt/v1',
			'/categorie/sposta',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle_sposta' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'id'              => array(
						'type'     => 'integer',
						'required' => true,
					),
					'nuova_madre'     => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 0,
					),
					'solo_anteprima'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'codice_conferma' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Solo chi gestisce il negozio puo' leggere e spostare le categorie.
	 */
	public static function permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Le categorie prodotto come `{ id, nome, madre, slug, prodotti }`.
	 *
	 * @return array[]
	 */
	private static function categorie(): array {
		$termini = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 0,
			)
		);
		$elenco  = array();
		if ( is_wp_error( $termini ) ) {
			return $elenco;
		}
		foreach ( $termini as $termine ) {
			$elenco[] = array(
				'id'       => (int) $termine->term_id,
				'nome'     => wp_specialchars_decode( $termine->name, ENT_QUOTES ),
				'madre'    => (int) $termine->parent,
				'slug'     => (string) $termine->slug,
				'prodotti' => (int) $termine->count,
			);
		}
		return $elenco;
	}

	/**
	 * L'albero delle categorie, con percorso, livello e indirizzo.
	 */
	public static function handle_elenco(): WP_REST_Response {
		$categorie   = self::categorie();
		$predefinita = (int) get_option( 'default_product_cat' );
		$righe       = array();
		foreach ( $categorie as $categoria ) {
			$per_id  = array_column( $categorie, null, 'id' );
			$url     = get_term_link( $categoria['id'], 'product_cat' );
			$righe[] = array(
				'id'          => $categoria['id'],
				'nome'        => $categoria['nome'],
				'madre'       => $categoria['madre'],
				'percorso'    => SpostamentoCategoria::percorso( $categorie, $categoria['id'] ),
				'livello'     => count( SpostamentoCategoria::catena( $per_id, $categoria['id'] ) ),
				'prodotti'    => $categoria['prodotti'],
				'predefinita' => $categoria['id'] === $predefinita,
				'url'         => is_wp_error( $url ) ? null : $url,
			);
		}
		usort( $righe, static fn( array $a, array $b ): int => strcmp( $a['percorso'], $b['percorso'] ) );
		return new WP_REST_Response( $righe, 200 );
	}

	/**
	 * Crea una categoria da sola (sotto una madre o alla radice). Anteprima di default; una
	 * categoria vuota non e' visibile a nessuno, quindi qui non serve il codice di conferma.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_crea( WP_REST_Request $request ) {
		$madre     = (int) $request->get_param( 'madre' );
		$categorie = self::categorie();

		$esito = NuovaCategoria::pianifica( $categorie, $request->get_param( 'nome' ), $madre );
		if ( isset( $esito['errore'] ) ) {
			return new WP_Error( 'push_contempt_' . $esito['errore']['codice'], $esito['errore']['messaggio'], array( 'status' => $esito['errore']['stato'] ) );
		}
		$piano = $esito['piano'];

		if ( $request->get_param( 'solo_anteprima' ) ) {
			return new WP_REST_Response(
				array(
					'solo_anteprima' => true,
					'nome'           => $piano['nome'],
					'madre'          => $madre,
					'percorso_dopo'  => $piano['percorso_dopo'],
					'livello'        => $piano['livello'],
				),
				200
			);
		}

		$termine = wp_insert_term( $piano['nome'], 'product_cat', array( 'parent' => $madre ) );
		if ( is_wp_error( $termine ) ) {
			$stato = 'term_exists' === $termine->get_error_code() ? 409 : 500;
			return new WP_Error( 'push_contempt_creazione_fallita', $termine->get_error_message(), array( 'status' => $stato ) );
		}
		$url = get_term_link( (int) $termine['term_id'], 'product_cat' );

		return new WP_REST_Response(
			array(
				'creata'   => true,
				'id'       => (int) $termine['term_id'],
				'nome'     => $piano['nome'],
				'percorso' => $piano['percorso_dopo'],
				'livello'  => $piano['livello'],
				'url'      => is_wp_error( $url ) ? null : $url,
			),
			200
		);
	}

	/**
	 * Anteprima o esecuzione dello spostamento.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_sposta( WP_REST_Request $request ) {
		$id          = (int) $request->get_param( 'id' );
		$nuova_madre = (int) $request->get_param( 'nuova_madre' );
		$categorie   = self::categorie();

		$esito = SpostamentoCategoria::pianifica( $categorie, $id, $nuova_madre, (int) get_option( 'default_product_cat' ) );
		if ( isset( $esito['errore'] ) ) {
			return new WP_Error( 'push_contempt_' . $esito['errore']['codice'], $esito['errore']['messaggio'], array( 'status' => $esito['errore']['stato'] ) );
		}
		$piano   = $esito['piano'];
		$stato   = array(
			$id . ':' . $piano['madre_prima'] . '>' . $piano['madre_dopo'],
			'figlie:' . implode( ',', $piano['sottocategorie'] ),
		);
		$segreto = wp_salt( 'auth' );

		if ( $request->get_param( 'solo_anteprima' ) ) {
			return new WP_REST_Response( self::anteprima( $categorie, $piano, ConfermaAzione::codice( self::AZIONE, $stato, $segreto ) ), 200 );
		}

		if ( ! ConfermaAzione::valido( (string) $request->get_param( 'codice_conferma' ), self::AZIONE, $stato, $segreto ) ) {
			return new WP_Error(
				'push_contempt_conferma_non_valida',
				"Manca il codice di conferma dell'anteprima, o l'albero delle categorie e' cambiato dopo: rifai l'anteprima, falla vedere all'utente e usa il nuovo codice. Non e' stato spostato niente.",
				array( 'status' => 409 )
			);
		}

		$url_prima = get_term_link( $id, 'product_cat' );
		$risultato = wp_update_term( $id, 'product_cat', array( 'parent' => $nuova_madre ) );
		if ( is_wp_error( $risultato ) ) {
			return new WP_Error( 'push_contempt_spostamento_fallito', $risultato->get_error_message(), array( 'status' => 500 ) );
		}
		clean_term_cache( $id, 'product_cat' );
		do_action( 'litespeed_purge_all' ); // Le pagine delle categorie in cache hanno ancora i vecchi indirizzi.
		$url_dopo = get_term_link( $id, 'product_cat' );

		return new WP_REST_Response(
			array(
				'spostata'       => true,
				'id'             => $id,
				'nome'           => $piano['nome'],
				'percorso_prima' => $piano['percorso_prima'],
				'percorso_dopo'  => $piano['percorso_dopo'],
				'url_prima'      => is_wp_error( $url_prima ) ? null : $url_prima,
				'url_dopo'       => is_wp_error( $url_dopo ) ? null : $url_dopo,
			),
			200
		);
	}

	/**
	 * Cosa succederebbe, senza cambiare niente.
	 *
	 * @param array[] $categorie Tutte le categorie.
	 * @param array   $piano     Piano di SpostamentoCategoria.
	 * @param string  $codice    Codice di conferma da restituire.
	 */
	private static function anteprima( array $categorie, array $piano, string $codice ): array {
		$id        = $piano['id'];
		$url_prima = get_term_link( $id, 'product_cat' );
		$url_prima = is_wp_error( $url_prima ) ? '' : $url_prima;
		$url_dopo  = self::url_dopo( $categorie, $id, $piano['madre_dopo'], $url_prima );
		$ramo      = array_merge( array( $id ), $piano['sottocategorie'] );
		$per_id    = array_column( $categorie, null, 'id' );

		$avvisi = array();
		if ( $url_prima !== $url_dopo ) {
			$avvisi[] = "L'indirizzo della categoria cambia da {$url_prima} a {$url_dopo}"
				. ( $piano['sottocategorie'] ? ' (e cambiano anche quelli delle sue sottocategorie)' : '' )
				. '. Il vecchio indirizzo continua a rispondere ma ha come canonical il nuovo; WordPress non crea un redirect 301: se serve va configurato a parte.';
		}
		$prodotti = self::prodotti_del_ramo( $categorie, $ramo );
		if ( false !== strpos( (string) ( function_exists( 'wc_get_permalink_structure' ) ? wc_get_permalink_structure()['product_base'] : '' ), '%product_cat%' ) && $prodotti['totale'] > 0 ) {
			$avvisi[] = "Gli indirizzi dei prodotti includono la categoria: cambieranno anche quelli di {$prodotti['totale']} prodotti.";
		}

		return array(
			'solo_anteprima'      => true,
			'id'                  => $id,
			'nome'                => $piano['nome'],
			'percorso_prima'      => $piano['percorso_prima'],
			'percorso_dopo'       => $piano['percorso_dopo'],
			'url_prima'           => $url_prima,
			'url_dopo'            => $url_dopo,
			'sottocategorie'      => array_map( static fn( int $figlia ): string => SpostamentoCategoria::percorso( $categorie, $figlia ), $piano['sottocategorie'] ),
			'prodotti_nel_ramo'   => $prodotti['totale'],
			'prodotti_pubblicati' => $prodotti['pubblicati'],
			'avvisi'              => $avvisi,
			'codice_conferma'     => $codice,
			'madre_dopo'          => $piano['madre_dopo'] > 0 ? $per_id[ $piano['madre_dopo'] ]['nome'] : null,
		);
	}

	/**
	 * L'indirizzo che avrebbe la categoria dopo lo spostamento.
	 *
	 * @param array[] $categorie   Tutte le categorie.
	 * @param int     $id          Categoria.
	 * @param int     $nuova_madre Nuova madre (0 = radice).
	 * @param string  $url_prima   Indirizzo attuale.
	 */
	private static function url_dopo( array $categorie, int $id, int $nuova_madre, string $url_prima ): string {
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return $url_prima; // Indirizzi "semplici" (?product_cat=...): la gerarchia non c'entra.
		}
		$per_id = array_column( $categorie, null, 'id' );
		$slugs  = array();
		foreach ( SpostamentoCategoria::catena( $per_id, $nuova_madre ) as $antenato ) {
			$slugs[] = $per_id[ $antenato ]['slug'];
		}
		$slugs[] = $per_id[ $id ]['slug'];
		$base    = function_exists( 'wc_get_permalink_structure' ) ? wc_get_permalink_structure()['category_rewrite_slug'] : 'categoria-prodotto';
		return user_trailingslashit( home_url( $base . '/' . implode( '/', $slugs ) ) );
	}

	/**
	 * Quanti prodotti sono nel ramo (categoria e sottocategorie), in totale e pubblicati.
	 *
	 * @param array[] $categorie Tutte le categorie.
	 * @param int[]   $ramo      Id della categoria e delle sue sottocategorie.
	 *
	 * @return array{totale: int, pubblicati: int}
	 */
	private static function prodotti_del_ramo( array $categorie, array $ramo ): array {
		$per_id = array_column( $categorie, null, 'id' );
		$slugs  = array_map( static fn( int $id ): string => $per_id[ $id ]['slug'], $ramo );
		$conta  = static function ( array $stati ) use ( $slugs ): int {
			return count(
				wc_get_products(
					array(
						'status'   => $stati,
						'limit'    => -1,
						'return'   => 'ids',
						'category' => $slugs,
					)
				)
			);
		};
		return array(
			'totale'     => $conta( array( 'publish', 'draft', 'pending', 'private' ) ),
			'pubblicati' => $conta( array( 'publish' ) ),
		);
	}
}
