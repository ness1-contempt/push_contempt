<?php
/**
 * Test delle misure sul testo di un articolo.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\TestoBlog;

/**
 * Logica pura: nessun bootstrap WordPress.
 */
class TestoBlogTest extends TestCase {

	public function test_un_contenuto_vuoto_o_solo_tag_ha_zero_parole(): void {
		$this->assertSame( 0, TestoBlog::conta_parole( '' ) );
		$this->assertSame( 0, TestoBlog::conta_parole( '  <p> </p><br> ' ) );
		$this->assertSame( 0, TestoBlog::conta_parole( '<script>var a = 1;</script>' ) );
	}

	public function test_conta_le_parole_del_testo_visibile(): void {
		$html = '<h2>Come scegliere</h2><p>Uno zaino <strong>leggero</strong> costa poco.</p>';

		$this->assertSame( 7, TestoBlog::conta_parole( $html ) );
	}

	public function test_i_blocchi_adiacenti_non_incollano_le_parole(): void {
		$html = '<p>fine</p><p>inizio</p><ul><li>uno</li><li>due</li></ul>';

		$this->assertSame( 'fine inizio uno due', TestoBlog::testo_visibile( $html ) );
	}

	public function test_ignora_script_e_stili_e_decodifica_le_entita(): void {
		$html = '<style>p{color:red}</style><p>Caff&egrave; &amp; t&eacute;</p><script>x()</script>';

		$this->assertSame( 'Caffè & té', TestoBlog::testo_visibile( $html ) );
	}

	public function test_i_commenti_dei_blocchi_di_gutenberg_non_contano(): void {
		$html = "<!-- wp:paragraph -->\n<p>Due parole</p>\n<!-- /wp:paragraph -->";

		$this->assertSame( 2, TestoBlog::conta_parole( $html ) );
	}
}
