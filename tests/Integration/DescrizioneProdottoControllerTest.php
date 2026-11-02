<?php
/**
 * Test di /descrizione/prodotto e /descrizione/ripristina su WordPress e WooCommerce veri.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

use PushContempt\DescrizioneSnapshot;

/**
 * La logica delle versioni e' provata a parte (DescrizioneSnapshotTest); qui si
 * prova il collegamento a prodotti, sanificazione HTML, permessi e validazione.
 */
class DescrizioneProdottoControllerTest extends RestTestCase {

	/**
	 * Un prodotto con le descrizioni date.
	 *
	 * @param string $lunga Descrizione.
	 * @param string $breve Descrizione breve.
	 */
	private function prodotto_con( string $lunga, string $breve = '' ): int {
		$id       = $this->prodotto();
		$prodotto = wc_get_product( $id );
		$prodotto->set_description( $lunga );
		$prodotto->set_short_description( $breve );
		$prodotto->save();
		return $id;
	}

	/**
	 * Le due descrizioni vive, rilette dal database.
	 *
	 * @param int $id Id del prodotto.
	 */
	private function vive( int $id ): array {
		$prodotto = wc_get_product( $id );
		return array(
			'descrizione'       => $prodotto->get_description( 'edit' ),
			'descrizione_breve' => $prodotto->get_short_description( 'edit' ),
		);
	}

	/**
	 * Scrive passando dall'endpoint (in JSON, come fa il server MCP).
	 *
	 * @param int    $id     Id del prodotto.
	 * @param array  $extra  Altri parametri (descrizione, descrizione_breve, origine).
	 */
	private function scrivi( int $id, array $extra ) {
		return $this->richiesta( 'POST', '/descrizione/prodotto', array_merge( array( 'id' => $id ), $extra ), true );
	}

	public function test_permessi_e_validazione(): void {
		$id = $this->prodotto_con( '<p>Vecchia</p>' );

		$this->assertSame( 400, $this->richiesta( 'POST', '/descrizione/prodotto', array( 'id' => $id ) )->get_status() );
		$this->assertSame( 400, $this->richiesta( 'POST', '/descrizione/ripristina', array( 'ids' => array( $id ), 'versione' => 'attuale' ) )->get_status() );
		$this->assertSame( 404, $this->scrivi( 999999, array( 'descrizione' => 'x' ) )->get_status() );

		$this->accedi_come( 'subscriber' );
		$this->assertSame( 403, $this->scrivi( $id, array( 'descrizione' => '<p>Nuova</p>' ) )->get_status() );
		$this->assertSame( '<p>Vecchia</p>', $this->vive( $id )['descrizione'] );
		$this->accedi_come( '' );
		$this->assertSame( 401, $this->richiesta( 'GET', '/descrizione/prodotto', array( 'id' => $id ) )->get_status() );
	}

	public function test_una_variazione_non_e_un_prodotto(): void {
		$variabile = new \WC_Product_Variable();
		$variabile->set_name( 'Variabile' );
		$variabile->save();
		$variazione = new \WC_Product_Variation();
		$variazione->set_parent_id( $variabile->get_id() );
		$variazione->save();

		$this->assertSame( 404, $this->richiesta( 'GET', '/descrizione/prodotto', array( 'id' => $variazione->get_id() ) )->get_status() );
	}

	public function test_la_prima_scrittura_crea_la_base_e_lascia_la_breve_se_non_data(): void {
		$id = $this->prodotto_con( '<p>Vecchia</p>', 'Breve vecchia' );

		$risposta = $this->scrivi( $id, array( 'descrizione' => '<p>Nuova</p>', 'origine' => 'prova' ) );

		$this->assertSame( 200, $risposta->get_status() );
		$this->assertSame( array( 'descrizione' => '<p>Nuova</p>', 'descrizione_breve' => 'Breve vecchia' ), $risposta->get_data() );
		$this->assertSame( array( 'descrizione' => '<p>Nuova</p>', 'descrizione_breve' => 'Breve vecchia' ), $this->vive( $id ) );
		$snapshot = get_post_meta( $id, DescrizioneSnapshot::META_KEY, true );
		$this->assertSame( '<p>Vecchia</p>', $snapshot['base']['descrizione'] );
		$this->assertSame( 'prova', $snapshot['ultima_scrittura']['origine'] );
	}

