<?php
/**
 * Endpoint REST per il report vendite per prodotto.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Report vendite per prodotto: piu' venduti, mai venduti, ricavo nel periodo.
 *
 * Usa le tabelle HPOS di WooCommerce (wc_order_product_lookup e' gia'
 * aggregata per prodotto/ordine con quantita' e ricavo, niente bisogno di
 * ricalcolare dai raw order items). Conta solo gli ordini "completati" o
 * "in elaborazione": esclude annullati, rimborsati, falliti, in attesa.
 */
class VenditeController {

	const PERIODI_GIORNI = array(
		'1_mese' => 30,
		'3_mesi' => 90,
		'6_mesi' => 180,
		'1_anno' => 365,
	);

	const STATI_VENDITA_VALIDA = array( 'wc-completed', 'wc-processing' );

	/**
	 * Registra la rotta REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/vendite',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'periodo' => array(
						'type'    => 'string',
						'default' => '1_mese',
						'enum'    => array_keys( self::PERIODI_GIORNI ),
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
	 * Report vendite nel periodo richiesto.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$periodo    = $request->get_param( 'periodo' );
		$cache_key  = 'vendite_' . $periodo;
		$dati_cache = wp_cache_get( $cache_key, 'push_contempt' );

		if ( false === $dati_cache ) {
			$dati_cache = self::calcola( $periodo );
			// 5 minuti: un report puo' essere richiesto piu' volte di seguito
			// (dashboard, aggiornamento pagina) senza bisogno di dati freschi al secondo.
			wp_cache_set( $cache_key, $dati_cache, 'push_contempt', 5 * MINUTE_IN_SECONDS );
		}

		return new WP_REST_Response( $dati_cache, 200 );
	}

	/**
	 * Query vere e proprie, separate da handle() per restare sotto cache.
	 *
	 * @param string $periodo Uno tra le chiavi di PERIODI_GIORNI.
	 */
	private static function calcola( string $periodo ): array {
		global $wpdb;

		$giorni = self::PERIODI_GIORNI[ $periodo ];
		$da     = gmdate( 'Y-m-d H:i:s', time() - ( $giorni * DAY_IN_SECONDS ) );

		$placeholder_stati = implode( ', ', array_fill( 0, count( self::STATI_VENDITA_VALIDA ), '%s' ) );
		$tabella_lookup    = $wpdb->prefix . 'wc_order_product_lookup';
		$tabella_orders    = $wpdb->prefix . 'wc_orders';

		// Niente funzione nativa WooCommerce per questa aggregazione: query diretta
		// sulle tabelle HPOS, nomi tabella e placeholder_stati generati da noi
		// (non input utente), non da $wpdb->prepare() - sicuro nonostante il sniff.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		// Caching gestito da handle() con wp_cache_get/set, non qui riga per riga.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$piu_venduti = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					lk.product_id AS id,
					p.post_title AS titolo,
					SUM(lk.product_qty) AS quantita_venduta,
					SUM(lk.product_net_revenue) AS ricavo_netto
				FROM {$tabella_lookup} lk
				INNER JOIN {$tabella_orders} o ON o.id = lk.order_id
				LEFT JOIN {$wpdb->posts} p ON p.ID = lk.product_id
				WHERE o.status IN ({$placeholder_stati}) AND lk.date_created >= %s
				GROUP BY lk.product_id
				ORDER BY quantita_venduta DESC",
				array_merge( self::STATI_VENDITA_VALIDA, array( $da ) )
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- vedi nota sopra.
		$mai_venduti = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_title AS titolo
				FROM {$wpdb->posts} p
				WHERE p.post_type = 'product' AND p.post_status = 'publish'
					AND p.ID NOT IN (
						SELECT DISTINCT lk.product_id
						FROM {$tabella_lookup} lk
						INNER JOIN {$tabella_orders} o ON o.id = lk.order_id
						WHERE o.status IN ({$placeholder_stati})
					)
				ORDER BY p.post_title",
				self::STATI_VENDITA_VALIDA
			),
			ARRAY_A
		);
		// phpcs:enable

		$ricavo_totale = 0.0;
		foreach ( $piu_venduti as &$riga ) {
			$riga['id']               = (int) $riga['id'];
			$riga['quantita_venduta'] = (int) $riga['quantita_venduta'];
			$riga['ricavo_netto']     = round( (float) $riga['ricavo_netto'], 2 );
			$ricavo_totale           += $riga['ricavo_netto'];
		}
		unset( $riga );

		foreach ( $mai_venduti as &$prodotto ) {
			$prodotto['id'] = (int) $prodotto['id'];
		}
		unset( $prodotto );

		return array(
			'periodo'               => $periodo,
			'dal'                   => substr( $da, 0, 10 ),
			'piu_venduti'           => $piu_venduti,
			'mai_venduti'           => $mai_venduti,
			'ricavo_totale_periodo' => round( $ricavo_totale, 2 ),
		);
	}
}
