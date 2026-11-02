<?php
/**
 * Test di /salute-catalogo su WordPress e WooCommerce veri.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

/**
 * Il prezzo si controlla dove sta sul prodotto stesso, non nei variabili e nei raggruppati.
 */
class SaluteCatalogoControllerTest extends RestTestCase {

	/**
	 * I problemi segnalati per un prodotto (vuoto se non e' nell'elenco).
	 *
	 * @param int $id Id del prodotto.
	 */
	private function problemi_di( int $id ): array {
		foreach ( $this->richiesta( 'GET', '/salute-catalogo' )->get_data() as $riga ) {
			if ( $riga['id'] === $id ) {
				return $riga['problemi'];
			}
		}
		return array();
	}

	public function test_un_semplice_senza_prezzo_e_segnalato(): void {
		$prodotto = new \WC_Product_Simple();
		$prodotto->set_name( 'Senza prezzo' );
		$prodotto->set_status( 'publish' );
		$id = $prodotto->save();

		$this->assertContains( 'Prezzo mancante, zero o non valido', $this->problemi_di( $id ) );
	}

	public function test_un_raggruppato_non_e_segnalato_per_il_prezzo(): void {
		$figlio = $this->prodotto();
		$gruppo = new \WC_Product_Grouped();
		$gruppo->set_name( 'Gruppo' );
		$gruppo->set_status( 'publish' );
		$gruppo->set_children( array( $figlio ) );
		$id = $gruppo->save();

		$this->assertNotContains( 'Prezzo mancante, zero o non valido', $this->problemi_di( $id ) );
	}

	public function test_un_variabile_non_e_segnalato_per_il_prezzo_e_le_righe_hanno_tipo_e_prezzo(): void {
		$variabile = new \WC_Product_Variable();
		$variabile->set_name( 'Variabile' );
		$variabile->set_status( 'publish' );
		$id = $variabile->save();

		$riga = null;
		foreach ( $this->richiesta( 'GET', '/salute-catalogo' )->get_data() as $voce ) {
			if ( $voce['id'] === $id ) {
				$riga = $voce;
			}
		}

		$this->assertNotNull( $riga ); // Senza immagine e categoria compare comunque.
		$this->assertNotContains( 'Prezzo mancante, zero o non valido', $riga['problemi'] );
		$this->assertSame( 'variable', $riga['tipo'] );
		$this->assertArrayHasKey( 'prezzo_min', $riga );
	}
}
