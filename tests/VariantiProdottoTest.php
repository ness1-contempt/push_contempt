<?php
/**
 * Test degli attributi e delle combinazioni di un prodotto variabile.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\VariantiProdotto;

/**
 * Logica pura: nessun bootstrap WordPress.
 */
class VariantiProdottoTest extends TestCase {

	/**
	 * Un attributo come arriva dalla richiesta.
	 *
	 * @param string $nome   Nome.
	 * @param array  $valori Valori.
	 */
	private function a( string $nome, array $valori ): array {
		return array(
			'nome'   => $nome,
			'valori' => $valori,
		);
	}

	public function test_un_attributo_da_una_variante_per_valore(): void {
		$esito = VariantiProdotto::analizza( array( $this->a( 'Colore', array( 'Nero', 'Bianco' ) ) ) );

		$this->assertSame( array(), $esito['errori'] );
		$this->assertSame(
			array(
				array( 'Colore' => 'Nero' ),
				array( 'Colore' => 'Bianco' ),
			),
			$esito['combinazioni']
		);
	}

	public function test_piu_attributi_danno_tutte_le_combinazioni_in_ordine(): void {
		$esito = VariantiProdotto::analizza(
			array(
				$this->a( 'Colore', array( 'Nero', 'Bianco' ) ),
				$this->a( 'Memoria', array( '256 GB', '512 GB' ) ),
			)
		);

		$this->assertSame(
			array(
				array(
					'Colore'  => 'Nero',
					'Memoria' => '256 GB',
				),
				array(
					'Colore'  => 'Nero',
					'Memoria' => '512 GB',
				),
				array(
					'Colore'  => 'Bianco',
					'Memoria' => '256 GB',
				),
				array(
					'Colore'  => 'Bianco',
					'Memoria' => '512 GB',
				),
			),
			$esito['combinazioni']
		);
		$this->assertSame( 'Memoria', $esito['attributi'][1]['nome'] );
	}

	public function test_pulisce_spazi_e_toglie_i_doppioni_ignorando_le_maiuscole_come_errore(): void {
		$esito = VariantiProdotto::analizza( array( $this->a( '  Colore ', array( '  Nero  scuro ', 'Blu' ) ) ) );
		$this->assertSame( 'Colore', $esito['attributi'][0]['nome'] );
		$this->assertSame( array( 'Nero scuro', 'Blu' ), $esito['attributi'][0]['valori'] );

		$doppio = VariantiProdotto::analizza( array( $this->a( 'Colore', array( 'Nero', 'nero' ) ) ) );
		$this->assertStringContainsString( 'compare piu\' di una volta', $doppio['errori'][0] );
		$this->assertSame( array(), $doppio['combinazioni'] );
	}

	public function test_attributi_assenti_o_malformati_sono_errori(): void {
		foreach ( array( null, array(), 'Colore', array( 'Colore' ) ) as $indice => $caso ) {
			$esito = VariantiProdotto::analizza( $caso );
			$this->assertNotSame( array(), $esito['errori'], 'caso n. ' . $indice );
			$this->assertSame( array(), $esito['combinazioni'] );
		}
	}

	public function test_nome_o_valori_vuoti_sono_errori_e_si_riportano_tutti(): void {
		$esito = VariantiProdotto::analizza(
			array(
				$this->a( '', array( 'x' ) ),
				$this->a( 'Colore', array() ),
				$this->a( 'Taglia', array( 'S', '  ' ) ),
			)
		);

		$this->assertCount( 3, $esito['errori'] );
		$this->assertSame( array(), $esito['attributi'] );
	}

	public function test_lo_stesso_attributo_due_volte_e_un_errore(): void {
		$esito = VariantiProdotto::analizza(
			array(
				$this->a( 'Colore', array( 'Nero' ) ),
				$this->a( 'colore', array( 'Blu' ) ),
			)
		);

		$this->assertStringContainsString( 'compare piu\' di una volta', $esito['errori'][0] );
	}

	public function test_troppe_combinazioni_o_troppi_attributi_sono_errori(): void {
		$molti = array_map( 'strval', range( 1, 8 ) );
		$esito = VariantiProdotto::analizza(
			array(
				$this->a( 'A', $molti ),
				$this->a( 'B', $molti ),
			)
		);
		$this->assertStringContainsString( '64', $esito['errori'][0] );
		$this->assertSame( array(), $esito['combinazioni'] );

		$cinque = array();
		foreach ( array( 'A', 'B', 'C', 'D', 'E' ) as $nome ) {
			$cinque[] = $this->a( $nome, array( '1' ) );
		}
		$this->assertStringContainsString( 'Troppi attributi', VariantiProdotto::analizza( $cinque )['errori'][0] );
	}

	public function test_il_tetto_e_inclusivo(): void {
		$esito = VariantiProdotto::analizza(
			array(
				$this->a( 'A', array_map( 'strval', range( 1, 10 ) ) ),
				$this->a( 'B', array_map( 'strval', range( 1, 5 ) ) ),
			)
		);

		$this->assertSame( array(), $esito['errori'] );
		$this->assertCount( VariantiProdotto::MAX_VARIANTI, $esito['combinazioni'] );
	}

	public function test_nomi_e_valori_troppo_lunghi_sono_errori(): void {
		$lungo = str_repeat( 'x', VariantiProdotto::MAX_LUNGHEZZA + 1 );

		$this->assertNotSame( array(), VariantiProdotto::analizza( array( $this->a( $lungo, array( 'a' ) ) ) )['errori'] );
		$this->assertNotSame( array(), VariantiProdotto::analizza( array( $this->a( 'Colore', array( $lungo ) ) ) )['errori'] );
	}

	public function test_gli_accenti_e_i_caratteri_speciali_restano(): void {
		$esito = VariantiProdotto::analizza( array( $this->a( 'Colorè', array( 'Verde « bosco »', '6,5"' ) ) ) );

		$this->assertSame( array(), $esito['errori'] );
		$this->assertSame( array( 'Verde « bosco »', '6,5"' ), $esito['attributi'][0]['valori'] );
	}
}
