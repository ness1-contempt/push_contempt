<?php
/**
 * Test della pulizia del brief del blog.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\BriefBlog;

/**
 * Logica pura: nessun bootstrap WordPress.
 */
class BriefBlogTest extends TestCase {

	public function test_senza_dati_da_il_brief_predefinito(): void {
		$this->assertSame( BriefBlog::predefinito(), BriefBlog::pulisci( array() ) );
		$this->assertSame( BriefBlog::predefinito(), BriefBlog::pulisci( 'non un array' ) );
		$this->assertFalse( BriefBlog::e_compilato( BriefBlog::predefinito() ) );
	}

	public function test_tiene_i_valori_validi_e_scarta_i_campi_sconosciuti(): void {
		$brief = BriefBlog::pulisci(
			array(
				'argomento'          => 'Zaini da trekking',
				'pubblico'           => 'Escursionisti alle prime armi',
				'tono'               => 'Amichevole',
				'lingua'             => 'en-GB',
				'lunghezza_parole'   => 900,
				'n_articoli'         => 5,
				'categorie_prodotti' => array( 12, '15' ),
				'note'               => "Cita sempre il peso.\nNiente superlativi.",
				'altro'              => 'ignorato',
			)
		);

		$this->assertSame( 'Zaini da trekking', $brief['argomento'] );
		$this->assertSame( 'en-GB', $brief['lingua'] );
		$this->assertSame( 900, $brief['lunghezza_parole'] );
		$this->assertSame( 5, $brief['n_articoli'] );
		$this->assertSame( array( 12, 15 ), $brief['categorie_prodotti'] );
		$this->assertSame( "Cita sempre il peso.\nNiente superlativi.", $brief['note'] );
		$this->assertArrayNotHasKey( 'altro', $brief );
		$this->assertTrue( BriefBlog::e_compilato( $brief ) );
	}

	public function test_toglie_html_e_spazi_dai_testi(): void {
		$brief = BriefBlog::pulisci( array( 'argomento' => "  <b>Tende</b>\n  da   campeggio <script>alert(1)</script> " ) );

		$this->assertSame( 'Tende da campeggio alert(1)', $brief['argomento'] );
	}

	public function test_accorcia_i_testi_troppo_lunghi(): void {
		$brief = BriefBlog::pulisci(
			array(
				'argomento' => str_repeat( 'a', 500 ),
				'note'      => str_repeat( 'b', 5000 ),
			)
		);

		$this->assertSame( 300, mb_strlen( $brief['argomento'] ) );
		$this->assertSame( 2000, mb_strlen( $brief['note'] ) );
	}

	public function test_i_numeri_restano_nei_limiti(): void {
		$basso = BriefBlog::pulisci(
			array(
				'lunghezza_parole' => 10,
				'n_articoli'       => 0,
			)
		);
		$alto  = BriefBlog::pulisci(
			array(
				'lunghezza_parole' => 99999,
				'n_articoli'       => 500,
			)
		);
		$testo = BriefBlog::pulisci(
			array(
				'lunghezza_parole' => 'molte',
				'n_articoli'       => null,
			)
		);

		$this->assertSame( 300, $basso['lunghezza_parole'] );
		$this->assertSame( 1, $basso['n_articoli'] );
		$this->assertSame( 3000, $alto['lunghezza_parole'] );
		$this->assertSame( 10, $alto['n_articoli'] );
		$this->assertSame( 1200, $testo['lunghezza_parole'] );
		$this->assertSame( 3, $testo['n_articoli'] );
	}

	public function test_una_lingua_non_valida_resta_italiano(): void {
		$this->assertSame( 'it', BriefBlog::pulisci( array( 'lingua' => 'italiano' ) )['lingua'] );
		$this->assertSame( 'it', BriefBlog::pulisci( array( 'lingua' => '<b>it</b>' ) )['lingua'] );
		$this->assertSame( 'fr', BriefBlog::pulisci( array( 'lingua' => 'fr' ) )['lingua'] );
	}

	public function test_le_categorie_senza_id_validi_e_i_doppioni_spariscono(): void {
		$brief = BriefBlog::pulisci( array( 'categorie_prodotti' => array( 3, '3', 0, -4, 'x', null, 7 ) ) );

		$this->assertSame( array( 3, 7 ), $brief['categorie_prodotti'] );
		$this->assertSame( array(), BriefBlog::pulisci( array( 'categorie_prodotti' => 'tutte' ) )['categorie_prodotti'] );
	}

	public function test_solo_spazi_nell_argomento_non_e_compilato(): void {
		$this->assertFalse( BriefBlog::e_compilato( BriefBlog::pulisci( array( 'argomento' => '   ' ) ) ) );
	}
}
