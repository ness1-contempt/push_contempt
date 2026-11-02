<?php
/**
 * Test di /alt/prodotto su WordPress e WooCommerce veri.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

use PushContempt\Rest\AltImmaginiController;

/**
 * Immagine in evidenza e galleria, ALT prima/dopo, rifiuto delle immagini
 * che non sono del prodotto, permessi.
 */
class AltImmaginiControllerTest extends RestTestCase {

	/**
	 * Un allegato (senza file vero) con l'ALT dato.
	 *
	 * @param string $alt ALT iniziale.
	 */
	private function allegato( string $alt = '' ): int {
		$id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/png' ) );
		if ( '' !== $alt ) {
			update_post_meta( $id, AltImmaginiController::META_ALT, $alt );
		}
		return $id;
	}

	/**
	 * Un prodotto con immagine in evidenza e galleria.
	 *
	 * @param int   $principale Allegato in evidenza.
	 * @param array $galleria   Allegati della galleria.
	 */
	private function prodotto_con_immagini( int $principale, array $galleria ): int {
		$id       = $this->prodotto();
		$prodotto = wc_get_product( $id );
		$prodotto->set_image_id( $principale );
		$prodotto->set_gallery_image_ids( $galleria );
		$prodotto->save();
		return $id;
	}

	/**
	 * Scrive passando dall'endpoint (in JSON, come fa il server MCP).
	 *
	 * @param int   $id       Id del prodotto.
	 * @param array $immagini Elementi {allegato_id, alt}.
	 */
	private function scrivi( int $id, array $immagini ) {
		return $this->richiesta( 'POST', '/alt/prodotto', array( 'id' => $id, 'immagini' => $immagini ), true );
	}

	public function test_la_lettura_elenca_principale_e_galleria_con_alt_e_parola_chiave(): void {
		$a  = $this->allegato( 'ALT uno' );
		$b  = $this->allegato();
		$id = $this->prodotto_con_immagini( $a, array( $b, $a ) );
		update_post_meta( $id, '_yoast_wpseo_focuskw', 'sedia rossa' );

		$dati = $this->richiesta( 'GET', '/alt/prodotto', array( 'id' => $id ) )->get_data();

		$this->assertSame( 'sedia rossa', $dati['parola_chiave_focus'] );
		$this->assertCount( 2, $dati['immagini'] );
		$this->assertSame( array( $a, 'principale', 'ALT uno' ), array( $dati['immagini'][0]['allegato_id'], $dati['immagini'][0]['ruolo'], $dati['immagini'][0]['alt'] ) );
		$this->assertSame( array( $b, 'galleria', '' ), array( $dati['immagini'][1]['allegato_id'], $dati['immagini'][1]['ruolo'], $dati['immagini'][1]['alt'] ) );
	}

	public function test_la_scrittura_da_prima_e_dopo_e_cambia_solo_le_immagini_indicate(): void {
		$a  = $this->allegato( 'Vecchio' );
		$b  = $this->allegato( 'Resta' );
		$id = $this->prodotto_con_immagini( $a, array( $b ) );

		$risposta = $this->scrivi( $id, array( array( 'allegato_id' => $a, 'alt' => '  Sedia rossa <b>frontale</b> ' ) ) );

		$this->assertSame( 200, $risposta->get_status() );
		$esito = $risposta->get_data()['immagini'][0];
		$this->assertSame( array( 'principale', 'Vecchio', 'Sedia rossa frontale' ), array( $esito['ruolo'], $esito['prima'], $esito['dopo'] ) );
		$this->assertSame( 'Sedia rossa frontale', get_post_meta( $a, AltImmaginiController::META_ALT, true ) );
		$this->assertSame( 'Resta', get_post_meta( $b, AltImmaginiController::META_ALT, true ) );
	}

	public function test_un_alt_vuoto_toglie_il_meta(): void {
		$a  = $this->allegato( 'Da togliere' );
		$id = $this->prodotto_con_immagini( $a, array() );

		$this->scrivi( $id, array( array( 'allegato_id' => $a, 'alt' => '' ) ) );

		$this->assertSame( '', get_post_meta( $a, AltImmaginiController::META_ALT, true ) );
	}

	public function test_un_allegato_non_del_prodotto_rifiuta_tutta_la_richiesta(): void {
		$mio      = $this->allegato( 'Mio' );
		$estraneo = $this->allegato( 'Altrui' );
		$id       = $this->prodotto_con_immagini( $mio, array() );

		$risposta = $this->scrivi(
			$id,
			array(
				array( 'allegato_id' => $mio, 'alt' => 'Nuovo' ),
				array( 'allegato_id' => $estraneo, 'alt' => 'Nuovo' ),
			)
		);

		$this->assertSame( 400, $risposta->get_status() );
		$this->assertSame( 'Mio', get_post_meta( $mio, AltImmaginiController::META_ALT, true ) );
		$this->assertSame( 'Altrui', get_post_meta( $estraneo, AltImmaginiController::META_ALT, true ) );
	}

	public function test_permessi_validazione_e_prodotto_inesistente(): void {
		$a  = $this->allegato( 'Mio' );
		$id = $this->prodotto_con_immagini( $a, array() );

		$this->assertSame( 400, $this->richiesta( 'POST', '/alt/prodotto', array( 'id' => $id ), true )->get_status() );
		$this->assertSame( 400, $this->scrivi( $id, array( array( 'alt' => 'senza id' ) ) )->get_status() );
		$this->assertSame( 404, $this->richiesta( 'GET', '/alt/prodotto', array( 'id' => 999999 ) )->get_status() );

		$this->accedi_come( 'subscriber' );
		$this->assertSame( 403, $this->scrivi( $id, array( array( 'allegato_id' => $a, 'alt' => 'x' ) ) )->get_status() );
		$this->accedi_come( '' );
		$this->assertSame( 401, $this->richiesta( 'GET', '/alt/prodotto', array( 'id' => $id ) )->get_status() );
		$this->assertSame( 'Mio', get_post_meta( $a, AltImmaginiController::META_ALT, true ) );
	}
}
