<?php
/**
 * Test della logica pura delle versioni SEO (base, precedente, attuale).
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

	const T1 = '2026-10-23T10:00:00+00:00';
	const T2 = '2026-10-23T11:00:00+00:00';
	const T3 = '2026-10-23T12:00:00+00:00';

	/**
	 * Valori SEO di esempio.
	 *
	 * @param string $titolo Titolo SEO.
	 * @param string $meta   Meta description.
	 */
	private function v( string $titolo, string $meta = 'Meta', string $kw = 'kw' ): array {
		return array(
			'titolo_seo'          => $titolo,
			'meta_description'    => $meta,
			'parola_chiave_focus' => $kw,
		);
	}

	/**
	 * Alla prima scrittura nasce la base (originale), uguale al precedente.
	 */
	public function test_prima_scrittura_crea_la_base(): void {
		$s = SeoSnapshot::dopo_scrittura( null, $this->v( 'Yoast' ), $this->v( 'AI 1' ), 'concorrente x.it', self::T1 );
		$this->assertSame( 'Yoast', $s['base']['titolo_seo'] );
		$this->assertSame( 'originale', $s['base']['tipo'] );
		$this->assertSame( 'Yoast', $s['precedente']['titolo_seo'] );
		$this->assertSame( 'AI 1', $s['ultima_scrittura']['titolo_seo'] );
		$this->assertSame( 'concorrente x.it', $s['ultima_scrittura']['origine'] );
	}

	/**
	 * Una scrittura che non cambia niente non tocca le versioni.
	 */
	public function test_scrittura_senza_cambiamenti_e_ignorata(): void {
		$this->assertNull( SeoSnapshot::dopo_scrittura( null, $this->v( 'Yoast' ), $this->v( 'Yoast' ), 'x', self::T1 ) );
	}

	/**
	 * Un prodotto senza SEO (campi vuoti) e' uno stato di partenza valido.
	 */
	public function test_base_puo_essere_vuota(): void {
		$vuoti = $this->v( '', '', '' );
		$s     = SeoSnapshot::dopo_scrittura( null, $vuoti, $this->v( 'AI' ), 'x', self::T1 );
		$this->assertSame( '', $s['base']['titolo_seo'] );
		$this->assertSame( '', $s['base']['parola_chiave_focus'] );
	}

	/**
	 * Alla scrittura successiva la base resta e il precedente diventa la versione appena sostituita.
	 */
	public function test_seconda_scrittura_sposta_il_precedente_e_non_la_base(): void {
		$s = SeoSnapshot::dopo_scrittura( null, $this->v( 'Yoast' ), $this->v( 'AI 1' ), 'concorrente x.it', self::T1 );
		$s = SeoSnapshot::dopo_scrittura( $s, $this->v( 'AI 1' ), $this->v( 'AI 2' ), 'concorrente y.it', self::T2 );
		$this->assertSame( 'Yoast', $s['base']['titolo_seo'] );
		$this->assertSame( 'AI 1', $s['precedente']['titolo_seo'] );
		$this->assertSame( 'concorrente x.it', $s['precedente']['origine'] );
		$this->assertSame( self::T1, $s['precedente']['salvato_il'] );
		$this->assertSame( 'AI 2', $s['ultima_scrittura']['titolo_seo'] );
	}

	/**
	 * Un ritocco a mano fra due scritture entra nel precedente, con l'origine "modifica manuale".
	 */
	public function test_modifica_manuale_finisce_nel_precedente(): void {
		$s = SeoSnapshot::dopo_scrittura( null, $this->v( 'Yoast' ), $this->v( 'AI 1' ), 'concorrente x.it', self::T1 );
		$this->assertSame( 'concorrente x.it', SeoSnapshot::origine_attuale( $s, $this->v( 'AI 1' ) ) );
		$this->assertSame( SeoSnapshot::ORIGINE_MANUALE, SeoSnapshot::origine_attuale( $s, $this->v( 'AI 1 ritoccato' ) ) );

		$s = SeoSnapshot::dopo_scrittura( $s, $this->v( 'AI 1 ritoccato' ), $this->v( 'AI 2' ), 'concorrente y.it', self::T2 );
		$this->assertSame( 'AI 1 ritoccato', $s['precedente']['titolo_seo'] );
		$this->assertSame( SeoSnapshot::ORIGINE_MANUALE, $s['precedente']['origine'] );
	}

	/**
	 * Tornare alla base scrive i valori della base e manda in precedente la versione lasciata.
	 */
	public function test_ripristina_la_base_scambia_il_precedente(): void {
		$s = SeoSnapshot::dopo_scrittura( null, $this->v( 'Yoast' ), $this->v( 'AI 1' ), 'x', self::T1 );
		$s = SeoSnapshot::dopo_scrittura( $s, $this->v( 'AI 1' ), $this->v( 'AI 2' ), 'y', self::T2 );

		$r = SeoSnapshot::ripristina( $s, 'base', $this->v( 'AI 2' ), self::T3 );
		$this->assertSame( $this->v( 'Yoast' ), $r['valori'] );
		$this->assertSame( 'AI 2', $r['snapshot']['precedente']['titolo_seo'] );
		$this->assertSame( 'Yoast', $r['snapshot']['base']['titolo_seo'] );
		$this->assertSame( 'ripristino della base', $r['snapshot']['ultima_scrittura']['origine'] );
	}

	/**
	 * Ripristino e ritorno: dopo "torna al precedente" si puo' rifarlo e si e' di nuovo dove si era.
	 */
	public function test_tornare_al_precedente_due_volte_riporta_al_punto_di_partenza(): void {
		$s = SeoSnapshot::dopo_scrittura( null, $this->v( 'Yoast' ), $this->v( 'AI 1' ), 'x', self::T1 );
		$s = SeoSnapshot::dopo_scrittura( $s, $this->v( 'AI 1' ), $this->v( 'AI 2' ), 'y', self::T2 );

		$primo = SeoSnapshot::ripristina( $s, 'precedente', $this->v( 'AI 2' ), self::T3 );
		$this->assertSame( 'AI 1', $primo['valori']['titolo_seo'] );
		$secondo = SeoSnapshot::ripristina( $primo['snapshot'], 'precedente', $this->v( 'AI 1' ), self::T3 );
		$this->assertSame( 'AI 2', $secondo['valori']['titolo_seo'] );
	}

	/**
	 * Se la versione scelta coincide gia' con i valori vivi non c'e' niente da fare.
	 */
	public function test_ripristino_inutile_o_non_valido_restituisce_null(): void {
		$s = SeoSnapshot::dopo_scrittura( null, $this->v( 'Yoast' ), $this->v( 'AI 1' ), 'x', self::T1 );
		$this->assertNull( SeoSnapshot::ripristina( $s, 'base', $this->v( 'Yoast' ), self::T2 ) );
		$this->assertNull( SeoSnapshot::ripristina( $s, 'ultima_scrittura', $this->v( 'AI 1' ), self::T2 ) );
		$this->assertNull( SeoSnapshot::ripristina( $s, 'inesistente', $this->v( 'AI 1' ), self::T2 ) );
	}

	/**
	 * "Usa l'attuale come base": la base diventa lo stato vivo, non e' piu' l'originale e il precedente resta.
	 */
	public function test_imposta_base_usa_i_valori_vivi_e_lo_dichiara(): void {
		$s = SeoSnapshot::dopo_scrittura( null, $this->v( 'Yoast' ), $this->v( 'AI 1' ), 'x', self::T1 );
		$s = SeoSnapshot::dopo_scrittura( $s, $this->v( 'AI 1' ), $this->v( 'AI 2' ), 'y', self::T2 );

		$nuova = SeoSnapshot::imposta_base( $s, $this->v( 'AI 2' ), self::T3 );
		$this->assertSame( 'AI 2', $nuova['base']['titolo_seo'] );
		$this->assertSame( 'utente', $nuova['base']['tipo'] );
		$this->assertSame( SeoSnapshot::ORIGINE_UTENTE, $nuova['base']['origine'] );
		$this->assertSame( self::T3, $nuova['base']['salvato_il'] );
		$this->assertSame( 'AI 1', $nuova['precedente']['titolo_seo'] );

		// Le scritture successive non toccano la nuova base.
		$dopo = SeoSnapshot::dopo_scrittura( $nuova, $this->v( 'AI 2' ), $this->v( 'AI 3' ), 'z', self::T3 );
		$this->assertSame( 'AI 2', $dopo['base']['titolo_seo'] );
		$this->assertSame( 'utente', $dopo['base']['tipo'] );
	}

	/**
	 * Per la risposta REST: nessuna versione se il prodotto non e' mai stato toccato, altrimenti tre versioni e i confronti.
	 */
	public function test_per_risposta(): void {
		$this->assertNull( SeoSnapshot::per_risposta( '', $this->v( 'Yoast' ) ) );
		$this->assertNull( SeoSnapshot::per_risposta( array(), $this->v( 'Yoast' ) ) );

		$s = SeoSnapshot::dopo_scrittura( null, $this->v( 'Yoast' ), $this->v( 'AI 1' ), 'concorrente x.it', self::T1 );
		$r = SeoSnapshot::per_risposta( $s, $this->v( 'AI 1' ) );
		$this->assertSame( 'Yoast', $r['base']['titolo_seo'] );
		$this->assertSame( 'originale', $r['base']['tipo'] );
		$this->assertSame( 'AI 1', $r['attuale']['titolo_seo'] );
		$this->assertSame( 'concorrente x.it', $r['attuale']['origine'] );
		$this->assertFalse( $r['attuale_e_base'] );
		$this->assertTrue( $r['precedente_e_base'] );

		$r = SeoSnapshot::per_risposta( $s, $this->v( 'Yoast' ) );
		$this->assertTrue( $r['attuale_e_base'] );
		$this->assertSame( SeoSnapshot::ORIGINE_MANUALE, $r['attuale']['origine'] );
	}
}
