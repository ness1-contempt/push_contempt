<?php
/**
 * Test di tipo e prezzo dei prodotti (semplici, variabili, raggruppati) nelle
 * letture REST: /prodotti e /seo/prodotto.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

/**
 * Il caso da cui nasce: un variabile con varianti a 20 e 29 dava 29 in un
 * elenco e 20 nella scheda SEO, perche' WooCommerce salva minimo e massimo
 * come due righe `_price` del padre.
 */
class PrezzoProdottoTest extends RestTestCase {

	/**
	 * Un variabile con una variante per ogni prezzo dato (taglia => prezzo; null = senza prezzo).
	 *
	 * @param array  $varianti Taglia => prezzo regolare.
	 * @param string $stato    Stato di ciascuna variante.
	 */
	private function variabile( array $varianti, string $stato = 'publish' ): \WC_Product_Variable {
		$attributo = new \WC_Product_Attribute();
		$attributo->set_name( 'Taglia' );
		$attributo->set_options( array_map( 'strval', array_keys( $varianti ) ) );
		$attributo->set_visible( true );
		$attributo->set_variation( true );

		$padre = new \WC_Product_Variable();
		$padre->set_name( 'Variabile di prova' );
		$padre->set_status( 'publish' );
		$padre->set_attributes( array( $attributo ) );
		$id = $padre->save();

		foreach ( $varianti as $taglia => $prezzo ) {
			$variante = new \WC_Product_Variation();
			$variante->set_parent_id( $id );
			$variante->set_attributes( array( 'taglia' => (string) $taglia ) );
			if ( null !== $prezzo ) {
				$variante->set_regular_price( $prezzo );
			}
			$variante->set_status( $stato );
			$variante->save();
		}
		\WC_Product_Variable::sync( $id );
		return wc_get_product( $id );
	}

	/**
	 * Riga del prodotto nell'elenco /prodotti.
	 *
	 * @param int $id Id del prodotto.
	 */
	private function nell_elenco( int $id ): array {
		foreach ( $this->richiesta( 'GET', '/prodotti', array( 'limite' => -1 ) )->get_data() as $riga ) {
			if ( $riga['id'] === $id ) {
				return $riga;
			}
		}
		$this->fail( "Prodotto {$id} assente dall'elenco" );
	}

	/**
	 * Tipo e prezzi dalla scheda SEO.
	 *
	 * @param int $id Id del prodotto.
	 */
	private function nella_scheda_seo( int $id ): array {
		$dati = $this->richiesta( 'GET', '/seo/prodotto', array( 'id' => $id ) )->get_data();
		return array_intersect_key( $dati, array_flip( array( 'tipo', 'prezzo', 'prezzo_min', 'prezzo_max', 'numero_varianti' ) ) );
	}

	public function test_un_variabile_da_lo_stesso_intervallo_nell_elenco_e_nella_scheda_seo(): void {
		$id = $this->variabile( array( 'S' => '20', 'L' => '29' ) )->get_id();

		$atteso = array(
			'tipo'            => 'variable',
			'prezzo'          => null,
			'prezzo_min'      => '20.00',
			'prezzo_max'      => '29.00',
			'numero_varianti' => 2,
		);
		$this->assertSame( $atteso, $this->nella_scheda_seo( $id ) );
		$this->assertEquals( $atteso, array_intersect_key( $this->nell_elenco( $id ), $atteso ) );
	}

	public function test_un_variabile_con_un_solo_prezzo_ha_minimo_e_massimo_uguali(): void {
		$id = $this->variabile( array( 'S' => '15', 'L' => '15' ) )->get_id();

		$dati = $this->nella_scheda_seo( $id );

		$this->assertSame( $dati['prezzo_min'], $dati['prezzo_max'] );
		$this->assertNull( $dati['prezzo'] );
	}

