<?php
/**
 * Test dei dati strutturati ProductGroup.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\ProductGroupSchema;

/**
 * Logica pura: nessun bootstrap WordPress.
 */
class ProductGroupSchemaTest extends TestCase {

	/**
	 * Il markup che WooCommerce scrive per un variabile.
	 */
	private function markup_woocommerce(): array {
		return array(
			'@type'           => 'Product',
			'@id'             => 'https://x.it/felpa/#product',
			'name'            => 'Felpa',
			'url'             => 'https://x.it/felpa/',
			'description'     => 'Felpa in cotone.',
			'image'           => 'https://x.it/felpa.jpg',
			'sku'             => 142,
			'gtin'            => '12345678',
			'brand'           => array( '@type' => 'Brand', 'name' => 'Acme' ),
			'aggregateRating' => array( '@type' => 'AggregateRating', 'ratingValue' => '4.5' ),
			'offers'          => array( array( '@type' => 'AggregateOffer', 'lowPrice' => '30.00', 'highPrice' => '35.00' ) ),
		);
	}

	/**
	 * Una variante, con i campi cambiati.
	 *
	 * @param int   $id      Id della variante.
	 * @param array $diversi Campi da cambiare.
	 */
	private function variante( int $id, array $diversi = array() ): array {
		return array_merge(
			array(
				'id'             => $id,
				'url'            => 'https://x.it/felpa/?attribute_colore=Nero&attribute_taglia=M' . $id,
				'nome'           => 'Felpa Nero, M' . $id,
				'sku'            => 'FEL-' . $id,
				'descrizione'    => '',
				'immagine'       => '',
				'gtin'           => '',
				'prezzo'         => '30.00',
				'prezzo_listino' => '',
				'valuta'         => 'EUR',
				'iva_inclusa'    => true,
				'disponibilita'  => 'InStock',
				'valido_fino'    => '2027-12-31',
				'attributi'      => array( 'Colore' => 'Nero', 'Taglia' => 'M' . $id ),
				'venditore'      => array( 'nome' => 'Neofusion', 'url' => 'https://x.it' ),
			),
			$diversi
		);
	}

	public function test_il_variabile_diventa_un_product_group_con_le_varianti_e_senza_offerta_sul_gruppo(): void {
		$gruppo = ProductGroupSchema::gruppo( $this->markup_woocommerce(), '142', array( $this->variante( 1 ), $this->variante( 2 ) ) );

		$this->assertSame( 'ProductGroup', $gruppo['@type'] );
		$this->assertSame( '142', $gruppo['productGroupID'] );
		$this->assertSame( array( 'https://schema.org/color', 'https://schema.org/size' ), $gruppo['variesBy'] );
		$this->assertCount( 2, $gruppo['hasVariant'] );
		$this->assertArrayNotHasKey( 'offers', $gruppo );
		$this->assertArrayNotHasKey( 'sku', $gruppo );
		$this->assertArrayNotHasKey( 'gtin', $gruppo, 'Il GTIN identifica un solo articolo: non resta sul gruppo.' );
	}

	public function test_nome_indirizzo_marca_e_valutazioni_del_markup_originale_restano(): void {
		$gruppo = ProductGroupSchema::gruppo( $this->markup_woocommerce(), '142', array( $this->variante( 1 ) ) );

		$this->assertSame( 'Felpa', $gruppo['name'] );
		$this->assertSame( 'https://x.it/felpa/#product', $gruppo['@id'] );
		$this->assertSame( 'Acme', $gruppo['brand']['name'] );
		$this->assertSame( '4.5', $gruppo['aggregateRating']['ratingValue'] );
		$this->assertSame( 'https://x.it/felpa.jpg', $gruppo['image'] );
	}

	public function test_ogni_variante_ha_codice_colore_taglia_e_offerta_con_prezzo_e_disponibilita(): void {
		$gruppo   = ProductGroupSchema::gruppo( $this->markup_woocommerce(), '142', array( $this->variante( 1, array( 'disponibilita' => 'OutOfStock' ) ) ) );
		$variante = $gruppo['hasVariant'][0];

		$this->assertSame( 'Product', $variante['@type'] );
		$this->assertSame( 'FEL-1', $variante['sku'] );
		$this->assertSame( '142', $variante['inProductGroupWithID'] );
		$this->assertSame( 'Nero', $variante['color'] );
		$this->assertSame( 'M1', $variante['size'] );
		$this->assertSame( '30.00', $variante['offers']['price'] );
		$this->assertSame( 'EUR', $variante['offers']['priceCurrency'] );
		$this->assertSame( 'https://schema.org/OutOfStock', $variante['offers']['availability'] );
		$this->assertTrue( $variante['offers']['priceSpecification'][0]['valueAddedTaxIncluded'] );
		$this->assertSame( 'Neofusion', $variante['offers']['seller']['name'] );
	}