	public function test_la_breve_si_scrive_se_data_anche_vuota(): void {
		$id = $this->prodotto_con( '<p>A</p>', 'Breve' );

		$this->scrivi( $id, array( 'descrizione' => '<p>A</p>', 'descrizione_breve' => '' ) );

		$this->assertSame( '', $this->vive( $id )['descrizione_breve'] );
	}

	public function test_l_html_e_sanificato_ma_i_tag_ammessi_e_i_backslash_restano(): void {
		$id = $this->prodotto_con( '' );

		$this->scrivi( $id, array( 'descrizione' => '<h2>Titolo</h2><p>Testo <a href="https://example.com/a">link</a> C:\\temp</p><script>alert(1)</script>' ) );

		$viva = $this->vive( $id )['descrizione'];
		$this->assertStringNotContainsString( '<script', $viva );
		$this->assertStringContainsString( '<h2>Titolo</h2>', $viva );
		$this->assertStringContainsString( '<a href="https://example.com/a">link</a>', $viva );
		$this->assertStringContainsString( 'C:\\temp', $viva );
	}

	public function test_una_scrittura_identica_non_tocca_le_versioni(): void {
		$id = $this->prodotto_con( '<p>Uguale</p>' );

		$this->scrivi( $id, array( 'descrizione' => '<p>Uguale</p>' ) );

		$this->assertSame( '', get_post_meta( $id, DescrizioneSnapshot::META_KEY, true ) );
	}

	public function test_ripristino_della_base_e_ritorno_al_precedente(): void {
		$id = $this->prodotto_con( '<p>Originale</p>', 'Breve orig.' );
		$this->scrivi( $id, array( 'descrizione' => '<p>Uno</p>', 'descrizione_breve' => 'Breve uno' ) );
		$this->scrivi( $id, array( 'descrizione' => '<p>Due</p>' ) );

		$esiti = $this->richiesta( 'POST', '/descrizione/ripristina', array( 'ids' => array( $id ), 'versione' => 'base' ), true )->get_data();

		$this->assertSame( array( $id => 'ripristinato' ), $esiti );
		$this->assertSame( array( 'descrizione' => '<p>Originale</p>', 'descrizione_breve' => 'Breve orig.' ), $this->vive( $id ) );

		$this->richiesta( 'POST', '/descrizione/ripristina', array( 'ids' => array( $id ), 'versione' => 'precedente' ), true );
		$this->assertSame( array( 'descrizione' => '<p>Due</p>', 'descrizione_breve' => 'Breve uno' ), $this->vive( $id ) );
	}

	public function test_esiti_gia_in_uso_nessuno_snapshot_e_non_trovato(): void {
		$scritto  = $this->prodotto_con( '<p>A</p>' );
		$this->scrivi( $scritto, array( 'descrizione' => '<p>B</p>' ) );
		$this->richiesta( 'POST', '/descrizione/ripristina', array( 'ids' => array( $scritto ), 'versione' => 'base' ), true );
		$mai_toccato = $this->prodotto_con( '<p>C</p>' );

		$esiti = $this->richiesta( 'POST', '/descrizione/ripristina', array( 'ids' => array( $scritto, $mai_toccato, 999999 ), 'versione' => 'base' ), true )->get_data();

		$this->assertSame( 'gia_in_uso', $esiti[ $scritto ] );
		$this->assertSame( 'nessuno_snapshot', $esiti[ $mai_toccato ] );
		$this->assertSame( 'non_trovato', $esiti[999999] );
		$this->assertSame( '<p>C</p>', $this->vive( $mai_toccato )['descrizione'] );
	}

