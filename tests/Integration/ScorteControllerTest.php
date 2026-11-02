<?php
/**
 * Test di /scorte, in particolare per le varianti dei prodotti variabili.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

/**
 * In un prodotto variabile le scorte stanno di solito sulle varianti: il padre
 * non deve nascondere una taglia esaurita o quasi.
 */
class ScorteControllerTest extends RestTestCase {

	/**
	 * Un variabile con una variante per ogni voce: taglia => impostazioni di scorta.
	 *
	 * Ogni voce: `gestione` (true, false o 'parent'), `quantita`, `stato` (stock status), `stato_post`.
	 *
	 * @param array $varianti    Taglia => impostazioni.
	 * @param array $padre_scorte Scorte del padre: `quantita` (la imposta e attiva la gestione) o vuoto.
	 * @param string $stato      Stato del padre.
	 */
	private function variabile( array $varianti, array $padre_scorte = array(), string $stato = 'publish' ): int {
		$attributo = new \WC_Product_Attribute();
		$attributo->set_name( 'Taglia' );
		$attributo->set_options( array_map( 'strval', array_keys( $varianti ) ) );
		$attributo->set_visible( true );
		$attributo->set_variation( true );

		$padre = new \WC_Product_Variable();
		$padre->set_name( 'Maglia' );
		$padre->set_status( $stato );
		$padre->set_attributes( array( $attributo ) );
		if ( isset( $padre_scorte['quantita'] ) ) {
			$padre->set_manage_stock( true );
			$padre->set_stock_quantity( $padre_scorte['quantita'] );
		}
		$id = $padre->save();

		foreach ( $varianti as $taglia => $impostazioni ) {
			$variante = new \WC_Product_Variation();
			$variante->set_parent_id( $id );
			$variante->set_attributes( array( 'taglia' => (string) $taglia ) );
			$variante->set_regular_price( '10' );
			$variante->set_status( $impostazioni['stato_post'] ?? 'publish' );
			$gestione = $impostazioni['gestione'] ?? false;
			if ( true === $gestione ) {
				$variante->set_manage_stock( true );
				$variante->set_stock_quantity( $impostazioni['quantita'] );
			} elseif ( isset( $impostazioni['stato'] ) ) {
				$variante->set_stock_status( $impostazioni['stato'] );
			}
			$variante->save();
		}
		\WC_Product_Variable::sync( $id );
		return $id;
	}

	/**
	 * Le righe di /scorte relative a un prodotto.
	 *
	 * @param int $id Id del prodotto (padre).
	 */
	private function righe_di( int $id ): array {
		$righe = $this->richiesta( 'GET', '/scorte', array( 'soglia' => 5 ) )->get_data();
		return array_values( array_filter( $righe, static fn( $riga ) => $riga['id'] === $id ) );
	}

	public function test_una_variante_con_poche_scorte_compare_senza_il_padre(): void {
		$id = $this->variabile(
			array(
				'S' => array(
					'gestione' => true,
					'quantita' => 2,
				),
				'L' => array(
					'gestione' => true,
					'quantita' => 50,
				),
			)
		);

		$righe = $this->righe_di( $id );

		$this->assertCount( 1, $righe );
		$this->assertSame( 'Maglia', $righe[0]['titolo'] );
		$this->assertSame( 'Taglia: S', $righe[0]['variante'] );
		$this->assertNotNull( $righe[0]['variante_id'] );
		$this->assertSame( 2, $righe[0]['scorte'] );
		$this->assertFalse( $righe[0]['esaurito'] );
	}

	public function test_una_variante_esaurita_senza_gestione_scorte_compare(): void {
		$id = $this->variabile(
			array(
				'S' => array( 'stato' => 'outofstock' ),
				'L' => array( 'stato' => 'instock' ),
			)
		);

		$righe = $this->righe_di( $id );

		$this->assertCount( 1, $righe );
		$this->assertSame( 'Taglia: S', $righe[0]['variante'] );
		$this->assertNull( $righe[0]['scorte'] );
		$this->assertTrue( $righe[0]['esaurito'] );
	}

	public function test_le_varianti_che_ereditano_le_scorte_dal_padre_non_si_ripetono(): void {
		$id = $this->variabile(
			array(
				'S' => array( 'gestione' => 'parent' ),
				'L' => array( 'gestione' => 'parent' ),
			),
			array( 'quantita' => 3 )
		);

		$righe = $this->righe_di( $id );

		$this->assertCount( 1, $righe );
		$this->assertNull( $righe[0]['variante_id'] );
		$this->assertSame( 3, $righe[0]['scorte'] );
	}

	public function test_varianti_non_pubblicate_e_padre_in_bozza_non_contano(): void {
		$con_variante_privata = $this->variabile(
			array(
				'S' => array(
					'gestione'   => true,
					'quantita'   => 1,
					'stato_post' => 'private',
				),
				'L' => array(
					'gestione' => true,
					'quantita' => 40,
				),
			)
		);
		$in_bozza             = $this->variabile(
			array(
				'S' => array(
					'gestione' => true,
					'quantita' => 1,
				),
			),
			array(),
			'draft'
		);

		$this->assertSame( array(), $this->righe_di( $con_variante_privata ) );
		$this->assertSame( array(), $this->righe_di( $in_bozza ) );
	}

	public function test_un_prodotto_semplice_resta_com_era(): void {
		$prodotto = wc_get_product( $this->prodotto() );
		$prodotto->set_manage_stock( true );
		$prodotto->set_stock_quantity( 4 );
		$prodotto->save();

		$righe = $this->righe_di( $prodotto->get_id() );

		$this->assertCount( 1, $righe );
		$this->assertNull( $righe[0]['variante_id'] );
		$this->assertNull( $righe[0]['variante'] );
		$this->assertSame( 4, $righe[0]['scorte'] );
	}

	public function test_gli_esauriti_vengono_prima_anche_se_sono_varianti(): void {
		$id    = $this->variabile(
			array(
				'S' => array(
					'gestione' => true,
					'quantita' => 1,
				),
				'L' => array( 'stato' => 'outofstock' ),
			)
		);
		$righe = $this->righe_di( $id );

		$this->assertSame( array( 'Taglia: L', 'Taglia: S' ), array_column( $righe, 'variante' ) );
	}
}
