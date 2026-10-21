<?php
/**
 * Endpoint REST per la lettura/scrittura dei campi SEO Yoast di un singolo prodotto.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Due rotte sullo stesso percorso: GET legge titolo/descrizione/prezzo del
 * prodotto piu' i campi Yoast attuali (contesto per chi deve scrivere i
 * nuovi testi), POST li sovrascrive. API native di WordPress/WooCommerce
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
	 * Scrive titolo SEO, meta description e parola chiave focus (sovrascrive
	 * i valori esistenti).
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
			$valore                 = sanitize_text_field( $request->get_param( $campo ) );
			$nuovi_valori[ $campo ] = $valore;
			update_post_meta( $prodotto_id, $meta_key, $valore );
		}

		return new WP_REST_Response( $nuovi_valori, 200 );
	}
}
