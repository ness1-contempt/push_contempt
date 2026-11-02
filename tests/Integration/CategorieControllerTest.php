<?php
/**
 * Test di /categorie e /categorie/sposta su WordPress e WooCommerce veri.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

/**
 * Albero, anteprima, conferma legata allo stato visto, rifiuti e permessi.
 */
class CategorieControllerTest extends RestTestCase {

	/**
	 * Crea una categoria prodotto.
	 *
	 * @param string $nome  Nome.
	 * @param int    $madre Categoria madre (0 = radice).
	 */
	private function categoria( string $nome, int $madre = 0 ): int {
		$termine = wp_insert_term( $nome, 'product_cat', array( 'parent' => $madre ) );
		$this->assertNotWPError( $termine );
		return (int) $termine['term_id'];
	}

	/**
	 * Chiama /categorie/sposta (in JSON, come fa il server MCP).
	 *
	 * @param int    $id          Categoria da spostare.
	 * @param int    $nuova_madre Nuova madre.
	 * @param bool   $anteprima   Se true non sposta.
	 * @param string $codice      Codice di conferma.
	 */
	private function sposta( int $id, int $nuova_madre, bool $anteprima = true, string $codice = '' ) {
		return $this->richiesta(
			'POST',
			'/categorie/sposta',
			array(
				'id'              => $id,
				'nuova_madre'     => $nuova_madre,
				'solo_anteprima'  => $anteprima,
				'codice_conferma' => $codice,
			),
			true
		);
	}

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		// Nel database di prova la categoria predefinita di WooCommerce non esiste: la creo.
		update_option( 'default_product_cat', $this->categoria( 'Senza categoria' ) );
	}

	public function test_l_elenco_ha_percorso_livello_e_categoria_predefinita(): void {
		$indumenti = $this->categoria( 'Indumenti' );
		$magliette = $this->categoria( 'Magliette', $indumenti );

		$righe = array_column( $this->richiesta( 'GET', '/categorie' )->get_data(), null, 'id' );

		$this->assertSame( 'Indumenti > Magliette', $righe[ $magliette ]['percorso'] );
		$this->assertSame( 2, $righe[ $magliette ]['livello'] );
		$this->assertSame( $indumenti, $righe[ $magliette ]['madre'] );
		$this->assertTrue( $righe[ (int) get_option( 'default_product_cat' ) ]['predefinita'] );
		$this->assertFalse( $righe[ $indumenti ]['predefinita'] );
	}

	public function test_l_anteprima_non_sposta_e_prevede_il_nuovo_indirizzo_gerarchico(): void {
		$indumenti = $this->categoria( 'Indumenti' );
		$magliette = $this->categoria( 'Magliette' );

		$dati = $this->sposta( $magliette, $indumenti )->get_data();

		$this->assertTrue( $dati['solo_anteprima'] );
		$this->assertSame( 'Magliette', $dati['percorso_prima'] );
		$this->assertSame( 'Indumenti > Magliette', $dati['percorso_dopo'] );
		// Con gli indirizzi "puliti" la categoria figlia ha quelli della madre davanti (verificato sul sito di sviluppo).
		$this->assertStringEndsWith( '/' . wc_get_permalink_structure()['category_rewrite_slug'] . '/indumenti/magliette/', $dati['url_dopo'] );
		$this->assertNotEmpty( $dati['avvisi'] );
		$this->assertNotEmpty( $dati['codice_conferma'] );
		$this->assertSame( 0, get_term( $magliette, 'product_cat' )->parent, 'L\'anteprima non sposta.' );
	}

	public function test_con_il_codice_giusto_sposta_e_senza_no(): void {
		$indumenti = $this->categoria( 'Indumenti' );
		$magliette = $this->categoria( 'Magliette' );
		$codice    = $this->sposta( $magliette, $indumenti )->get_data()['codice_conferma'];

		$this->assertSame( 409, $this->sposta( $magliette, $indumenti, false )->get_status() );
		$this->assertSame( 409, $this->sposta( $magliette, $indumenti, false, 'inventato' )->get_status() );
		$this->assertSame( 0, get_term( $magliette, 'product_cat' )->parent );

		$risposta = $this->sposta( $magliette, $indumenti, false, $codice );
		$this->assertSame( 200, $risposta->get_status() );
		$this->assertTrue( $risposta->get_data()['spostata'] );
		$this->assertSame( $indumenti, get_term( $magliette, 'product_cat' )->parent );
	}

	public function test_il_codice_di_uno_spostamento_non_vale_per_un_altro(): void {
		$a = $this->categoria( 'A' );
		$b = $this->categoria( 'B' );
		$c = $this->categoria( 'C' );
		$codice = $this->sposta( $b, $a )->get_data()['codice_conferma'];

		$this->assertSame( 409, $this->sposta( $b, $c, false, $codice )->get_status(), 'Un\'altra destinazione.' );
		$this->assertSame( 409, $this->sposta( $c, $a, false, $codice )->get_status(), 'Un\'altra categoria.' );
	}

	public function test_se_l_albero_cambia_dopo_l_anteprima_il_codice_non_vale_piu(): void {
		$a = $this->categoria( 'A' );
		$b = $this->categoria( 'B' );
		$codice = $this->sposta( $b, $a )->get_data()['codice_conferma'];

		wp_insert_term( 'Figlia nuova', 'product_cat', array( 'parent' => $b ) ); // Ora B ha una sottocategoria che l'utente non ha visto.

		$this->assertSame( 409, $this->sposta( $b, $a, false, $codice )->get_status() );
		$this->assertSame( 0, get_term( $b, 'product_cat' )->parent );
	}

	public function test_un_ramo_si_sposta_con_le_sottocategorie_e_l_anteprima_le_elenca(): void {
		$a      = $this->categoria( 'A' );
		$b      = $this->categoria( 'B' );
		$figlia = $this->categoria( 'Figlia', $b );

		$dati = $this->sposta( $b, $a )->get_data();
		$this->assertSame( array( 'B > Figlia' ), $dati['sottocategorie'] );
		$this->assertStringContainsString( 'sottocategorie', implode( ' ', $dati['avvisi'] ) );

		$this->sposta( $b, $a, false, $dati['codice_conferma'] );
		$this->assertSame( $b, get_term( $figlia, 'product_cat' )->parent, 'La figlia resta sotto B.' );
		$righe = array_column( $this->richiesta( 'GET', '/categorie' )->get_data(), null, 'id' );
		$this->assertSame( 'A > B > Figlia', $righe[ $figlia ]['percorso'] );
	}

	public function test_l_anteprima_conta_i_prodotti_nel_ramo(): void {
		$a      = $this->categoria( 'A' );
		$b      = $this->categoria( 'B' );
		$figlia = $this->categoria( 'Figlia', $b );
		$uno    = $this->prodotto();
		$due    = $this->prodotto( array(), 'draft' );
		wp_set_object_terms( $uno, array( $b ), 'product_cat' );
		wp_set_object_terms( $due, array( $figlia ), 'product_cat' );

		$dati = $this->sposta( $b, $a )->get_data();

		$this->assertSame( 2, $dati['prodotti_nel_ramo'] );
		$this->assertSame( 1, $dati['prodotti_pubblicati'] );
	}

	public function test_se_gli_indirizzi_dei_prodotti_includono_la_categoria_l_anteprima_lo_dice(): void {
		update_option( 'woocommerce_permalinks', array( 'product_base' => '/%product_cat%/prodotto' ) );
		$a    = $this->categoria( 'A' );
		$b    = $this->categoria( 'B' );
		$uno  = $this->prodotto();
		wp_set_object_terms( $uno, array( $b ), 'product_cat' );

		$avvisi = implode( ' ', $this->sposta( $b, $a )->get_data()['avvisi'] );

		$this->assertStringContainsString( 'indirizzi dei prodotti', $avvisi );
		delete_option( 'woocommerce_permalinks' );
	}

	public function test_i_casi_che_romperebbero_l_albero_sono_rifiutati(): void {
		$a = $this->categoria( 'A' );
		$b = $this->categoria( 'B', $a );
		$predefinita = (int) get_option( 'default_product_cat' );

		$this->assertSame( 404, $this->sposta( 999999, $a )->get_status() );
		$this->assertSame( 404, $this->sposta( $a, 999999 )->get_status() );
		$this->assertSame( 400, $this->sposta( $b, $a )->get_status(), 'Gia\' li\'.' );
		$this->assertSame( 400, $this->sposta( $a, $b )->get_status(), 'Dentro una sua figlia.' );
		$this->assertSame( 400, $this->sposta( $a, $a )->get_status() );
		$this->assertSame( 400, $this->sposta( $predefinita, $a )->get_status() );
	}

	public function test_validazione_e_permessi(): void {
		$a = $this->categoria( 'A' );
		$b = $this->categoria( 'B' );

		$this->assertSame( 400, $this->richiesta( 'POST', '/categorie/sposta', array( 'id' => $a ), true )->get_status() );
		$this->assertSame( 400, $this->richiesta( 'POST', '/categorie/sposta', array( 'id' => $a, 'nuova_madre' => -1 ), true )->get_status() );

		$this->accedi_come( 'subscriber' );
		$this->assertSame( 403, $this->richiesta( 'GET', '/categorie' )->get_status() );
		$this->assertSame( 403, $this->sposta( $a, $b )->get_status() );
	}

	/**
	 * Chiama POST /categorie (in JSON, come fa il server MCP).
	 *
	 * @param string $nome      Nome.
	 * @param int    $madre     Madre.
	 * @param bool   $anteprima Se true non crea.
	 */
	private function crea( string $nome, int $madre = 0, bool $anteprima = true ) {
		return $this->richiesta( 'POST', '/categorie', array( 'nome' => $nome, 'madre' => $madre, 'solo_anteprima' => $anteprima ), true );
	}

	public function test_creare_una_categoria_in_anteprima_non_la_crea(): void {
		$dati = $this->crea( 'Indumenti' )->get_data();

		$this->assertTrue( $dati['solo_anteprima'] );
		$this->assertSame( 'Indumenti', $dati['percorso_dopo'] );
		$this->assertNull( term_exists( 'Indumenti', 'product_cat' ) );
	}

	public function test_la_creazione_vera_crea_la_categoria_alla_radice_e_sotto_una_madre(): void {
		$radice = $this->crea( 'Indumenti', 0, false );
		$this->assertSame( 200, $radice->get_status() );
		$id = $radice->get_data()['id'];
		$this->assertSame( 0, get_term( $id, 'product_cat' )->parent );
		$this->assertNotEmpty( $radice->get_data()['url'] );

		$figlia = $this->crea( 'Magliette', $id, false )->get_data();
		$this->assertSame( 'Indumenti > Magliette', $figlia['percorso'] );
		$this->assertSame( $id, get_term( $figlia['id'], 'product_cat' )->parent );
	}

	public function test_un_nome_gia_presente_in_qualunque_ramo_e_rifiutato_e_non_ne_crea_un_altro(): void {
		$a = $this->categoria( 'A' );
		$this->categoria( 'Cover', $a );
		$prima = count( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) );

		$this->assertSame( 409, $this->crea( 'cover', 0, false )->get_status() );
		$this->assertSame( 409, $this->crea( 'Cover', $a, false )->get_status() );

		$this->assertCount( $prima, get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) );
	}

	public function test_creare_con_madre_inesistente_nome_vuoto_o_troppi_livelli_e_rifiutato(): void {
		$this->assertSame( 404, $this->crea( 'Nuova', 999999, false )->get_status() );
		$this->assertSame( 400, $this->crea( '   ', 0, false )->get_status() );

		$id = 0;
		foreach ( array( 'L1', 'L2', 'L3', 'L4', 'L5' ) as $nome ) {
			$id = $this->categoria( $nome, $id );
		}
		$this->assertSame( 400, $this->crea( 'L6', $id, false )->get_status() );
	}

	public function test_creare_richiede_il_nome_e_i_permessi(): void {
		$this->assertSame( 400, $this->richiesta( 'POST', '/categorie', array( 'madre' => 0 ), true )->get_status() );
		$this->assertSame( 400, $this->richiesta( 'POST', '/categorie', array( 'nome' => 'X', 'madre' => -1 ), true )->get_status() );

		$this->accedi_come( 'subscriber' );
		$this->assertSame( 403, $this->crea( 'X', 0, false )->get_status() );
	}
}
