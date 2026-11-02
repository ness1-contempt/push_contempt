<?php
/**
 * Test del giudizio SEO sui prodotti variabili.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\AnalisiVarianti;

/**
 * Logica pura: nessun bootstrap WordPress.
 */
class AnalisiVariantiTest extends TestCase {

	/**
	 * Un padre a posto: due attributi visibili, testo che li cita tutti.
	 *
	 * @param array $diversi Campi da cambiare.
	 */
	private function padre( array $diversi = array() ): array {
		return array_merge(
			array(
				'attributi'     => array(
					'Colore'  => array(
						'valori'  => array( 'Nero', 'Grigio' ),
						'visibile' => true,
					),
					'Taglia'  => array(
						'valori'  => array( 'M', 'L' ),
						'visibile' => true,
					),
				),
				'testo'         => 'Felpa in cotone, disponibile nero e grigio nelle taglie M e L.',
				'titolo_seo'    => 'Felpa in cotone',
				'parola_chiave' => 'felpa cotone',
			),
			$diversi
		);
	}

	/**
	 * Una variante a posto, con i campi cambiati.
	 *
	 * @param int   $id      Id della variante.
	 * @param array $diversi Campi da cambiare.
	 */
	private function variante( int $id, array $diversi = array() ): array {
		return array_merge(
			array(
				'id'               => $id,
				'stato'            => 'publish',
				'prezzo'           => '30',
				'sku'              => 'FEL-' . $id,
				'immagine_propria' => true,
				'attributi'        => array(
					'Colore' => 'Nero',
					'Taglia' => 'M' . $id,
				),
			),
			$diversi
		);
	}

	/**
	 * Codici dei problemi trovati.
	 *
	 * @param array $esito Risultato di analizza().
	 */
	private function codici( array $esito ): array {
		return array_column( $esito['problemi'], 'codice' );
	}

	public function test_un_prodotto_a_posto_non_ha_problemi(): void {
		$esito = AnalisiVarianti::analizza( $this->padre(), array( $this->variante( 1 ), $this->variante( 2 ) ) );
		$this->assertSame( array(), $esito['problemi'] );
		$this->assertSame(
			array(
				'errori'       => 0,
				'avvisi'       => 0,
				'suggerimenti' => 0,
			),
			$esito['conteggio']
		);
	}

	public function test_senza_varianti_pubblicate_e_un_errore(): void {
		$esito = AnalisiVarianti::analizza( $this->padre(), array( $this->variante( 1, array( 'stato' => 'private' ) ) ) );
		$this->assertContains( 'nessuna_variante_in_vendita', $this->codici( $esito ) );
	}

	public function test_una_variante_in_bozza_non_conta_come_in_vendita_ma_non_segnala_il_prezzo(): void {
		$esito = AnalisiVarianti::analizza(
			$this->padre(),
			array( $this->variante( 1 ), $this->variante( 2, array( 'stato' => 'private', 'prezzo' => '' ) ) )
		);
		$this->assertNotContains( 'variante_senza_prezzo', $this->codici( $esito ) );
	}

	public function test_una_variante_pubblicata_senza_prezzo_e_un_errore_con_il_suo_id(): void {
		$esito = AnalisiVarianti::analizza( $this->padre(), array( $this->variante( 1 ), $this->variante( 2, array( 'prezzo' => ' ' ) ) ) );
		$problema = $esito['problemi'][0];
		$this->assertSame( 'variante_senza_prezzo', $problema['codice'] );
		$this->assertSame( 'errore', $problema['gravita'] );
		$this->assertSame( array( 2 ), $problema['varianti'] );
	}

	public function test_una_variante_qualsiasi_e_segnalata(): void {
		$esito = AnalisiVarianti::analizza(
			$this->padre(),
			array( $this->variante( 1 ), $this->variante( 2, array( 'attributi' => array( 'Colore' => 'Nero', 'Taglia' => '' ) ) ) )
		);
		$this->assertContains( 'variante_qualsiasi', $this->codici( $esito ) );
	}

	public function test_due_varianti_con_la_stessa_combinazione_sono_un_errore_anche_con_maiuscole_diverse(): void {
		$stessa = array( 'Colore' => 'Nero', 'Taglia' => 'L' );
		$esito  = AnalisiVarianti::analizza(
			$this->padre(),
			array(
				$this->variante( 1, array( 'attributi' => $stessa ) ),
				$this->variante( 2, array( 'attributi' => array( 'Colore' => 'nero ', 'Taglia' => 'l' ) ) ),
				$this->variante( 3 ),
			)
		);
		$doppione = $esito['problemi'][0];
		$this->assertSame( 'combinazione_duplicata', $doppione['codice'] );
		$this->assertSame( array( 1, 2 ), $doppione['varianti'] );
	}

	public function test_un_attributo_nascosto_e_segnalato(): void {
		$padre                                  = $this->padre();
		$padre['attributi']['Colore']['visibile'] = false;
		$esito = AnalisiVarianti::analizza( $padre, array( $this->variante( 1 ), $this->variante( 2 ) ) );
		$this->assertContains( 'attributo_nascosto', $this->codici( $esito ) );
	}

