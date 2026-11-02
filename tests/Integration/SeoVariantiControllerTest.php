<?php
/**
 * Test di /seo/varianti su WordPress e WooCommerce veri.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

use PushContempt\Rest\AltImmaginiController;

/**
 * Lettura (attributi, varianti, problemi), scrittura di descrizione e SKU,
 * rifiuto di varianti e SKU non validi, permessi, e l'ALT delle immagini di variante.
 */
class SeoVariantiControllerTest extends RestTestCase {

	/**
	 * Scrive passando dall'endpoint (in JSON, come fa il server MCP).
	 *
	 * @param int   $id       Id del padre.
	 * @param array $varianti Voci {id, descrizione?, sku?}.
	 * @param bool  $anteprima Se true non salva.
	 */
	private function scrivi( int $id, array $varianti, bool $anteprima = false ) {
		return $this->richiesta( 'POST', '/seo/varianti', array( 'id' => $id, 'varianti' => $varianti, 'solo_anteprima' => $anteprima ), true );
	}

	public function test_la_lettura_da_attributi_varianti_prezzi_e_nessun_problema_di_prezzo(): void {
		$padre = $this->felpa();
		$dati  = $this->richiesta( 'GET', '/seo/varianti', array( 'id' => $padre->get_id() ) )->get_data();

		$this->assertSame( 'variable', $dati['tipo'] );
		$this->assertSame( '30.00', $dati['prezzo_min'] );
		$this->assertSame( '35.00', $dati['prezzo_max'] );
		$this->assertSame( 'Colore', $dati['attributi'][0]['nome'] );
		$this->assertSame( array( 'Nero', 'Grigio' ), $dati['attributi'][0]['valori'] );
		$this->assertCount( 4, $dati['varianti'] );
		$this->assertSame( array( 'Colore' => 'Nero', 'Taglia' => 'M' ), $dati['varianti'][0]['attributi'] );
		$this->assertSame( '30', $dati['varianti'][0]['prezzo'] );
		$this->assertContains( 'varianti_senza_sku', array_column( $dati['problemi'], 'codice' ) );
		$this->assertNotContains( 'variante_senza_prezzo', array_column( $dati['problemi'], 'codice' ) );
	}

	public function test_una_variante_senza_prezzo_e_un_errore(): void {
		$padre = $this->felpa( array( '30', '', '35', '35' ) );
		$dati  = $this->richiesta( 'GET', '/seo/varianti', array( 'id' => $padre->get_id() ) )->get_data();

		$this->assertGreaterThanOrEqual( 1, $dati['conteggio']['errori'] );
		$problema = $dati['problemi'][0];
		$this->assertSame( 'variante_senza_prezzo', $problema['codice'] );
		$this->assertSame( array( $dati['varianti'][1]['id'] ), $problema['varianti'] );
	}

	public function test_la_parola_chiave_e_i_valori_nel_testo_vengono_giudicati(): void {
		$padre = $this->felpa( array( '30', '30', '35', '35' ), 'Felpa in cotone.' );
		update_post_meta( $padre->get_id(), '_yoast_wpseo_title', 'Felpa nera in cotone' );
		update_post_meta( $padre->get_id(), '_yoast_wpseo_focuskw', 'felpa cotone' );
		$dati = $this->richiesta( 'GET', '/seo/varianti', array( 'id' => $padre->get_id() ) )->get_data();

		$this->assertContains( 'valori_non_nel_testo', array_column( $dati['problemi'], 'codice' ) );
		$this->assertSame( 'Felpa nera in cotone', $dati['titolo_seo'] );
	}

	public function test_un_prodotto_semplice_o_inesistente_e_rifiutato(): void {
		$this->assertSame( 400, $this->richiesta( 'GET', '/seo/varianti', array( 'id' => $this->prodotto() ) )->get_status() );
		$this->assertSame( 404, $this->richiesta( 'GET', '/seo/varianti', array( 'id' => 999999 ) )->get_status() );
		$padre = $this->felpa();
		$this->assertSame( 404, $this->richiesta( 'GET', '/seo/varianti', array( 'id' => $padre->get_children()[0] ) )->get_status() );
	}