	public function test_la_lettura_da_descrizioni_e_versioni(): void {
		$id = $this->prodotto_con( '<p>A</p>', 'Breve' );
		$this->assertNull( $this->richiesta( 'GET', '/descrizione/prodotto', array( 'id' => $id ) )->get_data()['versioni'] );

		$this->scrivi( $id, array( 'descrizione' => '<p>B</p>' ) );
		$dati = $this->richiesta( 'GET', '/descrizione/prodotto', array( 'id' => $id ) )->get_data();

		$this->assertSame( '<p>B</p>', $dati['descrizione'] );
		$this->assertSame( 'Breve', $dati['descrizione_breve'] );
		$this->assertSame( '<p>A</p>', $dati['versioni']['base']['descrizione'] );
	}

	public function test_lo_stato_riassume_lunghezze_tipo_prezzo_e_versioni(): void {
		$id = $this->prodotto_con( '<h2>Titolo</h2><p>Testo di venti caratteri</p>', '<b>Breve</b>' );
		update_post_meta( $id, '_yoast_wpseo_focuskw', 'sedia' );
		$modificato = $this->prodotto_con( '<p>A</p>' );
		$this->scrivi( $modificato, array( 'descrizione' => '<p>BB</p>' ) );

		$righe = array_column( $this->richiesta( 'GET', '/descrizione/stato' )->get_data(), null, 'id' );

		$this->assertSame( 'sedia', $righe[ $id ]['parola_chiave_focus'] );
		$this->assertSame( mb_strlen( 'TitoloTesto di venti caratteri' ), $righe[ $id ]['lunghezza_descrizione'] );
		$this->assertSame( 5, $righe[ $id ]['lunghezza_descrizione_breve'] );
		$this->assertSame( 'simple', $righe[ $id ]['tipo'] );
		$this->assertSame( '10', $righe[ $id ]['prezzo_min'] );
		$this->assertNull( $righe[ $id ]['versioni'] );
		$this->assertSame( '<p>A</p>', $righe[ $modificato ]['versioni']['base']['descrizione'] );
	}

	public function test_nuova_base_usa_le_descrizioni_attuali_e_salta_i_mai_modificati(): void {
		$id          = $this->prodotto_con( '<p>Originale</p>' );
		$mai_toccato = $this->prodotto_con( '<p>X</p>' );
		$this->scrivi( $id, array( 'descrizione' => '<p>Nuova</p>' ) );

		$esiti = $this->richiesta( 'POST', '/descrizione/base', array( 'ids' => array( $id, $mai_toccato, 999999 ) ), true )->get_data();

		$this->assertSame(
			array(
				$id          => 'impostata',
				$mai_toccato => 'nessuno_snapshot',
				999999       => 'non_trovato',
			),
			$esiti
		);
		$snapshot = get_post_meta( $id, DescrizioneSnapshot::META_KEY, true );
		$this->assertSame( '<p>Nuova</p>', $snapshot['base']['descrizione'] );
		$this->assertSame( 'utente', $snapshot['base']['tipo'] );
		$this->assertSame( 403, $this->nega_a_chi_non_gestisce( 'POST', '/descrizione/base', array( 'ids' => array( $id ) ) ) );
	}

	/**
	 * Lo stato HTTP di una richiesta fatta da un utente senza permessi.
	 *
	 * @param string $metodo Metodo HTTP.
	 * @param string $rotta  Rotta senza prefisso.
	 * @param array  $params Parametri.
	 */
	private function nega_a_chi_non_gestisce( string $metodo, string $rotta, array $params ): int {
		$this->accedi_come( 'subscriber' );
		return $this->richiesta( $metodo, $rotta, $params, true )->get_status();
	}
}