	public function test_un_attributo_senza_proprieta_schema_va_in_additional_property_e_in_variesby_per_nome(): void {
		$v      = $this->variante( 1, array( 'attributi' => array( 'Memoria' => '256 GB' ) ) );
		$gruppo = ProductGroupSchema::gruppo( $this->markup_woocommerce(), '142', array( $v ) );

		$this->assertSame( array( 'Memoria' ), $gruppo['variesBy'] );
		$this->assertSame(
			array( array( '@type' => 'PropertyValue', 'name' => 'Memoria', 'value' => '256 GB' ) ),
			$gruppo['hasVariant'][0]['additionalProperty']
		);
		$this->assertArrayNotHasKey( 'color', $gruppo['hasVariant'][0] );
	}

	public function test_etichette_in_italiano_e_inglese_sono_riconosciute_senza_badare_alle_maiuscole(): void {
		$this->assertSame( 'color', ProductGroupSchema::proprieta( ' COLORE ' ) );
		$this->assertSame( 'size', ProductGroupSchema::proprieta( 'Misura' ) );
		$this->assertSame( 'material', ProductGroupSchema::proprieta( 'Material' ) );
		$this->assertSame( 'pattern', ProductGroupSchema::proprieta( 'Fantasia' ) );
		$this->assertNull( ProductGroupSchema::proprieta( 'Memoria' ) );
	}

	public function test_un_prezzo_scontato_aggiunge_il_prezzo_di_listino_con_il_tipo_solo_su_quello(): void {
		$v      = $this->variante( 1, array( 'prezzo' => '24.00', 'prezzo_listino' => '30.00' ) );
		$offerta = ProductGroupSchema::gruppo( $this->markup_woocommerce(), '142', array( $v ) )['hasVariant'][0]['offers'];

		$this->assertSame( '24.00', $offerta['price'] );
		$this->assertArrayNotHasKey( 'priceType', $offerta['priceSpecification'][0] );
		$this->assertSame( '30.00', $offerta['priceSpecification'][1]['price'] );
		$this->assertSame( 'https://schema.org/ListPrice', $offerta['priceSpecification'][1]['priceType'] );
	}

	public function test_senza_imposte_non_c_e_value_added_tax_included(): void {
		$v = $this->variante( 1, array( 'iva_inclusa' => null ) );
		$specifica = ProductGroupSchema::gruppo( $this->markup_woocommerce(), '142', array( $v ) )['hasVariant'][0]['offers']['priceSpecification'][0];
		$this->assertArrayNotHasKey( 'valueAddedTaxIncluded', $specifica );
	}

	public function test_descrizione_immagine_e_gtin_della_variante_ci_sono_solo_se_validi(): void {
		$con   = $this->variante( 1, array( 'descrizione' => 'Nera, taglia M', 'immagine' => 'https://x.it/n.jpg', 'gtin' => '4006381333931' ) );
		$senza = $this->variante( 2, array( 'gtin' => '123' ) );
		$gruppo = ProductGroupSchema::gruppo( $this->markup_woocommerce(), '142', array( $con, $senza ) );

		$this->assertSame( 'Nera, taglia M', $gruppo['hasVariant'][0]['description'] );
		$this->assertSame( 'https://x.it/n.jpg', $gruppo['hasVariant'][0]['image'] );
		$this->assertSame( '4006381333931', $gruppo['hasVariant'][0]['gtin'] );
		$this->assertArrayNotHasKey( 'description', $gruppo['hasVariant'][1] );
		$this->assertArrayNotHasKey( 'image', $gruppo['hasVariant'][1] );
		$this->assertArrayNotHasKey( 'gtin', $gruppo['hasVariant'][1] );
	}

	/**
	 * @dataProvider casi_inaffidabili
	 *
	 * @param array $varianti Varianti che non si possono descrivere con certezza.
	 */
	public function test_con_dati_inaffidabili_il_markup_di_woocommerce_resta_invariato( array $varianti ): void {
		$originale = $this->markup_woocommerce();
		$this->assertSame( $originale, ProductGroupSchema::gruppo( $originale, '142', $varianti ) );
	}

	/**
	 * Casi in cui non si deve generare il gruppo.
	 */
	public function casi_inaffidabili(): array {
		return array(
			'nessuna variante'      => array( array() ),
			'prezzo mancante'       => array( array( $this->variante( 1, array( 'prezzo' => '' ) ) ) ),
			'attributo qualsiasi'   => array( array( $this->variante( 1, array( 'attributi' => array( 'Colore' => 'Nero', 'Taglia' => '' ) ) ) ) ),
			'combinazione doppia'   => array( array( $this->variante( 1, array( 'attributi' => array( 'Colore' => 'Nero' ) ) ), $this->variante( 2, array( 'attributi' => array( 'Colore' => 'nero' ) ) ) ) ),
		);
	}
}
