<?php
/**
 * Endpoint REST per consultare gli ordini WooCommerce (sola lettura).
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use DateTimeZone;
use InvalidArgumentException;
use PushContempt\OrderFilters;
use WC_Order;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Elenco ordini filtrabile per stato e finestra temporale, e dettaglio di
 * un singolo ordine.
 *
 * Usa wc_get_orders(), quindi funziona sia con HPOS sia con gli ordini
 * classici. Gli stati sono quelli nativi di WooCommerce (pending,
 * processing, on-hold, completed, cancelled, refunded, failed, piu'
 * eventuali stati personalizzati registrati da altri plugin): niente
 * stati inventati.
 *
 * Dati personali: l'elenco restituisce solo il necessario (numero, data,
 * stato, totale, prodotti, citta'); nome, contatti e indirizzi completi
 * stanno solo nel dettaglio, richiesto esplicitamente per un ordine.
 */
class OrdiniController {

	const LIMITE_DEFAULT = 50;
	const LIMITE_MASSIMO = 200;

	/**
	 * Registra le rotte REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/ordini',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'handle_elenco' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'stato'             => array(
						'type'    => 'string',
						'default' => '',
					),
					'ultime_ore'        => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'piu_vecchi_di_ore' => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'da'                => array( 'type' => 'string' ),
					'a'                 => array( 'type' => 'string' ),
					'ordina'            => array(
						'type'    => 'string',
						'default' => 'desc',
						'enum'    => array( 'asc', 'desc' ),
					),
					'limite'            => array(
						'type'    => 'integer',
						'default' => self::LIMITE_DEFAULT,
						'minimum' => 1,
						'maximum' => self::LIMITE_MASSIMO,
					),
				),
			)
		);

		register_rest_route(
			'push-contempt/v1',
			'/ordini/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'handle_dettaglio' ),
				'permission_callback' => array( self::class, 'permission' ),
			)
		);
	}

	/**
	 * Solo chi gestisce il negozio puo' leggere gli ordini.
	 */
	public static function permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Elenco ordini secondo i filtri richiesti.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_elenco( WP_REST_Request $request ) {
		$fuso = wp_timezone();

		try {
			$stati = OrderFilters::parse_stati( (string) $request->get_param( 'stato' ), self::stati_validi() );

			$da                    = $request->get_param( 'da' );
			$a                     = $request->get_param( 'a' );
			list( $inizio, $fine ) = OrderFilters::intervallo(
				time(),
				self::intero_o_null( $request->get_param( 'ultime_ore' ) ),
				self::intero_o_null( $request->get_param( 'piu_vecchi_di_ore' ) ),
				$da ? OrderFilters::parse_data( $da, $fuso ) : null,
				$a ? OrderFilters::parse_data( $a, $fuso, true ) : null
			);
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'push_contempt_ordini_filtri_non_validi', $e->getMessage(), array( 'status' => 400 ) );
		}

		$argomenti = array(
			'limit'    => (int) $request->get_param( 'limite' ),
			'orderby'  => 'date',
			'order'    => strtoupper( $request->get_param( 'ordina' ) ),
			'paginate' => true,
		);
		// Senza filtro: tutti gli stati veri (wc_get_orders includerebbe anche le bozze checkout).
		$argomenti['status'] = $stati ? $stati : self::stati_validi();
		$date_created        = OrderFilters::date_created( $inizio, $fine );
		if ( null !== $date_created ) {
			$argomenti['date_created'] = $date_created;
		}

		$risultato = wc_get_orders( $argomenti );

