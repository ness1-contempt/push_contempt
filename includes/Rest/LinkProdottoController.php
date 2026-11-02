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

		register_rest_route(
			'push-contempt/v1',
			'/link/prodotti',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'handle_riepilogo' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'stato'                     => array(
						'type'    => 'string',
						'enum'    => array( 'publish', 'draft', 'any' ),
						'default' => 'publish',
					),
					'solo_senza_link_in_uscita' => array(
						'type'    => 'boolean',
						'default' => false,
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
			return self::errore_dom();
		}

		$link = self::analizza( $prodotto );

		return new WP_REST_Response(
			array_merge(
				array(
					'id'     => $prodotto_id,
					'titolo' => $prodotto->get_name(),
				),
				$link,
				self::totali( $link )
			),
			200
		);
	}

	/**
	 * Riepilogo di tutti i prodotti: per ciascuno solo i totali dei link, e in
	 * testa quanti sono senza link in uscita (o senza link interni). Per vedere
	 * gli indirizzi di uno si usa la rotta del singolo prodotto.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_riepilogo( WP_REST_Request $request ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return self::errore_dom();
		}

		$stato    = (string) $request->get_param( 'stato' );
		$prodotti = wc_get_products(
			array(
				'status'  => 'any' === $stato ? array( 'publish', 'draft', 'pending', 'private' ) : $stato,
				'limit'   => -1,
				'orderby' => 'title',
				'order'   => 'ASC',
			)
		);

		$righe         = array();
		$senza_uscita  = 0;
		$senza_interni = 0;
		foreach ( $prodotti as $prodotto ) {
			$totali         = self::totali( self::analizza( $prodotto ) );
			$senza_uscita  += $totali['ha_link_in_uscita'] ? 0 : 1;
			$senza_interni += $totali['totale_interni'] > 0 ? 0 : 1;
			if ( $request->get_param( 'solo_senza_link_in_uscita' ) && $totali['ha_link_in_uscita'] ) {
				continue;
			}
			$righe[] = array_merge(
				array(
					'id'     => $prodotto->get_id(),
					'titolo' => $prodotto->get_name(),
					'stato'  => $prodotto->get_status(),
				),
				$totali
			);
		}

		return new WP_REST_Response(
			array(
				'analizzati'           => count( $prodotti ),
				'senza_link_in_uscita' => $senza_uscita,
				'senza_link_interni'   => $senza_interni,
				'prodotti'             => $righe,
			),
			200
		);
	}

	/**
	 * Link della descrizione di un prodotto (interni, esterni, altri).
	 *
	 * @param \WC_Product $prodotto Il prodotto.
	 */
	private static function analizza( \WC_Product $prodotto ): array {
		return AnalisiLink::analizza( $prodotto->get_description( 'edit' ), (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	/**
	 * I totali di un'analisi dei link.
	 *
	 * @param array $link Risultato di AnalisiLink::analizza().
	 */
	private static function totali( array $link ): array {
		return array(
			'totale_interni'    => count( $link['interni'] ),
			'totale_esterni'    => count( $link['esterni'] ),
			'ha_link_in_uscita' => count( $link['esterni'] ) > 0,
		);
	}

	/**
	 * Errore per l'hosting senza l'estensione PHP dom.
	 */
	private static function errore_dom(): WP_Error {
		return new WP_Error(
			'push_contempt_dom_mancante',
			"L'estensione PHP dom non e' attiva su questo hosting: non posso leggere i link.",
			array( 'status' => 501 )
		);
	}
}
