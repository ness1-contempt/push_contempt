<?php
/**
 * Test del giudizio su una bozza da pubblicare.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\ValutaBozza;

/**
 * Logica pura: nessun bootstrap WordPress.
 */
class ValutaBozzaTest extends TestCase {

	/**
	 * Una bozza a posto, con i campi cambiati.
	 *
	 * @param array $diversi Campi da cambiare.
	 */
	private function bozza( array $diversi = array() ): array {
		return array_merge(
			array(
				'stato'                      => 'draft',
				'titolo'                     => 'Felpa',
				'prezzo_min'                 => '30.00',
				'immagine_evidenza'          => true,
				'titolo_seo'                 => 'Felpa in cotone',
				'meta_description'           => 'Una felpa.',
				'parola_chiave'              => 'felpa',
				'solo_categoria_predefinita' => false,
				'errori_varianti'            => array(),
			),
			$diversi
		);
	}

	public function test_una_bozza_a_posto_non_ha_ne_bloccanti_ne_avvisi(): void {
		$this->assertSame(
			array(
				'bloccanti' => array(),
				'avvisi'    => array(),
			),
			ValutaBozza::valuta( $this->bozza() )
		);
	}

	public function test_un_prodotto_gia_pubblicato_non_si_ripubblica(): void {
		$esito = ValutaBozza::valuta( $this->bozza( array( 'stato' => 'publish' ) ) );
		$this->assertCount( 1, $esito['bloccanti'] );
		$this->assertStringContainsString( 'publish', $esito['bloccanti'][0] );
	}

	/**
	 * @dataProvider casi_bloccanti
	 *
	 * @param array $diversi Campi che rendono la bozza non pubblicabile.
	 */
	public function test_senza_titolo_prezzo_o_con_varianti_sbagliate_e_bloccata( array $diversi ): void {
		$this->assertNotEmpty( ValutaBozza::valuta( $this->bozza( $diversi ) )['bloccanti'] );
	}

	/**
	 * Casi che bloccano la pubblicazione.
	 */
	public function casi_bloccanti(): array {
		return array(
			'titolo vuoto'     => array( array( 'titolo' => '  ' ) ),
			'prezzo assente'   => array( array( 'prezzo_min' => null ) ),
			'prezzo vuoto'     => array( array( 'prezzo_min' => '' ) ),
			'varianti errate'  => array( array( 'errori_varianti' => array( 'Nessuna variante e\' pubblicata.' ) ) ),
		);
	}

	public function test_i_campi_mancanti_sono_solo_avvisi_e_non_bloccano(): void {
		$esito = ValutaBozza::valuta(
			$this->bozza(
				array(
					'immagine_evidenza'          => false,
					'titolo_seo'                 => '',
					'meta_description'           => ' ',
					'parola_chiave'              => '',
					'solo_categoria_predefinita' => true,
				)
			)
		);
		$this->assertSame( array(), $esito['bloccanti'] );
		$this->assertCount( 5, $esito['avvisi'] );
	}
}
