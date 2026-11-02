<?php
/**
 * Test del codice di conferma delle azioni.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\ConfermaAzione;

/**
 * Logica pura: nessun bootstrap WordPress.
 */
class ConfermaAzioneTest extends TestCase {

	public function test_lo_stesso_stato_da_lo_stesso_codice_in_qualunque_ordine(): void {
		$this->assertSame(
			ConfermaAzione::codice( 'pubblica', array( '3@100', '5@200' ), 'segreto' ),
			ConfermaAzione::codice( 'pubblica', array( '5@200', '3@100' ), 'segreto' )
		);
	}

	public function test_cambia_se_cambia_qualcosa(): void {
		$base = ConfermaAzione::codice( 'pubblica', array( '3@100' ), 'segreto' );
		$this->assertNotSame( $base, ConfermaAzione::codice( 'pubblica', array( '3@101' ), 'segreto' ), 'Il prodotto e\' stato modificato.' );
		$this->assertNotSame( $base, ConfermaAzione::codice( 'pubblica', array( '3@100', '4@1' ), 'segreto' ), 'Un prodotto in piu\'.' );
		$this->assertNotSame( $base, ConfermaAzione::codice( 'sposta_categoria', array( '3@100' ), 'segreto' ), 'Un\'altra azione.' );
		$this->assertNotSame( $base, ConfermaAzione::codice( 'pubblica', array( '3@100' ), 'altro' ), 'Un altro sito.' );
	}

	public function test_gli_elementi_non_si_confondono_per_come_sono_uniti(): void {
		$this->assertNotSame(
			ConfermaAzione::codice( 'pubblica', array( '1', '23' ), 's' ),
			ConfermaAzione::codice( 'pubblica', array( '12', '3' ), 's' )
		);
	}

	public function test_valido_accetta_solo_il_codice_giusto(): void {
		$codice = ConfermaAzione::codice( 'pubblica', array( '3@100' ), 's' );
		$this->assertTrue( ConfermaAzione::valido( $codice, 'pubblica', array( '3@100' ), 's' ) );
		$this->assertFalse( ConfermaAzione::valido( $codice, 'pubblica', array( '3@101' ), 's' ) );
		$this->assertFalse( ConfermaAzione::valido( '', 'pubblica', array( '3@100' ), 's' ), 'Il codice vuoto non vale mai.' );
		$this->assertFalse( ConfermaAzione::valido( 'inventato', 'pubblica', array( '3@100' ), 's' ) );
	}
}
