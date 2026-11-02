<?php
/**
 * Test di /link/prodotto su WordPress e WooCommerce veri.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

/**
 * La classificazione dei link e' provata a parte (AnalisiLinkTest); qui il
 * dominio del sito, i permessi e il prodotto inesistente.
 */
class LinkProdottoControllerTest extends RestTestCase {

	/**
	 * Un prodotto con la descrizione data.
	 *
	 * @param string $descrizione Descrizione (HTML).
	 */
	private function prodotto_con_descrizione( string $descrizione ): int {
		$id       = $this->prodotto();
		$prodotto = wc_get_product( $id );
		$prodotto->set_description( $descrizione );
		$prodotto->save();
		return $id;
	}

	public function test_divide_i_link_rispetto_al_dominio_del_sito(): void {
		$home = home_url( '/categoria/telefoni/' );
		$id   = $this->prodotto_con_descrizione( '<p><a href="' . $home . '">telefoni</a> <a href="https://www.gsmarena.com/">scheda</a></p>' );

		$dati = $this->richiesta( 'GET', '/link/prodotto', array( 'id' => $id ) )->get_data();

		$this->assertSame( array( $home ), array_column( $dati['interni'], 'url' ) );
		$this->assertSame( array( 'https://www.gsmarena.com/' ), array_column( $dati['esterni'], 'url' ) );
		$this->assertSame( 1, $dati['totale_interni'] );
		$this->assertSame( 1, $dati['totale_esterni'] );
		$this->assertTrue( $dati['ha_link_in_uscita'] );
	}

	public function test_senza_link_in_uscita_lo_dice(): void {
		$id = $this->prodotto_con_descrizione( '<p>Vedi <a href="/negozio/">il negozio</a>.</p>' );

		$dati = $this->richiesta( 'GET', '/link/prodotto', array( 'id' => $id ) )->get_data();

		$this->assertFalse( $dati['ha_link_in_uscita'] );
		$this->assertSame( 0, $dati['totale_esterni'] );
		$this->assertSame( 1, $dati['totale_interni'] );
	}

	public function test_permessi_e_prodotto_inesistente(): void {
		$id = $this->prodotto_con_descrizione( '<a href="https://esterno.it/">x</a>' );

		$this->assertSame( 404, $this->richiesta( 'GET', '/link/prodotto', array( 'id' => 999999 ) )->get_status() );
		$this->assertSame( 400, $this->richiesta( 'GET', '/link/prodotto' )->get_status() );
		$this->accedi_come( 'subscriber' );
		$this->assertSame( 403, $this->richiesta( 'GET', '/link/prodotto', array( 'id' => $id ) )->get_status() );
		$this->accedi_come( '' );
		$this->assertSame( 401, $this->richiesta( 'GET', '/link/prodotto', array( 'id' => $id ) )->get_status() );
	}

	/**
	 * Righe del riepilogo per id.
	 *
	 * @param array $parametri Parametri della richiesta.
	 */
	private function riepilogo( array $parametri = array() ): array {
		return $this->richiesta( 'GET', '/link/prodotti', $parametri )->get_data();
	}

	public function test_il_riepilogo_conta_i_prodotti_senza_link_in_uscita_e_senza_link_interni(): void {
		$completo      = $this->prodotto_con_descrizione( '<a href="/negozio/">n</a> <a href="https://esterno.it/">e</a>' );
		$solo_interni  = $this->prodotto_con_descrizione( '<a href="/negozio/">n</a>' );
		$senza_link    = $this->prodotto_con_descrizione( '<p>Niente link.</p>' );

		$dati = $this->riepilogo();

		$this->assertSame( 3, $dati['analizzati'] );
		$this->assertSame( 2, $dati['senza_link_in_uscita'] );
		$this->assertSame( 1, $dati['senza_link_interni'] );
		$per_id = array_column( $dati['prodotti'], null, 'id' );
		$this->assertTrue( $per_id[ $completo ]['ha_link_in_uscita'] );
		$this->assertSame( 1, $per_id[ $completo ]['totale_esterni'] );
		$this->assertFalse( $per_id[ $solo_interni ]['ha_link_in_uscita'] );
		$this->assertSame( 0, $per_id[ $senza_link ]['totale_interni'] );
		$this->assertArrayNotHasKey( 'esterni', $per_id[ $completo ] );
	}

	public function test_il_riepilogo_puo_elencare_solo_quelli_senza_link_in_uscita(): void {
		$completo = $this->prodotto_con_descrizione( '<a href="https://esterno.it/">e</a>' );
		$manca    = $this->prodotto_con_descrizione( '<p>Niente.</p>' );

		$dati = $this->riepilogo( array( 'solo_senza_link_in_uscita' => true ) );

		$this->assertSame( array( $manca ), array_column( $dati['prodotti'], 'id' ) );
		$this->assertSame( 2, $dati['analizzati'] );
		$this->assertSame( 1, $dati['senza_link_in_uscita'] );
		$this->assertNotContains( $completo, array_column( $dati['prodotti'], 'id' ) );
	}

	public function test_il_riepilogo_filtra_per_stato(): void {
		$pubblicato = $this->prodotto_con_descrizione( '<p>a</p>' );
		$bozza      = $this->prodotto( array(), 'draft' );

		$this->assertSame( array( $pubblicato ), array_column( $this->riepilogo()['prodotti'], 'id' ) );
		$this->assertSame( array( $bozza ), array_column( $this->riepilogo( array( 'stato' => 'draft' ) )['prodotti'], 'id' ) );
		$this->assertEqualsCanonicalizing( array( $pubblicato, $bozza ), array_column( $this->riepilogo( array( 'stato' => 'any' ) )['prodotti'], 'id' ) );
		$this->assertSame( 400, $this->richiesta( 'GET', '/link/prodotti', array( 'stato' => 'trash' ) )->get_status() );
	}

	public function test_il_riepilogo_rispetta_i_permessi(): void {
		$this->accedi_come( 'subscriber' );
		$this->assertSame( 403, $this->richiesta( 'GET', '/link/prodotti' )->get_status() );
		$this->accedi_come( '' );
		$this->assertSame( 401, $this->richiesta( 'GET', '/link/prodotti' )->get_status() );
	}
}