	public function test_la_scrittura_cambia_descrizione_e_sku_e_da_prima_e_dopo(): void {
		$padre = $this->felpa();
		$v     = $padre->get_children()[0];

		$risposta = $this->scrivi( $padre->get_id(), array( array( 'id' => $v, 'descrizione' => '  <b>Nera</b> <script>x</script>taglia M ', 'sku' => ' FEL-NM ' ) ) );

		$this->assertSame( 200, $risposta->get_status() );
		$esito = $risposta->get_data()['varianti'][0];
		$this->assertSame( array( '', 'FEL-NM' ), array( $esito['sku_prima'], $esito['sku_dopo'] ) );
		$variante = wc_get_product( $v );
		$this->assertSame( 'FEL-NM', $variante->get_sku() );
		$this->assertStringContainsString( 'taglia M', $variante->get_description() );
		$this->assertStringNotContainsString( '<script>', $variante->get_description() );
	}

	public function test_la_sola_anteprima_non_salva(): void {
		$padre = $this->felpa();
		$v     = $padre->get_children()[0];

		$risposta = $this->scrivi( $padre->get_id(), array( array( 'id' => $v, 'sku' => 'FEL-NM' ) ), true );

		$this->assertTrue( $risposta->get_data()['solo_anteprima'] );
		$this->assertSame( 'FEL-NM', $risposta->get_data()['varianti'][0]['sku_dopo'] );
		$this->assertSame( '', wc_get_product( $v )->get_sku() );
	}

	public function test_una_variante_di_un_altro_prodotto_rifiuta_tutto(): void {
		$padre   = $this->felpa();
		$altro   = $this->felpa();
		$mia     = $padre->get_children()[0];
		$altrui  = $altro->get_children()[0];

		$risposta = $this->scrivi(
			$padre->get_id(),
			array(
				array( 'id' => $mia, 'sku' => 'MIA' ),
				array( 'id' => $altrui, 'sku' => 'ALTRUI' ),
			)
		);

		$this->assertSame( 400, $risposta->get_status() );
		$this->assertSame( '', wc_get_product( $mia )->get_sku() );
		$this->assertSame( '', wc_get_product( $altrui )->get_sku() );
	}

	public function test_uno_sku_gia_in_uso_o_ripetuto_rifiuta_tutto(): void {
		$padre = $this->felpa();
		$figli = $padre->get_children();
		$altro = new \WC_Product_Simple();
		$altro->set_name( 'Altro' );
		$altro->set_sku( 'GIA-USATO' );
		$altro->save();

		$in_uso = $this->scrivi( $padre->get_id(), array( array( 'id' => $figli[0], 'sku' => 'NUOVO' ), array( 'id' => $figli[1], 'sku' => 'GIA-USATO' ) ) );
		$this->assertSame( 409, $in_uso->get_status() );
		$this->assertSame( '', wc_get_product( $figli[0] )->get_sku() );

		$ripetuto = $this->scrivi( $padre->get_id(), array( array( 'id' => $figli[0], 'sku' => 'UGUALE' ), array( 'id' => $figli[1], 'sku' => 'uguale' ) ) );
		$this->assertSame( 409, $ripetuto->get_status() );
	}

	public function test_riscrivere_lo_stesso_sku_della_variante_non_e_un_conflitto_e_vuoto_lo_toglie(): void {
		$padre = $this->felpa();
		$v     = $padre->get_children()[0];
		$this->assertSame( 200, $this->scrivi( $padre->get_id(), array( array( 'id' => $v, 'sku' => 'STESSO' ) ) )->get_status() );
		$this->assertSame( 200, $this->scrivi( $padre->get_id(), array( array( 'id' => $v, 'sku' => 'STESSO' ) ) )->get_status() );
		$this->scrivi( $padre->get_id(), array( array( 'id' => $v, 'sku' => '' ) ) );
		$this->assertSame( '', wc_get_product( $v )->get_sku() );
	}

