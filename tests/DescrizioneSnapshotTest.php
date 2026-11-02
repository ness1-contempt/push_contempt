<?php
/**
 * Test delle versioni della descrizione (stessa logica di SeoSnapshot, altri campi).
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\DescrizioneSnapshot;
use PushContempt\SeoSnapshot;

/**
 * La logica e' provata in SeoSnapshotTest; qui si prova che la sottoclasse
 * lavori sui suoi campi e non su quelli di Yoast.
 */
class DescrizioneSnapshotTest extends TestCase {

	const T1 = '2026-11-02T10:00:00+00:00';
	const T2 = '2026-11-02T11:00:00+00:00';

	/**
	 * Descrizioni di esempio.
	 *
	 * @param string $lunga  Descrizione.
	 * @param string $breve  Descrizione breve.
	 */
	private function d( string $lunga, string $breve = 'Breve' ): array {
		return array(
			'descrizione'       => $lunga,
			'descrizione_breve' => $breve,
		);
	}

	public function test_la_prima_scrittura_crea_la_base_con_i_campi_della_descrizione(): void {
		$s = DescrizioneSnapshot::dopo_scrittura( null, $this->d( '<p>Vecchia</p>' ), $this->d( '<p>Nuova</p>' ), 'api', self::T1 );

		$this->assertSame( '<p>Vecchia</p>', $s['base']['descrizione'] );
		$this->assertSame( 'Breve', $s['base']['descrizione_breve'] );
		$this->assertArrayNotHasKey( 'titolo_seo', $s['base'] );
		$this->assertSame( '<p>Nuova</p>', $s['ultima_scrittura']['descrizione'] );
	}

	public function test_cambiare_solo_la_breve_conta_come_modifica(): void {
		$this->assertNotNull( DescrizioneSnapshot::dopo_scrittura( null, $this->d( 'A', 'x' ), $this->d( 'A', 'y' ), 'api', self::T1 ) );
		$this->assertNull( DescrizioneSnapshot::dopo_scrittura( null, $this->d( 'A', 'x' ), $this->d( 'A', 'x' ), 'api', self::T1 ) );
	}

	public function test_il_ripristino_rimette_entrambe_le_descrizioni_e_la_lasciata_diventa_il_precedente(): void {
		$s  = DescrizioneSnapshot::dopo_scrittura( null, $this->d( 'Originale', 'Breve orig.' ), $this->d( 'Nuova', 'Breve nuova' ), 'api', self::T1 );
		$r  = DescrizioneSnapshot::ripristina( $s, 'base', $this->d( 'Nuova', 'Breve nuova' ), self::T2 );

		$this->assertSame( $this->d( 'Originale', 'Breve orig.' ), $r['valori'] );
		$this->assertSame( 'Nuova', $r['snapshot']['precedente']['descrizione'] );
	}

	public function test_non_condivide_il_meta_con_la_seo(): void {
		$this->assertNotSame( SeoSnapshot::META_KEY, DescrizioneSnapshot::META_KEY );
		$this->assertSame( array( 'titolo_seo', 'meta_description', 'parola_chiave_focus' ), SeoSnapshot::CAMPI );
	}
}
