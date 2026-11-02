<?php
/**
 * Test dell'elenco dei link di un testo HTML (interni, esterni, altri).
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\AnalisiLink;

/**
 * Logica pura: nessun bootstrap WordPress.
 */
class AnalisiLinkTest extends TestCase {

	const SITO = 'medapt.altervista.org';

	/**
	 * Gli indirizzi di una sezione dell'analisi.
	 *
	 * @param array  $analisi Risultato di AnalisiLink::analizza().
	 * @param string $sezione interni, esterni o altri.
	 */
	private function url( array $analisi, string $sezione ): array {
		return array_column( $analisi[ $sezione ], 'url' );
	}

	public function test_un_testo_senza_link_o_vuoto_non_da_nulla(): void {
		$vuoto = array(
			'interni' => array(),
			'esterni' => array(),
			'altri'   => array(),
		);
		$this->assertSame( $vuoto, AnalisiLink::analizza( '', self::SITO ) );
		$this->assertSame( $vuoto, AnalisiLink::analizza( '<p>Solo testo, nessun link.</p>', self::SITO ) );
	}

	public function test_divide_interni_ed_esterni(): void {
		$html = '<p>Vedi <a href="https://medapt.altervista.org/categoria/telefoni/">telefoni</a> e '
			. '<a href="https://www.gsmarena.com/x.php">scheda tecnica</a>.</p>';

		$analisi = AnalisiLink::analizza( $html, self::SITO );

		$this->assertSame( array( 'https://medapt.altervista.org/categoria/telefoni/' ), $this->url( $analisi, 'interni' ) );
		$this->assertSame( array( 'https://www.gsmarena.com/x.php' ), $this->url( $analisi, 'esterni' ) );
		$this->assertSame( 'telefoni', $analisi['interni'][0]['testo'] );
	}

	public function test_i_link_relativi_sono_interni(): void {
		$analisi = AnalisiLink::analizza( '<a href="/negozio/">a</a> <a href="altro/pagina">b</a> <a href="?p=3">c</a>', self::SITO );

		$this->assertSame( array( '/negozio/', 'altro/pagina', '?p=3' ), $this->url( $analisi, 'interni' ) );
		$this->assertSame( array(), $analisi['esterni'] );
	}

	public function test_www_maiuscole_e_porta_non_cambiano_il_dominio(): void {
		$html     = '<a href="http://WWW.Medapt.Altervista.org:8080/a">a</a><a href="//medapt.altervista.org/b">b</a>';
		$analisi  = AnalisiLink::analizza( $html, 'www.medapt.altervista.org' );

		$this->assertCount( 2, $analisi['interni'] );
		$this->assertSame( array(), $analisi['esterni'] );
	}

	public function test_un_dominio_simile_o_un_sottodominio_e_esterno(): void {
		$html = '<a href="https://medapt.altervista.org.finto.com/">a</a>'
			. '<a href="https://blog.medapt.altervista.org/">b</a>'
			. '<a href="//altro.it/c">c</a>';

		$analisi = AnalisiLink::analizza( $html, self::SITO );

		$this->assertCount( 3, $analisi['esterni'] );
		$this->assertSame( array(), $analisi['interni'] );
	}

	public function test_ancore_mailto_e_tel_sono_altri(): void {
		$html = '<a href="#specifiche">s</a><a href="mailto:a@b.it">m</a><a href="tel:+390123">t</a><a href="javascript:void(0)">j</a>';

		$analisi = AnalisiLink::analizza( $html, self::SITO );

		$this->assertSame( array( '#specifiche', 'mailto:a@b.it', 'tel:+390123', 'javascript:void(0)' ), $this->url( $analisi, 'altri' ) );
		$this->assertSame( array(), $analisi['interni'] );
		$this->assertSame( array(), $analisi['esterni'] );
	}

	public function test_un_ancora_senza_href_o_con_href_vuoto_non_e_un_link(): void {
		$analisi = AnalisiLink::analizza( '<a name="x">a</a><a href="">b</a><a href="   ">c</a>', self::SITO );

		$this->assertSame( array(), $analisi['interni'] );
		$this->assertSame( array(), $analisi['esterni'] );
		$this->assertSame( array(), $analisi['altri'] );
	}

	public function test_nofollow_e_testo_del_link(): void {
		$html    = '<a href="https://esterno.it/a" rel="noopener NoFollow">  Guida   <b>completa</b> </a>'
			. '<a href="https://esterno.it/b" rel="noopener">b</a>';
		$analisi = AnalisiLink::analizza( $html, self::SITO );

		$this->assertTrue( $analisi['esterni'][0]['nofollow'] );
		$this->assertSame( 'Guida completa', $analisi['esterni'][0]['testo'] );
		$this->assertFalse( $analisi['esterni'][1]['nofollow'] );
	}

	public function test_gestisce_accenti_entita_e_html_rotto(): void {
		$html = '<p>Città <a href="https://esterno.it/?a=1&amp;b=2">Più info &amp; dettagli</a> <b>non chiuso';

		$analisi = AnalisiLink::analizza( $html, self::SITO );

		$this->assertSame( array( 'https://esterno.it/?a=1&b=2' ), $this->url( $analisi, 'esterni' ) );
		$this->assertSame( 'Più info & dettagli', $analisi['esterni'][0]['testo'] );
	}

	public function test_un_link_ripetuto_compare_tante_volte_quante_e_scritto(): void {
		$analisi = AnalisiLink::analizza( '<a href="/a">1</a> <a href="/a">2</a>', self::SITO );

		$this->assertCount( 2, $analisi['interni'] );
	}
}
