<?php
/**
 * Endpoint REST per la lettura/scrittura dei campi SEO Yoast di un singolo prodotto.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use PushContempt\SeoSnapshot;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Due rotte sullo stesso percorso: GET legge titolo/descrizione/prezzo del
 * prodotto piu' i campi Yoast attuali (contesto per chi deve scrivere i
 * nuovi testi), POST li sovrascrive tenendo le versioni base e precedente
 * (vedi SeoSnapshot); altre due rotte ripristinano una versione o ridefiniscono
 * la base. API native di WordPress/WooCommerce
 * (`get_post_meta()`/`update_post_meta()`), niente SQL diretto - stesso
 * principio di ProdottiController e degli altri controller REST.
 */
class SeoProdottoController {

	const CAMPI_YOAST = array(
		'titolo_seo'          => '_yoast_wpseo_title',
		'meta_description'    => '_yoast_wpseo_metadesc',
		'parola_chiave_focus' => '_yoast_wpseo_focuskw',
	);

	/**
	 * Registra la rotta REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/seo/prodotto',
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
						'id'                  => array(
							'type'     => 'integer',
							'required' => true,
						),
						'titolo_seo'          => array(
							'type'     => 'string',
							'required' => true,
						),
						'meta_description'    => array(
							'type'     => 'string',
							'required' => true,
						),
						'parola_chiave_focus' => array(
							'type'     => 'string',
							'required' => true,
						),
						'origine'             => array(
							'type'     => 'string',
							'required' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			'push-contempt/v1',
			'/seo/ripristina',
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
						'enum'     => SeoSnapshot::VERSIONI,
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'push-contempt/v1',
			'/seo/base',
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
	 * Prodotto dato il suo id, o un errore 404 se non esiste.
	 *
	 * @param int $prodotto_id Id del prodotto da cercare.
	 *
	 * @return \WC_Product|WP_Error
	 */
	private static function trova_prodotto( int $prodotto_id ) {
		$prodotto = wc_get_product( $prodotto_id );
		if ( ! $prodotto ) {
			return new WP_Error(
				'push_contempt_prodotto_non_trovato',
				"Nessun prodotto con id {$prodotto_id}",
				array( 'status' => 404 )
			);
		}
		return $prodotto;
	}

	/**
	 * Titolo/descrizione/prezzo del prodotto piu' i campi Yoast attuali.
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

		$campi = array(
			'id'          => $prodotto_id,
			'titolo'      => $prodotto->get_name(),
			'descrizione' => $prodotto->get_description(),
			'prezzo'      => $prodotto->get_price(),
		);
		foreach ( self::CAMPI_YOAST as $campo => $meta_key ) {
			$valore          = get_post_meta( $prodotto_id, $meta_key, true );
			$campi[ $campo ] = '' !== $valore ? $valore : null;
		}

		return new WP_REST_Response( $campi, 200 );
	}

	/**
	 * I valori SEO Yoast che il prodotto ha adesso (campo => stringa, vuota se mancante).
	 *
	 * @param int $prodotto_id Id del prodotto.
	 */
	private static function valori_attuali( int $prodotto_id ): array {
		$attuali = array();
		foreach ( self::CAMPI_YOAST as $campo => $meta_key ) {
			$attuali[ $campo ] = (string) get_post_meta( $prodotto_id, $meta_key, true );
		}
		return $attuali;
	}