	public function test_le_varianti_non_pubblicate_non_contano_nell_intervallo(): void {
		$id    = $this->variabile( array( 'S' => '20', 'L' => '29' ) )->get_id();
		$extra = new \WC_Product_Variation();
		$extra->set_parent_id( $id );
		$extra->set_attributes( array( 'taglia' => 'XL' ) );
		$extra->set_regular_price( '99' );
		$extra->set_status( 'private' );
		$extra->save();
		\WC_Product_Variable::sync( $id );

		$dati = $this->nella_scheda_seo( $id );

		$this->assertSame( '29.00', $dati['prezzo_max'] );
		$this->assertSame( 3, $dati['numero_varianti'] );
	}

	public function test_un_variabile_senza_varianti_non_ha_prezzo(): void {
		$id = $this->variabile( array() )->get_id();

		$this->assertSame(
			array(
				'tipo'            => 'variable',
				'prezzo'          => null,
				'prezzo_min'      => null,
				'prezzo_max'      => null,
				'numero_varianti' => 0,
			),
			$this->nella_scheda_seo( $id )
		);
	}

	public function test_un_prodotto_semplice_ha_un_solo_prezzo(): void {
		$id = $this->prodotto();

		$this->assertEquals(
			array(
				'tipo'            => 'simple',
				'prezzo'          => '10',
				'prezzo_min'      => '10',
				'prezzo_max'      => '10',
				'numero_varianti' => null,
			),
			$this->nella_scheda_seo( $id )
		);
	}

	public function test_il_prezzo_in_offerta_e_quello_attivo(): void {
		$prodotto = wc_get_product( $this->prodotto() );
		$prodotto->set_sale_price( '7' );
		$prodotto->save();

		$this->assertSame( '7', $this->nella_scheda_seo( $prodotto->get_id() )['prezzo'] );
	}

	public function test_un_prodotto_senza_prezzo_da_null_non_stringa_vuota(): void {
		$prodotto = new \WC_Product_Simple();
		$prodotto->set_name( 'Senza prezzo' );
		$prodotto->set_status( 'publish' );
		$id = $prodotto->save();

		$dati = $this->nella_scheda_seo( $id );

		$this->assertNull( $dati['prezzo'] );
		$this->assertNull( $dati['prezzo_min'] );
	}

	public function test_un_raggruppato_ha_l_intervallo_dei_figli_pubblicati(): void {
		$economico = $this->prodotto();
		$caro      = $this->prodotto();
		$bozza     = $this->prodotto( array(), 'draft' );
		foreach ( array(
			$economico => '5.50',
			$caro      => '12',
			$bozza     => '1',
		) as $id => $prezzo ) {
			$figlio = wc_get_product( $id );
			$figlio->set_regular_price( $prezzo );
			$figlio->save();
		}
		$gruppo = new \WC_Product_Grouped();
		$gruppo->set_name( 'Gruppo' );
		$gruppo->set_status( 'publish' );
		$gruppo->set_children( array( $economico, $caro, $bozza ) );
		$id = $gruppo->save();

		$this->assertEquals(
			array(
				'tipo'            => 'grouped',
				'prezzo'          => null,
				'prezzo_min'      => '5.50',
				'prezzo_max'      => '12',
				'numero_varianti' => null,
			),
			$this->nella_scheda_seo( $id )
		);
	}

	public function test_l_elenco_cerca_per_titolo_e_riporta_sku_e_scorte(): void {
		$prodotto = wc_get_product( $this->prodotto() );
		$prodotto->set_name( 'Sedia ergonomica unica' );
		$prodotto->set_sku( 'SED-1' );
		$prodotto->set_manage_stock( true );
		$prodotto->set_stock_quantity( 4 );
		$prodotto->save();

		$righe = $this->richiesta( 'GET', '/prodotti', array( 'ricerca' => 'ergonomica unica' ) )->get_data();

		$this->assertCount( 1, $righe );
		$this->assertSame( 'SED-1', $righe[0]['sku'] );
		$this->assertSame( 4, $righe[0]['scorte'] );
		$this->assertSame( 'publish', $righe[0]['stato'] );
	}
}
