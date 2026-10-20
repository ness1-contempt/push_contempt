<?php
/**
 * Endpoint REST per il controllo salute catalogo.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use PushContempt\CatalogHealth;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Prodotti pubblicati con almeno un problema (immagine, categoria, prezzo,
 * descrizione) - vedi PushContempt\CatalogHealth per i criteri.
 */
class SaluteCatalogoController {

	/**
	 * Registra la rotta REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/salute-catalogo',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'soglia_descrizione' => array(
						'type'              => 'integer',
						'default'           => 50,
						'sanitize_callback' => static function ( $valore ) {
							return absint( $valore );
						},
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
	 * Prodotti pubblicati con almeno un problema.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$soglia_descrizione = (int) $request->get_param( 'soglia_descrizione' );

		$prodotti = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
			)
		);

		$risultato = array();
		foreach ( $prodotti as $prodotto ) {
			$dati = array(
				'ha_immagine'      => (bool) $prodotto->get_image_id(),
				'numero_categorie' => count( $prodotto->get_category_ids() ),
				'variabile'        => $prodotto->is_type( 'variable' ),
				'prezzo_regolare'  => $prodotto->get_regular_price(),
				'descrizione'      => $prodotto->get_description(),
			);

			$problemi = CatalogHealth::problemi( $dati, $soglia_descrizione );
			if ( ! empty( $problemi ) ) {
				$risultato[] = array(
					'id'       => $prodotto->get_id(),
					'titolo'   => $prodotto->get_name(),
					'problemi' => $problemi,
				);
			}
		}

		return new WP_REST_Response( $risultato, 200 );
	}
}
