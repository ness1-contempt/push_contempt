<?php
/**
 * Test della creazione di prodotti (POST /prodotti), semplici e variabili.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

/**
 * Il prodotto nasce sempre in bozza; per i variabili: tutte le combinazioni,
 * prezzo unico, nessuna scorta, anteprima senza effetti e niente a meta'.
 */
class ProdottiCreaControllerTest extends RestTestCase {

	/**
	 * Corpo base di una creazione.
	 *
	 * @param array $extra Campi da aggiungere o sostituire.
	 */
	private function corpo( array $extra = array() ): array {
		return array_merge(
			array(
				'titolo'          => 'Telefono di prova',
				'descrizione'     => '<p>Descrizione</p>',
				'prezzo_regolare' => '702',
				'categoria'       => 'Categoria di prova',
			),
			$extra
		);
	}

	/**
	 * Attributi di esempio: colore e memoria.
	 */
	private function attributi(): array {
		return array(
			array(
				'nome'   => 'Colore',
				'valori' => array( 'Nero', 'Bianco' ),
			),
			array(
				'nome'   => 'Memoria',
				'valori' => array( '256 GB', '512 GB' ),
			),
		);
	}

	/**
	 * Crea passando dall'endpoint (in JSON, come fa il server MCP).
	 *
	 * @param array $extra Campi da aggiungere o sostituire.
	 */
	private function crea( array $extra = array() ) {
		return $this->richiesta( 'POST', '/prodotti', $this->corpo( $extra ), true );
	}

