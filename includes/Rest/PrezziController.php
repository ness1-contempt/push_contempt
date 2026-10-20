<?php
/**
 * Endpoint REST per il calcolo e l'applicazione di prezzi in blocco.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use PushContempt\PricingCalculator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Due rotte: /prezzi/calcola (solo lettura, anteprima) e /prezzi/applica
 * (scrittura, usa l'API nativa di WooCommerce - niente SQL diretto: WC si
 * occupa da sola di _price/sconto attivo e della lookup table).
 *
 * Solo prodotti semplici: quelli variabili (prezzo = min/max delle
 * varianti) sono esclusi da /calcola e segnalati a parte.
 */
class PrezziController {

	/**
	 * Registra entrambe le rotte REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/prezzi/calcola',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'handle_calcola' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'categoria'   => array(
						'type'     => 'string',
						'required' => true,
					),
					'percentuale' => array(
						'type'              => 'number',
						'sanitize_callback' => array( self::class, 'sanitizza_numero_opzionale' ),
					),
					'delta'       => array(
						'type'              => 'number',
						'sanitize_callback' => array( self::class, 'sanitizza_numero_opzionale' ),
					),
				),
			)
		);

		register_rest_route(
			'push-contempt/v1',
			'/prezzi/applica',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle_applica' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'aggiornamenti' => array(
						'type'     => 'array',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Solo chi gestisce il negozio puo' leggere/scrivere questi endpoint.
	 */
	public static function permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Converte in float, o lascia null se il parametro non e' stato passato.
	 *
	 * @param mixed $valore Valore grezzo dalla richiesta.
	 */
	public static function sanitizza_numero_opzionale( $valore ) {
		return null === $valore ? null : (float) $valore;
	}

	/**
	 * Anteprima: prodotti semplici della categoria con prezzo attuale e
	 * quello calcolato. Non scrive nulla.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_calcola( WP_REST_Request $request ) {
		$categoria   = $request->get_param( 'categoria' );
		$percentuale = $request->get_param( 'percentuale' );
		$delta       = $request->get_param( 'delta' );

		if ( ( null === $percentuale ) === ( null === $delta ) ) {
			return new WP_Error(
				'push_contempt_parametri_invalidi',
				'Specificare esattamente uno tra percentuale e delta.',
				array( 'status' => 400 )
			);
		}

		$prodotti = wc_get_products(
			array(
				'category' => array( $categoria ),
				'limit'    => -1,
			)
		);

		$aggiornamenti = array();
		$variabili     = array();

		foreach ( $prodotti as $prodotto ) {
			if ( $prodotto->is_type( 'variable' ) ) {
				$variabili[] = array(
					'id'     => $prodotto->get_id(),
					'titolo' => $prodotto->get_name(),
				);
				continue;
			}

			$prezzo_attuale = $prodotto->get_regular_price();
			if ( '' === $prezzo_attuale ) {
				continue;
			}

			$aggiornamenti[] = array(
				'id'                      => $prodotto->get_id(),
				'titolo'                  => $prodotto->get_name(),
				'prezzo_regolare_attuale' => $prezzo_attuale,
				'prezzo_regolare_nuovo'   => PricingCalculator::nuovo_prezzo(
					$prezzo_attuale,
					$percentuale,
					$delta
				),
			);
		}

		return new WP_REST_Response(
			array(
				'categoria'                  => $categoria,
				'aggiornamenti'              => $aggiornamenti,
				'prodotti_variabili_esclusi' => $variabili,
			),
			200
		);
	}

	/**
	 * Scrive i nuovi prezzi (lista approvata, stessa forma dell'output di
	 * /calcola: ogni elemento serve `id` e `prezzo_regolare_nuovo`).
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	public static function handle_applica( WP_REST_Request $request ): WP_REST_Response {
		$aggiornamenti = $request->get_param( 'aggiornamenti' );

		$risultati = array();
		foreach ( $aggiornamenti as $voce ) {
			$prodotto_id = (int) ( $voce['id'] ?? 0 );
			$prodotto    = $prodotto_id ? wc_get_product( $prodotto_id ) : false;

			if ( ! $prodotto ) {
				$risultati[] = array(
					'id'     => $prodotto_id,
					'errore' => 'Prodotto non trovato',
				);
				continue;
			}

			$prodotto->set_regular_price( $voce['prezzo_regolare_nuovo'] ?? '' );
			$prodotto->save();

			$risultati[] = array(
				'id'                    => $prodotto->get_id(),
				'prezzo_regolare_nuovo' => $prodotto->get_regular_price(),
				'prezzo_effettivo'      => $prodotto->get_price(),
				'sconto_attivo'         => $prodotto->is_on_sale(),
			);
		}

		return new WP_REST_Response(
			array(
				'stato'      => 'applicato',
				'aggiornati' => $risultati,
			),
			200
		);
	}
}
