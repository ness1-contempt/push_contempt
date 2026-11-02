<?php
/**
 * Test di /prodotti/pubblica su WordPress e WooCommerce veri.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

/**
 * Anteprima, conferma legata allo stato visto, bozze non pubblicabili e permessi.
 */
class PubblicaProdottiControllerTest extends RestTestCase {

	/**
	 * Chiama l'endpoint (in JSON, come fa il server MCP).
	 *
	 * @param array  $ids       Id dei prodotti.
	 * @param bool   $anteprima Se true non pubblica.
	 * @param string $codice    Codice di conferma.
	 */
	private function pubblica( array $ids, bool $anteprima = true, string $codice = '' ) {
		return $this->richiesta(
			'POST',
			'/prodotti/pubblica',
			array(
				'ids'             => $ids,
				'solo_anteprima'  => $anteprima,
				'codice_conferma' => $codice,
			),
			true
		);
	}

	/**
	 * Anteprima e poi pubblicazione col suo codice.
	 *
	 * @param array $ids Id dei prodotti.
	 */
	private function anteprima_e_pubblica( array $ids ) {
		$anteprima = $this->pubblica( $ids )->get_data();
		return $this->pubblica( $ids, false, (string) $anteprima['codice_conferma'] );
	}

	public function test_l_anteprima_non_pubblica_e_dice_cosa_succederebbe(): void {
		$bozza = $this->prodotto( array(), 'draft' );

		$risposta = $this->pubblica( array( $bozza ) );

		$this->assertSame( 200, $risposta->get_status() );
		$dati = $risposta->get_data();
		$this->assertTrue( $dati['solo_anteprima'] );
		$this->assertSame( $bozza, $dati['da_pubblicare'][0]['id'] );
		$this->assertSame( '10', $dati['da_pubblicare'][0]['prezzo_min'] );
		$this->assertContains( 'Nessuna immagine in evidenza.', $dati['da_pubblicare'][0]['avvisi'] );
		$this->assertNotEmpty( $dati['codice_conferma'] );
		$this->assertSame( 'draft', get_post_status( $bozza ), 'L\'anteprima non cambia lo stato.' );
	}

	public function test_senza_il_codice_non_si_pubblica(): void {
		$bozza = $this->prodotto( array(), 'draft' );

		$this->assertSame( 409, $this->pubblica( array( $bozza ), false )->get_status() );
		$this->assertSame( 409, $this->pubblica( array( $bozza ), false, 'inventato' )->get_status() );
		$this->assertSame( 'draft', get_post_status( $bozza ) );
	}

	public function test_con_il_codice_dell_anteprima_pubblica_e_da_l_indirizzo(): void {
		$bozza = $this->prodotto( array(), 'draft' );

		$risposta = $this->anteprima_e_pubblica( array( $bozza ) );

		$this->assertSame( 200, $risposta->get_status() );
		$dati = $risposta->get_data();
		$this->assertSame( $bozza, $dati['pubblicati'][0]['id'] );
		$this->assertSame( get_permalink( $bozza ), $dati['pubblicati'][0]['url'] );
		$this->assertSame( array(), $dati['falliti'] );
		$this->assertSame( 'publish', get_post_status( $bozza ) );
	}

	public function test_il_codice_vale_solo_per_quei_prodotti(): void {
		$uno  = $this->prodotto( array(), 'draft' );
		$due  = $this->prodotto( array(), 'draft' );
		$code = $this->pubblica( array( $uno ) )->get_data()['codice_conferma'];

		$this->assertSame( 409, $this->pubblica( array( $uno, $due ), false, $code )->get_status(), 'Un prodotto in piu\' di quelli visti.' );
		$this->assertSame( 'draft', get_post_status( $uno ) );
		$this->assertSame( 'draft', get_post_status( $due ) );
	}

