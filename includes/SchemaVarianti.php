<?php
/**
 * Collega ProductGroupSchema al markup che WooCommerce scrive per i prodotti variabili.
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Sul filtro `woocommerce_structured_data_product`: per un variabile, nella sua scheda (non per
 * una variante gia' scelta nell'indirizzo, che WooCommerce descrive da solo con
 * `inProductGroupWithID`) legge le varianti in vendita, con i prezzi come li
 * mostra il negozio (imposte comprese o escluse secondo l'impostazione), e
 * lascia a `ProductGroupSchema` il compito di costruire il gruppo.
 *
 * Si spegne con l'opzione `push_contempt_schema_varianti` = "no" o con il
 * filtro `push_contempt_schema_varianti` (restituire false).
 */
class SchemaVarianti {

	const OPZIONE = 'push_contempt_schema_varianti';

	/**
	 * Aggancia il filtro (chiamato all'avvio del plugin).
	 */
	public static function register(): void {
		add_filter( 'woocommerce_structured_data_product', array( self::class, 'filtra' ), 20, 2 );
		add_filter( 'woocommerce_structured_data_type_for_page', array( self::class, 'tipi_della_pagina' ) );
	}

	/**
	 * WooCommerce stampa solo i tipi previsti per la pagina e raggruppa per `@type` in minuscolo:
	 * un "ProductGroup" non elencato verrebbe scartato in silenzio, insieme a tutto il prodotto.
	 *
	 * @param array $tipi Tipi previsti per la pagina.
	 */
	public static function tipi_della_pagina( $tipi ) {
		if ( is_array( $tipi ) && function_exists( 'is_product' ) && is_product() ) {
			$tipi[] = 'productgroup';
		}
		return $tipi;
	}

	/**
	 * Se la funzione e' accesa (di default si').
	 */
	public static function attiva(): bool {
		return (bool) apply_filters( self::OPZIONE, 'no' !== get_option( self::OPZIONE, 'si' ) );
	}

	/**
	 * Il markup, trasformato in ProductGroup se e' un variabile.
	 *
	 * @param array       $markup   Markup `Product` di WooCommerce.
	 * @param \WC_Product $prodotto Il prodotto.
	 */
	public static function filtra( $markup, $prodotto ) {
		if (
			! is_array( $markup )
			|| ! $prodotto instanceof \WC_Product_Variable
			|| isset( $markup['inProductGroupWithID'] )
			|| ! self::e_la_scheda_del_prodotto( $prodotto )
			|| ! self::attiva()
		) {
			return $markup;
		}

		$gruppo_id = $prodotto->get_sku() ? $prodotto->get_sku() : (string) $prodotto->get_id();
		return ProductGroupSchema::gruppo( $markup, $gruppo_id, self::varianti( $prodotto ) );
	}

	/**
	 * Se si sta mostrando la scheda di questo prodotto: negli archivi e fra i prodotti correlati
	 * WooCommerce scrive un `Product` per ogni prodotto in elenco, e li lasciamo com'e'.
	 *
	 * @param \WC_Product $prodotto Il prodotto.
	 */
	private static function e_la_scheda_del_prodotto( \WC_Product $prodotto ): bool {
		return function_exists( 'is_product' ) && is_product() && get_queried_object_id() === $prodotto->get_id();
	}

	/**
	 * Le varianti che il negozio mostra, nel formato di ProductGroupSchema.
	 *
	 * @param \WC_Product_Variable $prodotto Il padre.
	 */
	private static function varianti( \WC_Product_Variable $prodotto ): array {
		$righe = array();
		foreach ( $prodotto->get_children() as $variante_id ) {
			$variante = wc_get_product( $variante_id );
			if ( ! $variante instanceof \WC_Product_Variation || ! $variante->variation_is_visible() ) {
				continue;
			}
			$righe[] = self::riga( $variante, $prodotto );
		}
		return $righe;
	}

	/**
	 * Una variante nel formato di ProductGroupSchema.
	 *
	 * @param \WC_Product_Variation $variante Variante.
	 * @param \WC_Product_Variable  $padre    Il padre.
	 */
	private static function riga( \WC_Product_Variation $variante, \WC_Product_Variable $padre ): array {
		$attributi   = AttributiVarianti::della_variante( $variante, $padre );
		$immagine_id = (int) $variante->get_image_id( 'edit' );
		$in_offerta  = $variante->is_on_sale();

		if ( ! $variante->is_in_stock() ) {
			$disponibilita = 'OutOfStock';
		} else {
			$disponibilita = 'onbackorder' === $variante->get_stock_status() ? 'BackOrder' : 'InStock';
		}

		return array(
			'id'             => $variante->get_id(),
			'url'            => get_permalink( $padre->get_id() ), // Il canonical e' la pagina del padre; e un indirizzo con ?attribute_..&.. finirebbe con &amp; nel JSON (WooCommerce escapa l'HTML).
			'nome'           => trim( $padre->get_name() . ' ' . implode( ', ', array_filter( $attributi, 'strlen' ) ) ),
			'sku'            => $variante->get_sku() ? $variante->get_sku() : (string) $variante->get_id(),
			'descrizione'    => wp_strip_all_tags( (string) $variante->get_description( 'edit' ) ),
			'immagine'       => $immagine_id ? (string) wp_get_attachment_url( $immagine_id ) : '',
			'gtin'           => preg_replace( '/\D/', '', (string) $variante->get_global_unique_id() ),
			'prezzo'         => self::prezzo( $variante, (string) $variante->get_price() ),
			'prezzo_listino' => $in_offerta ? self::prezzo( $variante, (string) $variante->get_regular_price() ) : '',
			'valuta'         => get_woocommerce_currency(),
			'iva_inclusa'    => wc_tax_enabled() ? 'incl' === get_option( 'woocommerce_tax_display_shop' ) : null,
			'disponibilita'  => $disponibilita,
			'valido_fino'    => gmdate( 'Y-12-31', time() + YEAR_IN_SECONDS ),
			'attributi'      => $attributi,
			'venditore'      => array(
				'nome' => get_bloginfo( 'name' ),
				'url'  => home_url(),
			),
		);
	}

	/**
	 * Un prezzo come lo mostra il negozio: con o senza imposte, con i decimali impostati.
	 *
	 * @param \WC_Product_Variation $variante Variante.
	 * @param string                $prezzo   Prezzo grezzo ('' se manca).
	 */
	private static function prezzo( \WC_Product_Variation $variante, string $prezzo ): string {
		if ( '' === $prezzo ) {
			return '';
		}
		$mostrato = 'incl' === get_option( 'woocommerce_tax_display_shop' )
			? wc_get_price_including_tax( $variante, array( 'price' => $prezzo ) )
			: wc_get_price_excluding_tax( $variante, array( 'price' => $prezzo ) );
		return wc_format_decimal( $mostrato, wc_get_price_decimals() );
	}
}