	public function test_il_titolo_seo_con_un_solo_colore_e_segnalato_ma_non_come_parte_di_una_parola(): void {
		$varianti = array( $this->variante( 1 ), $this->variante( 2 ) );
		$esito    = AnalisiVarianti::analizza( $this->padre( array( 'titolo_seo' => 'Felpa nero in cotone' ) ), $varianti );
		$this->assertContains( 'titolo_seo_con_una_variante', $this->codici( $esito ) );

		// "L" dentro "Felpa" o "M" dentro "Maglia" non e' una taglia citata.
		$esito = AnalisiVarianti::analizza( $this->padre( array( 'titolo_seo' => 'Felpa Maglia' ) ), $varianti );
		$this->assertNotContains( 'titolo_seo_con_una_variante', $this->codici( $esito ) );
	}

	public function test_un_attributo_con_un_solo_valore_non_fa_scattare_il_titolo(): void {
		$padre                                = $this->padre( array( 'titolo_seo' => 'Felpa nero' ) );
		$padre['attributi']['Colore']['valori'] = array( 'Nero' );
		$esito = AnalisiVarianti::analizza( $padre, array( $this->variante( 1 ), $this->variante( 2 ) ) );
		$this->assertNotContains( 'titolo_seo_con_una_variante', $this->codici( $esito ) );
	}

	public function test_la_parola_chiave_con_un_solo_colore_e_solo_un_suggerimento(): void {
		$esito = AnalisiVarianti::analizza( $this->padre( array( 'parola_chiave' => 'felpa grigio' ) ), array( $this->variante( 1 ), $this->variante( 2 ) ) );
		$trovato = array_values( array_filter( $esito['problemi'], static fn( $p ) => 'parola_chiave_con_una_variante' === $p['codice'] ) );
		$this->assertSame( 'suggerimento', $trovato[0]['gravita'] );
	}

	public function test_valori_che_non_compaiono_nel_testo_sono_un_suggerimento(): void {
		$esito = AnalisiVarianti::analizza( $this->padre( array( 'testo' => 'Felpa in cotone.' ) ), array( $this->variante( 1 ), $this->variante( 2 ) ) );
		$trovato = array_values( array_filter( $esito['problemi'], static fn( $p ) => 'valori_non_nel_testo' === $p['codice'] ) );
		$this->assertStringContainsString( 'Colore: Nero', $trovato[0]['messaggio'] );
		$this->assertStringContainsString( 'Taglia: L', $trovato[0]['messaggio'] );
	}

	public function test_immagini_proprie_tutte_assenti_suggerimento_alcune_assenti_avviso(): void {
		$tutte = AnalisiVarianti::analizza(
			$this->padre(),
			array( $this->variante( 1, array( 'immagine_propria' => false ) ), $this->variante( 2, array( 'immagine_propria' => false ) ) )
		);
		$this->assertSame( 'suggerimento', $tutte['problemi'][0]['gravita'] );
		$this->assertSame( array( 1, 2 ), $tutte['problemi'][0]['varianti'] );

		$alcune = AnalisiVarianti::analizza(
			$this->padre(),
			array( $this->variante( 1 ), $this->variante( 2, array( 'immagine_propria' => false ) ) )
		);
		$this->assertSame( 'avviso', $alcune['problemi'][0]['gravita'] );
		$this->assertSame( array( 2 ), $alcune['problemi'][0]['varianti'] );
	}

	public function test_una_sola_variante_non_chiede_immagini_diverse(): void {
		$esito = AnalisiVarianti::analizza( $this->padre(), array( $this->variante( 1, array( 'immagine_propria' => false ) ) ) );
		$this->assertNotContains( 'varianti_senza_immagine_propria', $this->codici( $esito ) );
	}

	public function test_varianti_senza_sku_sono_un_suggerimento(): void {
		$esito = AnalisiVarianti::analizza( $this->padre(), array( $this->variante( 1, array( 'sku' => '' ) ), $this->variante( 2 ) ) );
		$this->assertSame( 'varianti_senza_sku', $esito['problemi'][0]['codice'] );
		$this->assertSame( array( 1 ), $esito['problemi'][0]['varianti'] );
	}

	public function test_i_problemi_sono_ordinati_per_gravita_e_contati(): void {
		$esito = AnalisiVarianti::analizza(
			$this->padre( array( 'testo' => 'Felpa.' ) ),
			array( $this->variante( 1, array( 'sku' => '', 'prezzo' => '' ) ), $this->variante( 2, array( 'sku' => '' ) ) )
		);
		$this->assertSame( array( 'errore', 'suggerimento', 'suggerimento' ), array_column( $esito['problemi'], 'gravita' ) );
		$this->assertSame( 1, $esito['conteggio']['errori'] );
		$this->assertSame( 2, $esito['conteggio']['suggerimenti'] );
	}
}
