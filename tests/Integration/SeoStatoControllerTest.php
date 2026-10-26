<?php
/**
 * Test di /seo/stato: la vista d'insieme dei campi SEO e delle versioni.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

use PushContempt\SeoSnapshot;

class SeoStatoControllerTest extends RestTestCase {

	/**
	 * Le righe di /seo/stato, per id del prodotto.
	 */
	private function stato(): array {
		$righe = array();
		foreach ( $this->richiesta( 'GET', '/seo/stato' )->get_data() as $riga ) {
			$righe[ $riga['id'] ] = $riga;
		}
		return $righe;
	}

	public function test_chi_non_gestisce_il_negozio_viene_rifiutato(): void {
		$this->accedi_come( 'subscriber' );
		$this->assertSame( 403, $this->richiesta( 'GET', '/seo/stato' )->get_status() );

		$this->accedi_come( '' );
		$this->assertSame( 401, $this->richiesta( 'GET', '/seo/stato' )->get_status() );
	}

	public function test_elenca_pubblicati_e_bozze_ma_non_i_privati(): void {
		$pubblicato = $this->prodotto();
		$bozza      = $this->prodotto( array(), 'draft' );
		$privato    = $this->prodotto( array(), 'private' );

		$stato = $this->stato();

		$this->assertArrayHasKey( $pubblicato, $stato );
		$this->assertArrayHasKey( $bozza, $stato );
		$this->assertArrayNotHasKey( $privato, $stato );
		$this->assertSame( 'draft', $stato[ $bozza ]['stato'] );
	}

	public function test_campi_mancanti_sono_null_e_le_lunghezze_contano_i_caratteri_presenti(): void {
		$id = $this->prodotto( array( 'titolo_seo' => 'Titolo di 15 car' ) );

		$riga = $this->stato()[ $id ];

		$this->assertSame( 'Titolo di 15 car', $riga['titolo_seo'] );
		$this->assertNull( $riga['meta_description'] );
		$this->assertSame( 16, $riga['lunghezza_titolo_seo'] );
		$this->assertSame( 0, $riga['lunghezza_meta_description'] );
		$this->assertNull( $riga['versioni'], 'senza interventi del plugin non ci sono versioni' );
	}

	public function test_dopo_una_scrittura_le_versioni_dicono_cosa_e_in_uso(): void {
		$id = $this->prodotto( $this->valori( 'Yoast' ) );
		$this->richiesta( 'POST', '/seo/prodotto', array_merge( array( 'id' => $id, 'origine' => 'concorrente x.it' ), $this->valori( 'AI 1' ) ) );

		$versioni = $this->stato()[ $id ]['versioni'];

		$this->assertSame( 'Titolo Yoast', $versioni['base']['titolo_seo'] );
		$this->assertSame( 'Titolo AI 1', $versioni['attuale']['titolo_seo'] );
		$this->assertSame( 'concorrente x.it', $versioni['attuale']['origine'] );
		$this->assertFalse( $versioni['attuale_e_base'] );
		$this->assertTrue( $versioni['precedente_e_base'] );
	}

	public function test_una_modifica_a_mano_in_yoast_si_vede_come_modifica_manuale(): void {
		$id = $this->prodotto( $this->valori( 'Yoast' ) );
		$this->richiesta( 'POST', '/seo/prodotto', array_merge( array( 'id' => $id ), $this->valori( 'AI 1' ) ) );
		update_post_meta( $id, '_yoast_wpseo_metadesc', 'Cambiata a mano' );

		$versioni = $this->stato()[ $id ]['versioni'];

		$this->assertSame( SeoSnapshot::ORIGINE_MANUALE, $versioni['attuale']['origine'] );
		$this->assertSame( 'Cambiata a mano', $versioni['attuale']['meta_description'] );
	}
}