	public function test_se_il_prodotto_cambia_dopo_l_anteprima_il_codice_non_vale_piu(): void {
		$bozza = $this->prodotto( array(), 'draft' );
		$code  = $this->pubblica( array( $bozza ) )->get_data()['codice_conferma'];

		$prodotto = wc_get_product( $bozza );
		$prodotto->set_name( 'Titolo cambiato dopo' );
		$prodotto->save();

		$this->assertSame( 409, $this->pubblica( array( $bozza ), false, $code )->get_status() );
		$this->assertSame( 'draft', get_post_status( $bozza ) );
	}

	public function test_anche_un_campo_seo_cambiato_dopo_l_anteprima_invalida_il_codice(): void {
		$bozza = $this->prodotto( array(), 'draft' );
		$code  = $this->pubblica( array( $bozza ) )->get_data()['codice_conferma'];

		// I meta Yoast non toccano la data di modifica del prodotto: conta cio' che l'utente ha visto negli avvisi.
		update_post_meta( $bozza, '_yoast_wpseo_title', 'Ora c\'e\'' );

		$this->assertSame( 409, $this->pubblica( array( $bozza ), false, $code )->get_status() );
	}

	public function test_un_prodotto_gia_pubblicato_senza_prezzo_o_inesistente_non_si_pubblica(): void {
		$pubblicato = $this->prodotto();
		$senza      = $this->prodotto( array(), 'draft' );
		$prodotto   = wc_get_product( $senza );
		$prodotto->set_regular_price( '' );
		$prodotto->save();
		$buona = $this->prodotto( array(), 'draft' );

		$dati = $this->pubblica( array( $pubblicato, $senza, 999999, $buona ) )->get_data();

		$this->assertSame( array( $buona ), array_column( $dati['da_pubblicare'], 'id' ) );
		$this->assertSame( array( $pubblicato, $senza ), array_column( $dati['non_pubblicabili'], 'id' ) );
		$this->assertSame( array( 999999 ), $dati['non_trovati'] );

		$risposta = $this->anteprima_e_pubblica( array( $pubblicato, $senza, 999999, $buona ) );
		$this->assertSame( array( $buona ), array_column( $risposta->get_data()['pubblicati'], 'id' ) );
		$this->assertSame( 'draft', get_post_status( $senza ), 'Quello senza prezzo resta bozza.' );
	}

	public function test_se_nessuno_e_pubblicabile_l_anteprima_non_da_codice_e_l_azione_si_rifiuta(): void {
		$pubblicato = $this->prodotto();

		$this->assertNull( $this->pubblica( array( $pubblicato ) )->get_data()['codice_conferma'] );
		$this->assertSame( 400, $this->pubblica( array( $pubblicato ), false, 'x' )->get_status() );
	}

	public function test_un_variabile_a_posto_si_pubblica_e_uno_con_varianti_sbagliate_no(): void {
		$buono = $this->felpa();
		$buono->set_status( 'draft' );
		$buono->save();
		$rotto = $this->felpa( array( '', '', '', '' ) );
		$rotto->set_status( 'draft' );
		$rotto->save();

		$dati = $this->pubblica( array( $buono->get_id(), $rotto->get_id() ) )->get_data();

		$this->assertSame( array( $buono->get_id() ), array_column( $dati['da_pubblicare'], 'id' ) );
		$this->assertSame( 'variable', $dati['da_pubblicare'][0]['tipo'] );
		$this->assertSame( array( $rotto->get_id() ), array_column( $dati['non_pubblicabili'], 'id' ) );
	}

	public function test_validazione_e_permessi(): void {
		$bozza = $this->prodotto( array(), 'draft' );

		$this->assertSame( 400, $this->pubblica( array() )->get_status() );
		$this->assertSame( 400, $this->richiesta( 'POST', '/prodotti/pubblica', array(), true )->get_status() );
		$this->assertSame( 400, $this->pubblica( range( 1, 51 ) )->get_status() );

		$this->accedi_come( 'subscriber' );
		$this->assertSame( 403, $this->pubblica( array( $bozza ) )->get_status() );
	}
}