		return new WP_REST_Response(
			array(
				'totale_trovati' => (int) $risultato->total,
				'restituiti'     => count( $risultato->orders ),
				'ordini'         => array_map( array( self::class, 'riepilogo' ), $risultato->orders ),
			),
			200
		);
	}

	/**
	 * Dettaglio completo di un ordine, dati del cliente inclusi.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_dettaglio( WP_REST_Request $request ) {
		$ordine = wc_get_order( (int) $request->get_param( 'id' ) );

		// I rimborsi sono oggetti "ordine" a parte: qui interessano solo gli ordini veri.
		if ( ! $ordine instanceof WC_Order ) {
			return new WP_Error( 'push_contempt_ordine_non_trovato', 'Ordine non trovato.', array( 'status' => 404 ) );
		}

		return new WP_REST_Response( self::dettaglio( $ordine ), 200 );
	}

	/**
	 * Slug degli stati ordine noti a WooCommerce, senza prefisso "wc-".
	 *
	 * Esclude "checkout-draft": e' la bozza interna del checkout a blocchi,
	 * non un ordine vero (non e' mai stata inviata dal cliente).
	 *
	 * @return string[]
	 */
	private static function stati_validi(): array {
		$slug = array_map(
			static fn( $chiave ) => 0 === strpos( $chiave, 'wc-' ) ? substr( $chiave, 3 ) : $chiave,
			array_keys( wc_get_order_statuses() )
		);

		return array_values( array_diff( $slug, array( 'checkout-draft' ) ) );
	}

	/**
	 * Intero se il parametro e' stato passato, altrimenti null.
	 *
	 * @param mixed $valore Valore grezzo del parametro REST.
	 */
	private static function intero_o_null( $valore ): ?int {
		return null === $valore || '' === $valore ? null : (int) $valore;
	}

	/**
	 * Data nel fuso del sito in formato ISO 8601, o null se assente.
	 *
	 * @param \WC_DateTime|null $data Data WooCommerce.
	 */
	private static function data_iso( $data ): ?string {
		return $data ? wp_date( 'c', $data->getTimestamp() ) : null;
	}

	/**
	 * Importo come numero con 2 decimali.
	 *
	 * @param mixed $valore Importo (stringa o numero).
	 */
	private static function importo( $valore ): float {
		return round( (float) $valore, 2 );
	}

	/**
	 * Riga dell'elenco: solo il necessario, senza dati personali del cliente
	 * oltre alla citta' di spedizione (o fatturazione se manca).
	 *
	 * @param WC_Order $ordine Ordine WooCommerce.
	 */
	private static function riepilogo( WC_Order $ordine ): array {
		$prodotti = array();
		foreach ( $ordine->get_items() as $riga ) {
			$prodotti[] = array(
				'nome'     => $riga->get_name(),
				'quantita' => (int) $riga->get_quantity(),
			);
		}

		$stato = $ordine->get_status();

		return array(
			'id'              => $ordine->get_id(),
			'numero'          => $ordine->get_order_number(),
			'stato'           => $stato,
			'stato_etichetta' => wc_get_order_status_name( $stato ),
			'data_creazione'  => self::data_iso( $ordine->get_date_created() ),
			'totale'          => self::importo( $ordine->get_total() ),
			'valuta'          => $ordine->get_currency(),
			'pagamento'       => $ordine->get_payment_method_title(),
			'citta'           => $ordine->get_shipping_city() ? $ordine->get_shipping_city() : $ordine->get_billing_city(),
			'prodotti'        => $prodotti,
		);
	}

	/**
	 * Dettaglio completo: riepilogo piu' righe con id prodotto, totali,
	 * dati del cliente, spedizione e note.
	 *
	 * @param WC_Order $ordine Ordine WooCommerce.
	 */
	private static function dettaglio( WC_Order $ordine ): array {
		$righe = array();
		foreach ( $ordine->get_items() as $riga ) {
			$righe[] = array(
				'prodotto_id' => $riga->get_product_id(),
				'variante_id' => $riga->get_variation_id(),
				'nome'        => $riga->get_name(),
				'quantita'    => (int) $riga->get_quantity(),
				'totale'      => self::importo( $riga->get_total() ),
			);
		}

		return array_merge(
			self::riepilogo( $ordine ),
			array(
				'data_pagamento'         => self::data_iso( $ordine->get_date_paid() ),
				'data_completamento'     => self::data_iso( $ordine->get_date_completed() ),
				'righe'                  => $righe,
				'importi'                => array(
					'subtotale'  => self::importo( $ordine->get_subtotal() ),
					'sconto'     => self::importo( $ordine->get_discount_total() ),
					'spedizione' => self::importo( $ordine->get_shipping_total() ),
					'tasse'      => self::importo( $ordine->get_total_tax() ),
					'totale'     => self::importo( $ordine->get_total() ),
				),
				'metodo_spedizione'      => $ordine->get_shipping_method(),
				'cliente'                => array(
					'nome'     => trim( $ordine->get_billing_first_name() . ' ' . $ordine->get_billing_last_name() ),
					'email'    => $ordine->get_billing_email(),
					'telefono' => $ordine->get_billing_phone(),
				),
				'indirizzo_fatturazione' => $ordine->get_formatted_billing_address( '' ),
				'indirizzo_spedizione'   => $ordine->get_formatted_shipping_address( '' ),
				'nota_cliente'           => $ordine->get_customer_note(),
			)
		);
	}
}
