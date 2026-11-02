<?php
/**
 * Test dei dati strutturati dei prodotti variabili su WooCommerce vero.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

use PushContempt\SchemaVarianti;

/**
 * Il markup passa da WC_Structured_Data::generate_product_data(), come nella
 * pagina del prodotto, poi dal nostro filtro.
 */
class SchemaVariantiTest extends RestTestCase {

	/**
	 * Il markup Product/ProductGroup che WooCommerce scrive per il prodotto.
	 *
	 * @param \WC_Product $prodotto Il prodotto.
	 */
	private function markup( \WC_Product $prodotto, bool $nella_scheda = true ): array {
		if ( $nella_scheda ) {
			$this->go_to( get_permalink( $prodotto->get_id() ) );
		}
		$dati = new \WC_Structured_Data();
		$dati->generate_product_data( $prodotto );
		$trovati = array_values( array_filter( $dati->get_data(), static fn( $m ) => isset( $m['@type'] ) && in_array( $m['@type'], array( 'Product', 'ProductGroup' ), true ) ) );
		$this->assertCount( 1, $trovati );
		return $trovati[0];
	}

	public function tear_down(): void {
		delete_option( SchemaVarianti::OPZIONE );
		remove_all_filters( SchemaVarianti::OPZIONE );
		parent::tear_down();
	}

	public function test_un_variabile_diventa_un_product_group_con_una_offerta_per_variante(): void {
		$padre  = $this->felpa( array( '30', '30', '35', '35' ) );
		$gruppo = $this->markup( $padre );

		$this->assertSame( 'ProductGroup', $gruppo['@type'] );
		$this->assertSame( (string) $padre->get_id(), $gruppo['productGroupID'] );
		$this->assertSame( array( 'https://schema.org/color', 'https://schema.org/size' ), $gruppo['variesBy'] );
		$this->assertCount( 4, $gruppo['hasVariant'] );

		$prima = $gruppo['hasVariant'][0];
		$this->assertSame( 'Nero', $prima['color'] );
		$this->assertSame( 'M', $prima['size'] );
		$this->assertSame( '30.00', $prima['offers']['price'] );
		$this->assertSame( 'https://schema.org/InStock', $prima['offers']['availability'] );
		$this->assertSame( get_permalink( $padre->get_id() ), $prima['url'], 'Tutte le varianti puntano alla pagina del padre, che e\' il canonical.' );
		$this->assertSame( get_permalink( $padre->get_id() ) . '#variant-' . $padre->get_children()[0], $prima['@id'] );
		$this->assertStringNotContainsString( '&', $prima['url'] . $prima['offers']['url'] );
		$this->assertSame( (string) $padre->get_children()[0], $prima['sku'], 'Senza SKU si usa l\'id della variante.' );
	}

	public function test_lo_sku_della_variante_e_il_suo_codice_se_c_e(): void {
		$padre = $this->felpa();
		$v     = wc_get_product( $padre->get_children()[1] );
		$v->set_sku( 'FEL-NL' );
		$v->save();

		$this->assertSame( 'FEL-NL', $this->markup( wc_get_product( $padre->get_id() ) )['hasVariant'][1]['sku'] );
	}

	public function test_la_descrizione_e_l_immagine_della_variante_arrivano_nei_dati(): void {
		$padre = $this->felpa();
		$img   = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$v     = wc_get_product( $padre->get_children()[0] );
		$v->set_description( '<p>Nera, taglia M</p>' );
		$v->set_image_id( $img );
		$v->save();

		$variante = $this->markup( wc_get_product( $padre->get_id() ) )['hasVariant'][0];
		$this->assertSame( 'Nera, taglia M', $variante['description'] );
		$this->assertSame( wp_get_attachment_url( $img ), $variante['image'] );
	}