	/**
	 * Quanti prodotti e varianti ci sono, in qualunque stato.
	 */
	private function conta_prodotti(): int {
		return count(
			get_posts(
				array(
					'post_type'   => array( 'product', 'product_variation' ),
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}

	public function test_un_semplice_nasce_in_bozza_come_prima(): void {
		$risposta = $this->crea();

		$this->assertSame( 201, $risposta->get_status() );
		$dati     = $risposta->get_data();
		$prodotto = wc_get_product( $dati['prodotto_id'] );
		$this->assertSame( 'draft', $prodotto->get_status() );
		$this->assertSame( 'simple', $prodotto->get_type() );
		$this->assertSame( '702', $prodotto->get_regular_price() );
		$this->assertSame( 'simple', $dati['tipo'] );
	}

	public function test_un_variabile_nasce_in_bozza_con_tutte_le_combinazioni_a_prezzo_unico(): void {
		$risposta = $this->crea(
			array(
				'tipo'      => 'variable',
				'attributi' => $this->attributi(),
			)
		);

		$this->assertSame( 201, $risposta->get_status() );
		$dati     = $risposta->get_data();
		$prodotto = wc_get_product( $dati['prodotto_id'] );
		$this->assertInstanceOf( \WC_Product_Variable::class, $prodotto );
		$this->assertSame( 'draft', $prodotto->get_status() );
		$this->assertCount( 4, $prodotto->get_children() );
		$this->assertSame( 4, $dati['numero_varianti'] );
		$this->assertEquals( array( 702, 702 ), array( $dati['prezzo_min'], $dati['prezzo_max'] ) );

		$combinazioni = array();
		foreach ( $prodotto->get_children() as $figlio_id ) {
			$variante = wc_get_product( $figlio_id );
			$this->assertSame( '702', $variante->get_regular_price() );
			$this->assertFalse( $variante->get_manage_stock() );
			$this->assertSame( 'publish', $variante->get_status() );
			$attributi      = $variante->get_attributes();
			$combinazioni[] = $attributi['colore'] . ' / ' . $attributi['memoria'];
		}
		sort( $combinazioni );
		$this->assertSame( array( 'Bianco / 256 GB', 'Bianco / 512 GB', 'Nero / 256 GB', 'Nero / 512 GB' ), $combinazioni );
	}

	public function test_gli_attributi_del_padre_sono_propri_e_usati_per_le_varianti(): void {
		$id = $this->crea(
			array(
				'tipo'      => 'variable',
				'attributi' => $this->attributi(),
			)
		)->get_data()['prodotto_id'];

		$attributi = array_values( wc_get_product( $id )->get_attributes() );

		$this->assertSame( 'Colore', $attributi[0]->get_name() );
		$this->assertSame( array( 'Nero', 'Bianco' ), $attributi[0]->get_options() );
		$this->assertTrue( $attributi[0]->get_variation() );
		$this->assertSame( 0, $attributi[0]->get_id() ); // Non e' un attributo globale.
	}

	public function test_senza_immagini_il_variabile_si_crea_lo_stesso(): void {
		$risposta = $this->crea(
			array(
				'tipo'      => 'variable',
				'attributi' => array(
					array(
						'nome'   => 'Taglia',
						'valori' => array( 'S', 'M' ),
					),
				),
			)
		);

		$this->assertSame( 201, $risposta->get_status() );
		$this->assertEmpty( wc_get_product( $risposta->get_data()['prodotto_id'] )->get_image_id() );
	}

	public function test_l_anteprima_non_scrive_niente_nemmeno_la_categoria(): void {
		$prima = $this->conta_prodotti();

		$risposta = $this->crea(
			array(
				'tipo'           => 'variable',
				'attributi'      => $this->attributi(),
				'solo_anteprima' => true,
			)
		);

		$this->assertSame( 200, $risposta->get_status() );
		$dati = $risposta->get_data();
		$this->assertSame( 'anteprima', $dati['stato'] );
		$this->assertSame( 4, $dati['numero_varianti'] );
		$this->assertCount( 4, $dati['varianti'] );
		$this->assertSame( array( 'Colore' => 'Nero', 'Memoria' => '256 GB' ), $dati['varianti'][0]['valori'] );
		$this->assertSame( '702', $dati['varianti'][0]['prezzo'] );
		$this->assertTrue( $dati['categoria_nuova'] );
		$this->assertSame( 'non gestite', $dati['scorte'] );
		$this->assertSame( $prima, $this->conta_prodotti() );
		$this->assertFalse( get_term_by( 'name', 'Categoria di prova', 'product_cat' ) );
	}

	public function test_l_anteprima_di_un_semplice_non_crea_niente(): void {
		$prima = $this->conta_prodotti();

		$dati = $this->crea( array( 'solo_anteprima' => true ) )->get_data();

		$this->assertSame( 'anteprima', $dati['stato'] );
		$this->assertArrayNotHasKey( 'varianti', $dati );
		$this->assertSame( $prima, $this->conta_prodotti() );
	}

	public function test_attributi_non_validi_danno_400_e_non_creano_niente(): void {
		$prima = $this->conta_prodotti();
		$casi  = array(
			'senza attributi'  => array( 'tipo' => 'variable' ),
			'valori doppi'     => array(
				'tipo'      => 'variable',
				'attributi' => array(
					array(
						'nome'   => 'Colore',
						'valori' => array( 'Nero', 'nero' ),
					),
				),
			),
			'attributo vuoto'  => array(
				'tipo'      => 'variable',
				'attributi' => array(
					array(
						'nome'   => 'Colore',
						'valori' => array(),
					),
				),
			),
			'nomi con stesso slug' => array(
				'tipo'      => 'variable',
				'attributi' => array(
					array(
						'nome'   => 'Memoria (GB)',
						'valori' => array( '1' ),
					),
					array(
						'nome'   => 'Memoria GB',
						'valori' => array( '2' ),
					),
				),
			),
			'prezzo non valido' => array(
				'tipo'            => 'variable',
				'prezzo_regolare' => '12,50',
				'attributi'       => $this->attributi(),
			),
			'prezzo zero'      => array(
				'tipo'            => 'variable',
				'prezzo_regolare' => '0',
				'attributi'       => $this->attributi(),
			),
		);

		foreach ( $casi as $nome => $extra ) {
			$risposta = $this->crea( $extra );
			$this->assertSame( 400, $risposta->get_status(), $nome );
			$this->assertNotEmpty( $risposta->get_data()['data']['errori'], $nome );
		}
		$this->assertSame( $prima, $this->conta_prodotti() );
		$this->assertFalse( get_term_by( 'name', 'Categoria di prova', 'product_cat' ) );
	}

	public function test_troppe_combinazioni_danno_400(): void {
		$molti = array_map( 'strval', range( 1, 8 ) );

		$risposta = $this->crea(
			array(
				'tipo'      => 'variable',
				'attributi' => array(
					array(
						'nome'   => 'A',
						'valori' => $molti,
					),
					array(
						'nome'   => 'B',
						'valori' => $molti,
					),
				),
			)
		);

		$this->assertSame( 400, $risposta->get_status() );
		$this->assertStringContainsString( '64', $risposta->get_data()['message'] );
	}

	public function test_un_errore_a_meta_toglie_padre_e_varianti_gia_create(): void {
		$prima    = $this->conta_prodotti();
		$contatore = 0;
		$guasto   = function () use ( &$contatore ) {
			$contatore++;
			if ( 2 === $contatore ) {
				throw new \RuntimeException( 'guasto simulato' );
			}
		};
		add_action( 'woocommerce_new_product_variation', $guasto );

		$risposta = $this->crea(
			array(
				'tipo'      => 'variable',
				'attributi' => $this->attributi(),
			)
		);

		remove_action( 'woocommerce_new_product_variation', $guasto );
		$this->assertSame( 500, $risposta->get_status() );
		$this->assertStringContainsString( 'non e\' stato creato niente', $risposta->get_data()['message'] );
		$this->assertSame( $prima, $this->conta_prodotti() );
	}

	public function test_il_tipo_deve_essere_semplice_o_variabile(): void {
		$this->assertSame( 400, $this->crea( array( 'tipo' => 'grouped' ) )->get_status() );
	}

	public function test_permessi(): void {
		$this->accedi_come( 'subscriber' );
		$this->assertSame( 403, $this->crea()->get_status() );
		$this->accedi_come( '' );
		$this->assertSame( 401, $this->crea()->get_status() );
	}

	public function test_le_bozze_dicono_tipo_e_numero_di_varianti(): void {
		$id = $this->crea(
			array(
				'tipo'      => 'variable',
				'attributi' => $this->attributi(),
			)
		)->get_data()['prodotto_id'];

		$righe = array_column( $this->richiesta( 'GET', '/prodotti/bozze' )->get_data(), null, 'id' );

		$this->assertSame( 'variable', $righe[ $id ]['tipo'] );
		$this->assertSame( 4, $righe[ $id ]['numero_varianti'] );
	}

	// --- albero delle categorie (percorso_categoria) -----------------------------

	/**
	 * Crea una categoria prodotto e ne restituisce l'id.
	 *
	 * @param string $nome  Nome.
	 * @param int    $madre Id della madre (0 = radice).
	 */
	private function categoria( string $nome, int $madre = 0 ): int {
		$risultato = wp_insert_term( $nome, 'product_cat', array( 'parent' => $madre ) );
		return (int) $risultato['term_id'];
	}

	/**
	 * Quante categorie prodotto ci sono.
	 */
	private function conta_categorie(): int {
		return (int) wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
	}

	public function test_il_percorso_crea_le_categorie_una_dentro_l_altra_e_il_prodotto_va_nell_ultima(): void {
		$risposta = $this->crea( array( 'percorso_categoria' => array( 'Indumenti', 'Magliette' ) ) );

		$this->assertSame( 201, $risposta->get_status() );
		$dati      = $risposta->get_data();
		$indumenti = get_term_by( 'name', 'Indumenti', 'product_cat' );
		$magliette = get_term_by( 'name', 'Magliette', 'product_cat' );
		$this->assertSame( 0, (int) $indumenti->parent );
		$this->assertSame( (int) $indumenti->term_id, (int) $magliette->parent );
		$this->assertSame( array( (int) $magliette->term_id ), wc_get_product( $dati['prodotto_id'] )->get_category_ids() );
		$this->assertSame( array( 'Indumenti', 'Indumenti > Magliette' ), $dati['categorie_create'] );
	}

	public function test_le_categorie_gia_nel_posto_giusto_si_riusano_e_si_crea_solo_il_resto(): void {
		$indumenti = $this->categoria( 'Indumenti' );
		$magliette = $this->categoria( 'Magliette', $indumenti );
		$prima     = $this->conta_categorie();

		$dati = $this->crea( array( 'percorso_categoria' => array( 'indumenti', ' MAGLIETTE ', 'Sportive' ) ) )->get_data();

		$this->assertSame( $prima + 1, $this->conta_categorie() );
		$this->assertSame( array( 'Indumenti > Magliette > Sportive' ), $dati['categorie_create'] );
		$sportive = get_term_by( 'name', 'Sportive', 'product_cat' );
		$this->assertSame( $magliette, (int) $sportive->parent );
	}

	public function test_una_categoria_con_il_nome_in_un_altro_ramo_da_409_e_non_crea_niente(): void {
		$abbigliamento = $this->categoria( 'Abbigliamento' );
		$this->categoria( 'Magliette', $abbigliamento );
		$prima_cat = $this->conta_categorie();
		$prima     = $this->conta_prodotti();

		$risposta = $this->crea( array( 'percorso_categoria' => array( 'Indumenti', 'Magliette' ) ) );

		$this->assertSame( 409, $risposta->get_status() );
		$this->assertStringContainsString( 'esiste gia\' come «Abbigliamento > Magliette»', $risposta->get_data()['message'] );
		$this->assertStringContainsString( 'la cartella la vuole in «Indumenti > Magliette»', $risposta->get_data()['message'] );
		$this->assertSame( $prima_cat, $this->conta_categorie() ); // Nemmeno Indumenti e' stata creata.
		$this->assertSame( $prima, $this->conta_prodotti() );
	}

	public function test_due_categorie_con_lo_stesso_nome_sul_sito_danno_409(): void {
		$this->categoria( 'Magliette' );
		$madre = $this->categoria( 'Abbigliamento' );
		$this->categoria( 'Magliette 2', $madre );
		wp_update_term( (int) get_term_by( 'name', 'Magliette 2', 'product_cat' )->term_id, 'product_cat', array( 'name' => 'Magliette', 'slug' => 'magliette-bis' ) );

		$risposta = $this->crea( array( 'percorso_categoria' => array( 'Magliette' ) ) );

		$this->assertSame( 409, $risposta->get_status() );
		$this->assertStringContainsString( 'Esistono 2 categorie chiamate «Magliette»', $risposta->get_data()['message'] );
	}

	public function test_l_anteprima_mostra_cosa_si_creerebbe_senza_creare_niente(): void {
		$indumenti = $this->categoria( 'Indumenti' );
		$prima_cat = $this->conta_categorie();
		$prima     = $this->conta_prodotti();

		$dati = $this->crea(
			array(
				'percorso_categoria' => array( 'Indumenti', 'Magliette' ),
				'solo_anteprima'     => true,
			)
		)->get_data();

		$this->assertSame( array( 'esiste', 'da_creare' ), array_column( $dati['categorie'], 'stato' ) );
		$this->assertSame( $indumenti, $dati['categorie'][0]['id'] );
		$this->assertTrue( $dati['categoria_nuova'] );
		$this->assertSame( $prima_cat, $this->conta_categorie() );
		$this->assertSame( $prima, $this->conta_prodotti() );
	}

	public function test_l_anteprima_segnala_gia_il_conflitto(): void {
		$this->categoria( 'Magliette' );

		$risposta = $this->crea(
			array(
				'percorso_categoria' => array( 'Indumenti', 'Magliette' ),
				'solo_anteprima'     => true,
			)
		);

		$this->assertSame( 409, $risposta->get_status() );
	}

	public function test_percorsi_malformati_danno_400(): void {
		$prima = $this->conta_categorie();
		$casi  = array(
			'livello vuoto'   => array( 'Indumenti', '  ' ),
			'troppi livelli'  => array( 'a', 'b', 'c', 'd', 'e', 'f' ),
			'nome ripetuto'   => array( 'Indumenti', 'indumenti' ),
		);
		foreach ( $casi as $nome => $percorso ) {
			$this->assertSame( 400, $this->crea( array( 'percorso_categoria' => $percorso ) )->get_status(), $nome );
		}
		$this->assertSame( $prima, $this->conta_categorie() ); // Niente creato.
	}

	public function test_serve_la_categoria_o_il_percorso(): void {
		$corpo = $this->corpo();
		unset( $corpo['categoria'] );

		$risposta = $this->richiesta( 'POST', '/prodotti', $corpo, true );

		$this->assertSame( 400, $risposta->get_status() );
	}

	public function test_la_categoria_singola_di_prima_funziona_ancora(): void {
		$madre    = $this->categoria( 'Abbigliamento' );
		$figlia   = $this->categoria( 'Pantaloni', $madre );
		$risposta = $this->crea( array( 'categoria' => 'Pantaloni' ) );

		$this->assertSame( 201, $risposta->get_status() );
		$this->assertSame( array( $figlia ), wc_get_product( $risposta->get_data()['prodotto_id'] )->get_category_ids() );
		$this->assertSame( array(), $risposta->get_data()['categorie_create'] );
	}

	public function test_i_nomi_con_caratteri_speciali_si_riconoscono(): void {
		$this->categoria( 'Accessori & Cavi' );
		$prima = $this->conta_categorie();

		$dati = $this->crea( array( 'percorso_categoria' => array( 'Accessori & Cavi', 'Caricatori' ) ) )->get_data();

		$this->assertSame( $prima + 1, $this->conta_categorie() );
		$this->assertSame( array( 'Accessori & Cavi > Caricatori' ), $dati['categorie_create'] );
	}

	public function test_un_errore_a_meta_toglie_anche_le_categorie_create(): void {
		$prima_cat = $this->conta_categorie();
		$guasto    = static function () {
			throw new \RuntimeException( 'guasto simulato' );
		};
		add_action( 'woocommerce_new_product_variation', $guasto );

		$risposta = $this->crea(
			array(
				'tipo'               => 'variable',
				'attributi'          => $this->attributi(),
				'percorso_categoria' => array( 'Indumenti', 'Magliette' ),
			)
		);

		remove_action( 'woocommerce_new_product_variation', $guasto );
		$this->assertSame( 500, $risposta->get_status() );
		$this->assertSame( $prima_cat, $this->conta_categorie() );
	}

	public function test_la_verifica_dice_cosa_succederebbe_per_ogni_percorso_senza_scrivere(): void {
		$this->categoria( 'Indumenti' );
		$prima = $this->conta_categorie();

		$dati = $this->richiesta(
			'POST',
			'/categorie/verifica',
			array(
				'percorsi' => array(
					array( 'Indumenti', 'Magliette' ),
					array( 'Sport', 'Magliette' ),
					array( 'Indumenti', '' ),
				),
			),
			true
		)->get_data();

		$this->assertSame( array( 'esiste', 'da_creare' ), array_column( $dati['percorsi'][0]['livelli'], 'stato' ) );
		$this->assertNotSame( array(), $dati['percorsi'][2]['errori'] );
		$this->assertCount( 1, $dati['conflitti_tra_percorsi'] );
		$this->assertStringContainsString( 'Sport > Magliette', $dati['conflitti_tra_percorsi'][0] );
		$this->assertSame( $prima, $this->conta_categorie() );
	}

	public function test_la_verifica_rispetta_i_permessi(): void {
		$this->accedi_come( 'subscriber' );
		$this->assertSame( 403, $this->richiesta( 'POST', '/categorie/verifica', array( 'percorsi' => array() ), true )->get_status() );
		$this->accedi_come( '' );
		$this->assertSame( 401, $this->richiesta( 'POST', '/categorie/verifica', array( 'percorsi' => array() ), true )->get_status() );
	}
}
