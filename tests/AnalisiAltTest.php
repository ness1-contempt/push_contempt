<?php
/**
 * Test del giudizio sull'ALT delle immagini.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\AnalisiAlt;

/**
 * Logica pura: nessun bootstrap WordPress.
 */
class AnalisiAltTest extends TestCase {

	public function test_un_alt_vuoto_o_di_soli_spazi_e_mancante(): void {
		$this->assertSame( 'mancante', AnalisiAlt::problema( '', 'cuffie bluetooth' ) );
		$this->assertSame( 'mancante', AnalisiAlt::problema( "  \n ", '' ) );
	}

	public function test_un_alt_senza_la_parola_chiave_e_segnalato(): void {
		$this->assertSame( 'senza_parola_chiave', AnalisiAlt::problema( 'immagine di un mouse', 'mouse wireless' ) );
	}

	public function test_il_confronto_ignora_maiuscole_accenti_di_maiuscola_e_spazi(): void {
		$this->assertNull( AnalisiAlt::problema( 'Le  CUFFIE   Bluetooth  nere', 'Cuffie Bluetooth' ) );
		$this->assertNull( AnalisiAlt::problema( 'Città di Perù', 'PERÙ' ) );
	}

	public function test_senza_parola_chiave_basta_che_l_alt_ci_sia(): void {
		$this->assertNull( AnalisiAlt::problema( 'qualunque descrizione', '' ) );
		$this->assertNull( AnalisiAlt::problema( 'qualunque descrizione', '   ' ) );
	}

	public function test_conta_i_problemi_per_tipo(): void {
		$this->assertSame(
			array(
				'mancanti'            => 2,
				'senza_parola_chiave' => 1,
			),
			AnalisiAlt::conta( array( 'mancante', null, 'senza_parola_chiave', 'mancante' ) )
		);
		$this->assertSame(
			array(
				'mancanti'            => 0,
				'senza_parola_chiave' => 0,
			),
			AnalisiAlt::conta( array() )
		);
	}
}