	/**
	 * Scrive titolo SEO, meta description e parola chiave focus (sovrascrive
	 * i valori esistenti, aggiornando base e precedente: vedi SeoSnapshot).
	 * `origine` e' facoltativa e dice chi scrive (es. "concorrente x.it"):
	 * serve solo a mostrarlo all'utente.
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

		$nuovi_valori = array();
		foreach ( self::CAMPI_YOAST as $campo => $meta_key ) {
			$nuovi_valori[ $campo ] = sanitize_text_field( $request->get_param( $campo ) );
		}

		$esistente = get_post_meta( $prodotto_id, SeoSnapshot::META_KEY, true );
		$origine   = sanitize_text_field( (string) $request->get_param( 'origine' ) );
		$versioni  = SeoSnapshot::dopo_scrittura(
			is_array( $esistente ) ? $esistente : null,
			self::valori_attuali( $prodotto_id ),
			$nuovi_valori,
			'' !== $origine ? $origine : 'api',
			gmdate( 'c' )
		);
		if ( null !== $versioni ) {
			update_post_meta( $prodotto_id, SeoSnapshot::META_KEY, $versioni );
		}

		foreach ( self::CAMPI_YOAST as $campo => $meta_key ) {
			update_post_meta( $prodotto_id, $meta_key, $nuovi_valori[ $campo ] );
		}

		return new WP_REST_Response( $nuovi_valori, 200 );
	}

	/**
	 * Rimette la base o il precedente di uno o piu' prodotti. Il precedente
	 * diventa la versione che si lascia (si puo' sempre tornare indietro).
	 * Risposta: per ogni id l'esito ("ripristinato", "gia_in_uso",
	 * "nessuno_snapshot", "non_trovato").
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	public static function handle_ripristina( WP_REST_Request $request ): WP_REST_Response {
		$versione = (string) $request->get_param( 'versione' );
		$esiti    = array();
		foreach ( self::ids( $request ) as $prodotto_id ) {
			$snapshot = self::snapshot_di( $prodotto_id, $esiti );
			if ( null === $snapshot ) {
				continue;
			}
			$risultato = SeoSnapshot::ripristina( $snapshot, $versione, self::valori_attuali( $prodotto_id ), gmdate( 'c' ) );
			if ( null === $risultato ) {
				$esiti[ $prodotto_id ] = 'gia_in_uso';
				continue;
			}
			foreach ( $risultato['valori'] as $campo => $valore ) {
				if ( '' === $valore ) {
					delete_post_meta( $prodotto_id, self::CAMPI_YOAST[ $campo ] );
				} else {
					update_post_meta( $prodotto_id, self::CAMPI_YOAST[ $campo ], $valore );
				}
			}
			update_post_meta( $prodotto_id, SeoSnapshot::META_KEY, $risultato['snapshot'] );
			$esiti[ $prodotto_id ] = 'ripristinato';
		}

		return new WP_REST_Response( $esiti, 200 );
	}

	/**
	 * Usa i valori attuali come nuova base di uno o piu' prodotti (la base
	 * non e' piu' lo stato originale di Yoast). Esiti: "impostata",
	 * "nessuno_snapshot", "non_trovato".
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	public static function handle_imposta_base( WP_REST_Request $request ): WP_REST_Response {
		$esiti = array();
		foreach ( self::ids( $request ) as $prodotto_id ) {
			$snapshot = self::snapshot_di( $prodotto_id, $esiti );
			if ( null === $snapshot ) {
				continue;
			}
			update_post_meta(
				$prodotto_id,
				SeoSnapshot::META_KEY,
				SeoSnapshot::imposta_base( $snapshot, self::valori_attuali( $prodotto_id ), gmdate( 'c' ) )
			);
			$esiti[ $prodotto_id ] = 'impostata';
		}

		return new WP_REST_Response( $esiti, 200 );
	}

	/**
	 * Gli id dei prodotti della richiesta, senza doppioni.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	private static function ids( WP_REST_Request $request ): array {
		return array_unique( array_map( 'intval', (array) $request->get_param( 'ids' ) ) );
	}

	/**
	 * Le versioni salvate di un prodotto. Se il prodotto non esiste o non ne
	 * ha, scrive l'esito in $esiti e restituisce null.
	 *
	 * @param int   $prodotto_id Id del prodotto.
	 * @param array $esiti       Esiti per id, aggiornati per riferimento.
	 */
	private static function snapshot_di( int $prodotto_id, array &$esiti ): ?array {
		if ( ! wc_get_product( $prodotto_id ) ) {
			$esiti[ $prodotto_id ] = 'non_trovato';
			return null;
		}
		$snapshot = get_post_meta( $prodotto_id, SeoSnapshot::META_KEY, true );
		if ( ! is_array( $snapshot ) || empty( $snapshot['base'] ) ) {
			$esiti[ $prodotto_id ] = 'nessuno_snapshot';
			return null;
		}
		return $snapshot;
	}
}
