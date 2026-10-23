<?php
/**
 * Test della logica pura dello snapshot SEO.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\SeoSnapshot;

/**
 * Nessun bootstrap WordPress necessario: SeoSnapshot e' logica pura.
 */
class SeoSnapshotTest extends TestCase {

	/**
	 * Valori SEO di esempio, come base per i singoli test.
	 *
	 * @param array $override Chiavi da sovrascrivere rispetto alla base.
	 */
	private function valori( array $override = array() ): array {
		return array_merge(
			array(
				'titolo_seo'          => 'Titolo Yoast',
				'meta_description'    => 'Meta di Yoast',
				'parola_chiave_focus' => 'smartphone',
			),
			$override
		);
	}

	/**
	 * Senza snapshot e con un campo che cambia, va salvato.
	 */
	public function test_salva_alla_prima_modifica(): void {
		$nuovi = $this->valori( array( 'meta_description' => 'Meta nuova' ) );
		$this->assertTrue( SeoSnapshot::da_salvare( null, $this->valori(), $nuovi ) );
	}

	/**
	 * Se lo snapshot c'e' gia' non si sostituisce: resta l'originale.
	 */
	public function test_non_sostituisce_lo_snapshot_esistente(): void {
		$esistente = SeoSnapshot::crea( $this->valori(), 'manuale', '2026-10-23T10:00:00+00:00' );
		$nuovi     = $this->valori( array( 'titolo_seo' => 'Altro' ) );
		$this->assertFalse( SeoSnapshot::da_salvare( $esistente, $this->valori( array( 'titolo_seo' => 'Primo AI' ) ), $nuovi ) );
	}

	/**
	 * Una scrittura che non cambia niente non crea uno snapshot inutile.
	 */
	public function test_non_salva_se_non_cambia_nulla(): void {
		$this->assertFalse( SeoSnapshot::da_salvare( null, $this->valori(), $this->valori() ) );
	}

	/**
	 * Un prodotto senza SEO (campi vuoti) e' uno stato originale valido da conservare.
	 */
	public function test_salva_anche_da_campi_vuoti(): void {
		$vuoti = array(
			'titolo_seo'          => '',
			'meta_description'    => '',
			'parola_chiave_focus' => '',
		);
		$this->assertTrue( SeoSnapshot::da_salvare( null, $vuoti, $this->valori() ) );
		$snapshot = SeoSnapshot::crea( $vuoti, 'manuale', '2026-10-23T10:00:00+00:00' );
		$this->assertSame( $vuoti, SeoSnapshot::valori_da_ripristinare( $snapshot ) );
	}

	/**
	 * Lo snapshot ripristina solo i tre campi SEO, non origine e data.
	 */
	public function test_valori_da_ripristinare_solo_i_campi_seo(): void {
		$snapshot = SeoSnapshot::crea( $this->valori(), 'concorrente x.it', '2026-10-23T10:00:00+00:00' );
		$this->assertSame( $this->valori(), SeoSnapshot::valori_da_ripristinare( $snapshot ) );
		$this->assertSame( 'concorrente x.it', $snapshot['origine'] );
	}

	/**
	 * Per la risposta REST un meta assente o vuoto diventa null.
	 */
	public function test_per_risposta(): void {
		$this->assertNull( SeoSnapshot::per_risposta( '' ) );
		$this->assertNull( SeoSnapshot::per_risposta( array() ) );
		$snapshot = SeoSnapshot::crea( $this->valori(), 'manuale', '2026-10-23T10:00:00+00:00' );
		$risposta = SeoSnapshot::per_risposta( $snapshot );
		$this->assertSame( 'Titolo Yoast', $risposta['titolo_seo'] );
		$this->assertSame( '2026-10-23T10:00:00+00:00', $risposta['salvato_il'] );
	}
}
