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
 * nuovi testi), POST li sovrascrive dopo aver conservato i valori precedenti
 * (vedi SeoSnapshot); una terza rotta li ripristina. API native di WordPress/WooCommerce
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
	 * i valori esistenti, dopo averli conservati in uno snapshot se e' la
	 * prima modifica: vedi SeoSnapshot). `origine` e' facoltativa e dice chi
	 * scrive (es. "concorrente x.it"): serve solo a mostrarlo all'utente.
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
		$attuali   = self::valori_attuali( $prodotto_id );
		if ( SeoSnapshot::da_salvare( is_array( $esistente ) ? $esistente : null, $attuali, $nuovi_valori ) ) {
			$origine = sanitize_text_field( (string) $request->get_param( 'origine' ) );
			update_post_meta(
				$prodotto_id,
				SeoSnapshot::META_KEY,
				SeoSnapshot::crea( $attuali, '' !== $origine ? $origine : 'api', gmdate( 'c' ) )
			);
		}

		foreach ( self::CAMPI_YOAST as $campo => $meta_key ) {
			update_post_meta( $prodotto_id, $meta_key, $nuovi_valori[ $campo ] );
		}

		return new WP_REST_Response( $nuovi_valori, 200 );
	}

	/**
	 * Rimette i valori SEO di uno o piu' prodotti com'erano prima del primo
	 * intervento. Un prodotto senza snapshot non viene toccato. Risposta: per
	 * ogni id l'esito ("ripristinato", "nessuno_snapshot", "non_trovato").
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	public static function handle_ripristina( WP_REST_Request $request ): WP_REST_Response {
		$esiti = array();
		foreach ( array_unique( array_map( 'intval', (array) $request->get_param( 'ids' ) ) ) as $prodotto_id ) {
			if ( ! wc_get_product( $prodotto_id ) ) {
				$esiti[ $prodotto_id ] = 'non_trovato';
				continue;
			}
			$snapshot = get_post_meta( $prodotto_id, SeoSnapshot::META_KEY, true );
			if ( ! is_array( $snapshot ) || empty( $snapshot ) ) {
				$esiti[ $prodotto_id ] = 'nessuno_snapshot';
				continue;
			}
			foreach ( SeoSnapshot::valori_da_ripristinare( $snapshot ) as $campo => $valore ) {
				if ( '' === $valore ) {
					delete_post_meta( $prodotto_id, self::CAMPI_YOAST[ $campo ] );
				} else {
					update_post_meta( $prodotto_id, self::CAMPI_YOAST[ $campo ], $valore );
				}
			}
			delete_post_meta( $prodotto_id, SeoSnapshot::META_KEY );
			$esiti[ $prodotto_id ] = 'ripristinato';
		}

		return new WP_REST_Response( $esiti, 200 );
	}
}
