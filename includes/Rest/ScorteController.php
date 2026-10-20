<?php
/**
 * Endpoint REST per l'alert scorte.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Prodotti pubblicati esauriti o sotto una soglia minima di scorte.
 *
 * Stessa logica di inventory_tools.py in push_contempt_mcp (il prototipo
 * Python): un prodotto senza gestione scorte attiva non ha un numero di
 * scorte attendibile, viene segnalato solo se il suo stato e'
 * "esaurito"/"in attesa di riapprovvigionamento", mai per soglia.
 */
class ScorteController {

	const STATI_ESAURITO = array( 'outofstock', 'onbackorder' );

	/**
	 * Registra la rotta REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/scorte',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'soglia' => array(
						'type'              => 'number',
						'default'           => 5,
						'sanitize_callback' => static function ( $valore ) {
							return floatval( $valore );
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
	 * Prodotti esauriti per primi, poi per scorte crescenti.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$soglia   = (float) $request->get_param( 'soglia' );
		$prodotti = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
			)
		);

		$segnalati = array();
		foreach ( $prodotti as $prodotto ) {
			$esaurito     = in_array( $prodotto->get_stock_status(), self::STATI_ESAURITO, true );
			$gestisce     = $prodotto->get_manage_stock();
			$scorte       = $gestisce ? $prodotto->get_stock_quantity() : null;
			$scorte_basse = $gestisce && null !== $scorte && $scorte <= $soglia;

			if ( ! $esaurito && ! $scorte_basse ) {
				continue;
			}

			$segnalati[] = array(
				'id'           => $prodotto->get_id(),
				'titolo'       => $prodotto->get_name(),
				'sku'          => $prodotto->get_sku(),
				'scorte'       => $scorte,
				'stato_scorte' => $prodotto->get_stock_status(),
				'esaurito'     => $esaurito,
			);
		}

		usort( $segnalati, array( self::class, 'confronta' ) );

		return new WP_REST_Response( $segnalati, 200 );
	}

	/**
	 * Esauriti prima, poi per scorte crescenti (null trattato come -1).
	 *
	 * @param array $a Primo prodotto da confrontare.
	 * @param array $b Secondo prodotto da confrontare.
	 */
	private static function confronta( array $a, array $b ): int {
		$peso_a = $a['esaurito'] ? 0 : 1;
		$peso_b = $b['esaurito'] ? 0 : 1;
		if ( $peso_a !== $peso_b ) {
			return $peso_a <=> $peso_b;
		}

		$scorte_a = null === $a['scorte'] ? -1 : $a['scorte'];
		$scorte_b = null === $b['scorte'] ? -1 : $b['scorte'];
		return $scorte_a <=> $scorte_b;
	}
}
