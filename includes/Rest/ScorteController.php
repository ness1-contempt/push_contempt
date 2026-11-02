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
 *
 * Nei prodotti variabili le scorte stanno spesso sulle varianti: ogni variante
 * pubblicata che ne ha bisogno compare come riga a se' (stesso `id` del padre,
 * `variante_id` e `variante` valorizzati), cosi' il padre non nasconde un
 * esaurimento che riguarda una sola taglia o colore.
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
	 * Prodotti esauriti per primi, poi per scorte crescenti. Un prodotto
	 * variabile compare anche con le sue varianti che hanno bisogno di
	 * rifornimento (vedi riga_variante).
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
			$riga = self::riga( $prodotto, $prodotto, $soglia, null );
			if ( null !== $riga ) {
				$segnalati[] = $riga;
			}
			if ( $prodotto instanceof \WC_Product_Variable ) {
				foreach ( $prodotto->get_children() as $variante_id ) {
					$variante = wc_get_product( $variante_id );
					if ( $variante && 'publish' === $variante->get_status() ) {
						$riga = self::riga( $prodotto, $variante, $soglia, wc_get_formatted_variation( $variante, true ) );
						if ( null !== $riga ) {
							$segnalati[] = $riga;
						}
					}
				}
			}
		}

		usort( $segnalati, array( self::class, 'confronta' ) );

		return new WP_REST_Response( $segnalati, 200 );
	}

	/**
	 * La riga da segnalare per un prodotto o una sua variante, o null se non serve.
	 *
	 * Una variante che non gestisce scorte in proprio ma le eredita dal padre
	 * ("parent") non ha un numero suo: lo copre la riga del padre. Una che non
	 * gestisce scorte per niente si segnala solo se e' esaurita. Una che le
	 * gestisce in proprio segue la soglia come un prodotto qualsiasi.
	 *
	 * @param \WC_Product $prodotto Il prodotto (il padre, se $voce e' una variante).
	 * @param \WC_Product $voce     Il prodotto stesso o la variante da valutare.
	 * @param float       $soglia   Soglia scorte.
	 * @param string|null $variante Descrizione della variante (es. "Taglia: L"), null per un prodotto.
	 */
	private static function riga( \WC_Product $prodotto, \WC_Product $voce, float $soglia, ?string $variante ): ?array {
		$gestione = $voce->get_manage_stock();
		if ( null !== $variante && 'parent' === $gestione ) {
			return null;
		}

		$esaurito     = in_array( $voce->get_stock_status(), self::STATI_ESAURITO, true );
		$gestisce     = (bool) $gestione;
		$scorte       = $gestisce ? $voce->get_stock_quantity() : null;
		$scorte_basse = $gestisce && null !== $scorte && $scorte <= $soglia;

		if ( ! $esaurito && ! $scorte_basse ) {
			return null;
		}

		return array(
			'id'           => $prodotto->get_id(),
			'titolo'       => $prodotto->get_name(),
			'variante_id'  => null !== $variante ? $voce->get_id() : null,
			'variante'     => $variante,
			'sku'          => $voce->get_sku(),
			'scorte'       => $scorte,
			'stato_scorte' => $voce->get_stock_status(),
			'esaurito'     => $esaurito,
		);
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
