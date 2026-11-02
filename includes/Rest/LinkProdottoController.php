<?php
/**
 * Endpoint REST che elenca i link della descrizione di un prodotto.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use PushContempt\AnalisiLink;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Sola lettura. Analizza la descrizione lunga (quella che Yoast valuta per i
 * link in uscita) cosi' come e' salvata, senza filtri ne' shortcode.
 */
class LinkProdottoController {

	/**
	 * Registra la rotta REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/link/prodotto',
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
			)
		);
	}

	/**
	 * Solo chi gestisce il negozio puo' leggere questo endpoint.
	 */
	public static function permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Link della descrizione divisi in interni ed esterni, con i totali.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_leggi( WP_REST_Request $request ) {
		$prodotto_id = (int) $request->get_param( 'id' );
		$prodotto    = wc_get_product( $prodotto_id );
		if ( ! $prodotto || $prodotto->is_type( 'variation' ) ) {
			return new WP_Error(
				'push_contempt_prodotto_non_trovato',
				"Nessun prodotto con id {$prodotto_id}",
				array( 'status' => 404 )
			);
		}
		if ( ! class_exists( 'DOMDocument' ) ) {
			return new WP_Error(
				'push_contempt_dom_mancante',
				"L'estensione PHP dom non e' attiva su questo hosting: non posso leggere i link.",
				array( 'status' => 501 )
			);
		}

		$link = AnalisiLink::analizza( $prodotto->get_description( 'edit' ), (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		return new WP_REST_Response(
			array(
				'id'                => $prodotto_id,
				'titolo'            => $prodotto->get_name(),
				'interni'           => $link['interni'],
				'esterni'           => $link['esterni'],
				'altri'             => $link['altri'],
				'totale_interni'    => count( $link['interni'] ),
				'totale_esterni'    => count( $link['esterni'] ),
				'ha_link_in_uscita' => count( $link['esterni'] ) > 0,
			),
			200
		);
	}
}