	public function test_una_variante_esaurita_resta_ma_non_disponibile_e_una_privata_sparisce(): void {
		$padre = $this->felpa();
		$figli = $padre->get_children();
		$esaurita = wc_get_product( $figli[0] );
		$esaurita->set_stock_status( 'outofstock' );
		$esaurita->save();
		$privata = wc_get_product( $figli[1] );
		$privata->set_status( 'private' );
		$privata->save();

		$gruppo = $this->markup( wc_get_product( $padre->get_id() ) );
		$this->assertCount( 3, $gruppo['hasVariant'] );
		$this->assertSame( 'https://schema.org/OutOfStock', $gruppo['hasVariant'][0]['offers']['availability'] );
	}

	public function test_una_variante_in_offerta_ha_il_prezzo_di_listino(): void {
		$padre = $this->felpa();
		$v     = wc_get_product( $padre->get_children()[0] );
		$v->set_regular_price( '30' );
		$v->set_sale_price( '24' );
		$v->save();

		$offerta = $this->markup( wc_get_product( $padre->get_id() ) )['hasVariant'][0]['offers'];
		$this->assertSame( '24.00', $offerta['price'] );
		$this->assertSame( '30.00', $offerta['priceSpecification'][1]['price'] );
		$this->assertSame( 'https://schema.org/ListPrice', $offerta['priceSpecification'][1]['priceType'] );
	}

	public function test_una_variante_senza_prezzo_non_e_nella_scheda_e_quindi_nemmeno_nel_gruppo(): void {
		$padre  = $this->felpa( array( '30', '', '35', '35' ) );
		$gruppo = $this->markup( $padre );

		$this->assertSame( 'ProductGroup', $gruppo['@type'] );
		$this->assertCount( 3, $gruppo['hasVariant'] );
		$this->assertNotContains( $padre->get_children()[1], array_map( 'intval', array_column( $gruppo['hasVariant'], 'sku' ) ) );
	}

	public function test_l_opzione_e_il_filtro_spengono_la_funzione(): void {
		$padre = $this->felpa();

		update_option( SchemaVarianti::OPZIONE, 'no' );
		$this->assertSame( 'Product', $this->markup( $padre )['@type'] );

		update_option( SchemaVarianti::OPZIONE, 'si' );
		add_filter( SchemaVarianti::OPZIONE, '__return_false' );
		$this->assertSame( 'Product', $this->markup( $padre )['@type'] );
	}

	public function test_fuori_dalla_scheda_del_prodotto_resta_il_markup_di_woocommerce(): void {
		$padre = $this->felpa();
		$this->go_to( home_url( '/' ) );
		$this->assertSame( 'Product', $this->markup( $padre, false )['@type'] );

		// Un altro prodotto nella scheda (es. fra i correlati): non e' quello mostrato.
		$altro = $this->felpa();
		$this->go_to( get_permalink( $altro->get_id() ) );
		$this->assertSame( 'Product', $this->markup( $padre, false )['@type'] );
	}

	public function test_il_tipo_productgroup_e_tra_quelli_che_woocommerce_stampa_nella_scheda(): void {
		$padre = $this->felpa();
		$this->go_to( get_permalink( $padre->get_id() ) );
		$this->assertContains( 'productgroup', apply_filters( 'woocommerce_structured_data_type_for_page', array( 'product' ) ) );

		$this->go_to( home_url( '/' ) );
		$this->assertNotContains( 'productgroup', apply_filters( 'woocommerce_structured_data_type_for_page', array( 'product' ) ) );
	}

	public function test_la_pagina_stampa_davvero_il_product_group(): void {
		$padre = $this->felpa();
		$this->go_to( get_permalink( $padre->get_id() ) );
		$dati = WC()->structured_data;
		$dati->set_data( array(), true );
		$dati->generate_product_data( $padre );

		$stampato = $dati->get_structured_data( array( 'product', 'productgroup', 'breadcrumblist' ) );
		$this->assertSame( 'ProductGroup', $stampato['@type'] );
	}

	public function test_un_prodotto_semplice_non_cambia(): void {
		$this->assertSame( 'Product', $this->markup( wc_get_product( $this->prodotto() ) )['@type'] );
	}
}
