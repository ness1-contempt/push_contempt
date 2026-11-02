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
}