	public function test_permessi_e_validazione(): void {
		$padre = $this->felpa();
		$this->assertSame( 400, $this->richiesta( 'POST', '/seo/varianti', array( 'id' => $padre->get_id() ), true )->get_status() );
		$this->assertSame( 400, $this->scrivi( $padre->get_id(), array( array( 'sku' => 'senza id' ) ) )->get_status() );

		$this->accedi_come( 'subscriber' );
		$this->assertSame( 403, $this->richiesta( 'GET', '/seo/varianti', array( 'id' => $padre->get_id() ) )->get_status() );
		$this->assertSame( 403, $this->scrivi( $padre->get_id(), array( array( 'id' => $padre->get_children()[0], 'sku' => 'x' ) ) )->get_status() );
	}

	public function test_l_immagine_di_una_variante_compare_nell_alt_con_ruolo_variante_e_si_puo_scrivere(): void {
		$padre   = $this->felpa();
		$padre_img = self::factory()->attachment->create( array( 'post_mime_type' => 'image/png' ) );
		$var_img   = self::factory()->attachment->create( array( 'post_mime_type' => 'image/png' ) );
		$padre->set_image_id( $padre_img );
		$padre->save();
		$variante = wc_get_product( $padre->get_children()[0] );
		$variante->set_image_id( $var_img );
		$variante->save();

		$dati = $this->richiesta( 'GET', '/alt/prodotto', array( 'id' => $padre->get_id() ) )->get_data();
		$this->assertSame( array( 'principale', 'variante' ), array_column( $dati['immagini'], 'ruolo' ) );

		$esito = $this->richiesta( 'POST', '/alt/prodotto', array( 'id' => $padre->get_id(), 'immagini' => array( array( 'allegato_id' => $var_img, 'alt' => 'Felpa nera' ) ) ), true );
		$this->assertSame( 200, $esito->get_status() );
		$this->assertSame( 'Felpa nera', get_post_meta( $var_img, AltImmaginiController::META_ALT, true ) );

		$lettura = $this->richiesta( 'GET', '/seo/varianti', array( 'id' => $padre->get_id() ) )->get_data();
		$this->assertTrue( $lettura['varianti'][0]['immagine_propria'] );
		$this->assertSame( 'Felpa nera', $lettura['varianti'][0]['alt_immagine'] );
		$this->assertFalse( $lettura['varianti'][1]['immagine_propria'] );
	}

	public function test_l_interruttore_dei_dati_strutturati_si_legge_e_si_cambia(): void {
		delete_option( \PushContempt\SchemaVarianti::OPZIONE );
		$this->assertTrue( $this->richiesta( 'GET', '/seo/schema-varianti' )->get_data()['attivo'], 'Di default e\' acceso.' );

		$spento = $this->richiesta( 'POST', '/seo/schema-varianti', array( 'attivo' => false ), true );
		$this->assertSame( 200, $spento->get_status() );
		$this->assertFalse( $spento->get_data()['attivo'] );
		$this->assertSame( 'no', get_option( \PushContempt\SchemaVarianti::OPZIONE ) );

		$this->assertTrue( $this->richiesta( 'POST', '/seo/schema-varianti', array( 'attivo' => true ), true )->get_data()['attivo'] );
		$this->assertSame( 400, $this->richiesta( 'POST', '/seo/schema-varianti', array(), true )->get_status() );

		$this->accedi_come( 'subscriber' );
		$this->assertSame( 403, $this->richiesta( 'GET', '/seo/schema-varianti' )->get_status() );
		delete_option( \PushContempt\SchemaVarianti::OPZIONE );
	}
}
