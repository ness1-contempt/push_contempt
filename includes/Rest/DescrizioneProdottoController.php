<?php
/**
 * Endpoint REST per la lettura/scrittura della descrizione di un prodotto.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use PushContempt\DescrizioneSnapshot;
use PushContempt\PrezzoProdotto;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Stesso schema di SeoProdottoController, sulla descrizione lunga e su quella
 * breve: GET legge, POST scrive tenendo base e precedente (vedi
 * DescrizioneSnapshot), una seconda rotta ripristina una delle due versioni.
 * L'HTML passa da `wp_kses_post()` (stessi tag ammessi in un articolo, niente
 * script) e si scrive con le API di WooCommerce, niente SQL diretto.
 */
class DescrizioneProdottoController {

	/**
	 * Registra le rotte REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/descrizione/prodotto',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'handle_leggi' ),
					'permission_callback' => array( self::class, 'permission' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'handle_scrivi' ),
					'permission_callback' => array( self::class, 'permission' ),
					'args'                => array(
						'id'                => array(
							'type'     => 'integer',
							'required' => true,
						),
						'descrizione'       => array(
							'type'     => 'string',
							'required' => true,
						),
						'descrizione_breve' => array(
							'type'     => 'string',
							'required' => false,
						),
						'origine'           => array(
							'type'     => 'string',
							'required' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			'push-contempt/v1',
			'/descrizione/ripristina',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle_ripristina' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'ids'      => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
						'required' => true,
					),
					'versione' => array(
						'type'     => 'string',
						'enum'     => DescrizioneSnapshot::VERSIONI,
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'push-contempt/v1',
			'/descrizione/stato',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'handle_stato' ),
				'permission_callback' => array( self::class, 'permission' ),
			)
		);

		register_rest_route(
			'push-contempt/v1',
			'/descrizione/base',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle_imposta_base' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'ids' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Solo chi gestisce il negozio puo' leggere/scrivere questo endpoint.
	 */
	public static function permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Prodotto dato il suo id, o un errore 404 se non esiste (una variazione non e' un prodotto).
	 *
	 * @param int $prodotto_id Id del prodotto da cercare.
	 *
	 * @return \WC_Product|WP_Error
	 */
	private static function trova_prodotto( int $prodotto_id ) {
		$prodotto = wc_get_product( $prodotto_id );
		if ( ! $prodotto || $prodotto->is_type( 'variation' ) ) {
			return new WP_Error(
				'push_contempt_prodotto_non_trovato',
				"Nessun prodotto con id {$prodotto_id}",
				array( 'status' => 404 )
			);
		}
		return $prodotto;
	}

	/**
	 * Le due descrizioni che il prodotto ha adesso.
	 *
	 * @param \WC_Product $prodotto Il prodotto.
	 */
	private static function valori_attuali( \WC_Product $prodotto ): array {
		return array(
			'descrizione'       => (string) $prodotto->get_description( 'edit' ),
			'descrizione_breve' => (string) $prodotto->get_short_description( 'edit' ),
		);
	}

	/**
	 * Scrive le due descrizioni sul prodotto. Il salvataggio passa da
	 * wp_update_post(), che toglie i backslash ai testi: si "slashano" prima.
	 *
	 * @param \WC_Product $prodotto Il prodotto.
	 * @param array       $valori   Descrizione e descrizione breve.
	 */
	private static function imposta( \WC_Product $prodotto, array $valori ): void {
		$prodotto->set_description( wp_slash( $valori['descrizione'] ) );
		$prodotto->set_short_description( wp_slash( $valori['descrizione_breve'] ) );
		$prodotto->save();
	}

	/**
	 * Descrizione lunga e breve attuali, con titolo e le versioni conservate.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_leggi( WP_REST_Request $request ) {
		$prodotto_id = (int) $request->get_param( 'id' );
		$prodotto    = self::trova_prodotto( $prodotto_id );
		if ( is_wp_error( $prodotto ) ) {
			return $prodotto;
		}

		$attuali = self::valori_attuali( $prodotto );
		return new WP_REST_Response(
			array_merge(
				array(
					'id'     => $prodotto_id,
					'titolo' => $prodotto->get_name(),
				),
				$attuali,
				array(
					'versioni' => DescrizioneSnapshot::per_risposta(
						get_post_meta( $prodotto_id, DescrizioneSnapshot::META_KEY, true ),
						$attuali
					),
				)
			),
			200
		);
	}

	/**
	 * Scrive la descrizione (e, se data, quella breve), aggiornando base e
	 * precedente. `descrizione_breve` assente = quella breve resta com'e'.
	 * `origine` e' facoltativa e dice chi scrive.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_scrivi( WP_REST_Request $request ) {
		$prodotto_id = (int) $request->get_param( 'id' );
		$prodotto    = self::trova_prodotto( $prodotto_id );
		if ( is_wp_error( $prodotto ) ) {
			return $prodotto;
		}

		$attuali = self::valori_attuali( $prodotto );
		$nuovi   = array(
			'descrizione'       => wp_kses_post( (string) $request->get_param( 'descrizione' ) ),
			'descrizione_breve' => $attuali['descrizione_breve'],
		);
		if ( null !== $request->get_param( 'descrizione_breve' ) ) {
			$nuovi['descrizione_breve'] = wp_kses_post( (string) $request->get_param( 'descrizione_breve' ) );
		}

		$esistente = get_post_meta( $prodotto_id, DescrizioneSnapshot::META_KEY, true );
		$origine   = sanitize_text_field( (string) $request->get_param( 'origine' ) );
		$versioni  = DescrizioneSnapshot::dopo_scrittura(
			is_array( $esistente ) ? $esistente : null,
			$attuali,
			$nuovi,
			'' !== $origine ? $origine : 'api',
			gmdate( 'c' )
		);
		if ( null !== $versioni ) {
			update_post_meta( $prodotto_id, DescrizioneSnapshot::META_KEY, wp_slash( $versioni ) );
			self::imposta( $prodotto, $nuovi );
		}

		return new WP_REST_Response( self::valori_attuali( wc_get_product( $prodotto_id ) ), 200 );
	}

	/**
	 * Rimette la base o il precedente di uno o piu' prodotti. Il precedente
	 * diventa la versione che si lascia. Esiti per id: "ripristinato",
	 * "gia_in_uso", "nessuno_snapshot", "non_trovato".
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	public static function handle_ripristina( WP_REST_Request $request ): WP_REST_Response {
		$versione = (string) $request->get_param( 'versione' );
		$ids      = array_unique( array_map( 'intval', (array) $request->get_param( 'ids' ) ) );
		$esiti    = array();
		foreach ( $ids as $prodotto_id ) {
			$prodotto = self::trova_prodotto( $prodotto_id );
			if ( is_wp_error( $prodotto ) ) {
				$esiti[ $prodotto_id ] = 'non_trovato';
				continue;
			}
			$snapshot = get_post_meta( $prodotto_id, DescrizioneSnapshot::META_KEY, true );
			if ( ! is_array( $snapshot ) || empty( $snapshot['base'] ) ) {
				$esiti[ $prodotto_id ] = 'nessuno_snapshot';
				continue;
			}
			$risultato = DescrizioneSnapshot::ripristina( $snapshot, $versione, self::valori_attuali( $prodotto ), gmdate( 'c' ) );
			if ( null === $risultato ) {
				$esiti[ $prodotto_id ] = 'gia_in_uso';
				continue;
			}
			self::imposta( $prodotto, $risultato['valori'] );
			update_post_meta( $prodotto_id, DescrizioneSnapshot::META_KEY, wp_slash( $risultato['snapshot'] ) );
			$esiti[ $prodotto_id ] = 'ripristinato';
		}

		return new WP_REST_Response( $esiti, 200 );
	}

	/**
	 * Vista d'insieme: tutti i prodotti pubblicati o in bozza con lunghezza delle
	 * descrizioni (testo senza tag), tipo e prezzo, parola chiave e versioni.
	 */
	public static function handle_stato(): WP_REST_Response {
		$prodotti = wc_get_products(
			array(
				'status'  => array( 'publish', 'draft' ),
				'limit'   => -1,
				'orderby' => 'title',
				'order'   => 'ASC',
			)
		);

		$risultato = array();
		foreach ( $prodotti as $prodotto ) {
			$attuali       = self::valori_attuali( $prodotto );
			$parola_chiave = (string) get_post_meta( $prodotto->get_id(), '_yoast_wpseo_focuskw', true );
			$risultato[]   = array_merge(
				array(
					'id'                          => $prodotto->get_id(),
					'titolo'                      => $prodotto->get_name(),
					'stato'                       => $prodotto->get_status(),
					'parola_chiave_focus'         => '' !== $parola_chiave ? $parola_chiave : null,
					'lunghezza_descrizione'       => self::lunghezza_testo( $attuali['descrizione'] ),
					'lunghezza_descrizione_breve' => self::lunghezza_testo( $attuali['descrizione_breve'] ),
				),
				PrezzoProdotto::di( $prodotto ),
				array(
					'versioni' => DescrizioneSnapshot::per_risposta(
						get_post_meta( $prodotto->get_id(), DescrizioneSnapshot::META_KEY, true ),
						$attuali
					),
				)
			);
		}

		return new WP_REST_Response( $risultato, 200 );
	}

	/**
	 * Quanti caratteri di testo ha un HTML (tag esclusi).
	 *
	 * @param string $html Testo HTML.
	 */
	private static function lunghezza_testo( string $html ): int {
		return mb_strlen( trim( wp_strip_all_tags( $html ) ) );
	}

	/**
	 * Usa le descrizioni attuali come nuova base di uno o piu' prodotti (la
	 * base non e' piu' lo stato prima dei nostri interventi). Esiti per id:
	 * "impostata", "nessuno_snapshot", "non_trovato".
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	public static function handle_imposta_base( WP_REST_Request $request ): WP_REST_Response {
		$ids   = array_unique( array_map( 'intval', (array) $request->get_param( 'ids' ) ) );
		$esiti = array();
		foreach ( $ids as $prodotto_id ) {
			$prodotto = self::trova_prodotto( $prodotto_id );
			if ( is_wp_error( $prodotto ) ) {
				$esiti[ $prodotto_id ] = 'non_trovato';
				continue;
			}
			$snapshot = get_post_meta( $prodotto_id, DescrizioneSnapshot::META_KEY, true );
			if ( ! is_array( $snapshot ) || empty( $snapshot['base'] ) ) {
				$esiti[ $prodotto_id ] = 'nessuno_snapshot';
				continue;
			}
			update_post_meta(
				$prodotto_id,
				DescrizioneSnapshot::META_KEY,
				wp_slash( DescrizioneSnapshot::imposta_base( $snapshot, self::valori_attuali( $prodotto ), gmdate( 'c' ) ) )
			);
			$esiti[ $prodotto_id ] = 'impostata';
		}

		return new WP_REST_Response( $esiti, 200 );
	}
}
