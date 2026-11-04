<?php
/**
 * Test degli endpoint del blog: brief, contesto e articoli in bozza.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

/**
 * Gli articoli nascono sempre in bozza, un titolo gia' usato blocca il doppione, solo le
 * bozze si modificano da qui, e il contesto dice all'assistente cosa linkare e cosa c'e' gia'.
 */
class BlogControllerTest extends RestTestCase {

	/**
	 * Corpo base di un articolo.
	 *
	 * @param array $extra Campi da aggiungere o sostituire.
	 */
	private function corpo( array $extra = array() ): array {
		return array_merge(
			array(
				'titolo'    => 'Come scegliere lo zaino giusto',
				'contenuto' => '<h2>Il peso</h2><p>Uno zaino leggero fa la differenza su ogni sentiero.</p>',
			),
			$extra
		);
	}

	/**
	 * Crea un articolo passando dall'endpoint (in JSON, come fa il server MCP).
	 *
	 * @param array $extra Campi da aggiungere o sostituire.
	 */
	private function crea( array $extra = array() ) {
		return $this->richiesta( 'POST', '/blog/articoli', $this->corpo( $extra ), true );
	}

	public function test_il_brief_parte_vuoto_e_si_salva_a_pezzi(): void {
		$iniziale = $this->richiesta( 'GET', '/blog/brief' )->get_data();
		$this->assertFalse( $iniziale['compilato'] );
		$this->assertSame( 'it', $iniziale['lingua'] );

		$salvato = $this->richiesta(
			'POST',
			'/blog/brief',
			array(
				'argomento'  => 'Zaini da trekking',
				'n_articoli' => 4,
			),
			true
		)->get_data();
		$this->assertTrue( $salvato['compilato'] );

		$this->richiesta( 'POST', '/blog/brief', array( 'tono' => 'Amichevole' ), true );
		$letto = $this->richiesta( 'GET', '/blog/brief' )->get_data();

		$this->assertSame( 'Zaini da trekking', $letto['argomento'] );
		$this->assertSame( 4, $letto['n_articoli'] );
		$this->assertSame( 'Amichevole', $letto['tono'] );
	}

	public function test_un_articolo_nasce_sempre_in_bozza(): void {
		$risposta = $this->crea(
			array(
				'estratto'            => 'Come sceglierlo.',
				'categoria'           => 'Guide',
				'tag'                 => array( 'trekking', 'zaini' ),
				'titolo_seo'          => 'Scegliere lo zaino giusto',
				'meta_description'    => 'Peso, volume e schiena: come scegliere lo zaino.',
				'parola_chiave_focus' => 'zaino trekking',
			)
		);

		$this->assertSame( 201, $risposta->get_status() );
		$dati = $risposta->get_data();
		$this->assertSame( 'creato_in_bozza', $dati['esito'] );
		$this->assertSame( 'draft', $dati['stato'] );
		$this->assertTrue( $dati['categoria_creata'] );

		$articolo = get_post( $dati['articolo_id'] );
		$this->assertSame( 'draft', $articolo->post_status );
		$this->assertSame( 'post', $articolo->post_type );
		$this->assertSame( 'Come sceglierlo.', $articolo->post_excerpt );
		$this->assertSame( array( 'Guide' ), wp_get_post_categories( $articolo->ID, array( 'fields' => 'names' ) ) );
		$this->assertEqualSets( array( 'trekking', 'zaini' ), wp_get_post_tags( $articolo->ID, array( 'fields' => 'names' ) ) );
		$this->assertSame( 'zaino trekking', get_post_meta( $articolo->ID, '_yoast_wpseo_focuskw', true ) );
		$this->assertSame( 11, $dati['n_parole'] );
		// Una bozza non ha la data GMT: la data non deve diventare 0000-00-00.
		$this->assertMatchesRegularExpression( '/^' . gmdate( 'Y' ) . '-\d\d-\d\dT/', $dati['creato'] );
	}

	public function test_riusa_una_categoria_che_esiste_gia(): void {
		self::factory()->category->create( array( 'name' => 'Guide' ) );

		$dati = $this->crea( array( 'categoria' => 'Guide' ) )->get_data();

		$this->assertFalse( $dati['categoria_creata'] );
	}

	public function test_titolo_e_testo_sono_obbligatori(): void {
		$this->assertSame( 400, $this->richiesta( 'POST', '/blog/articoli', array( 'contenuto' => '<p>x</p>' ), true )->get_status() );
		$this->assertSame( 400, $this->crea( array( 'titolo' => '   ' ) )->get_status() );
		$this->assertSame( 400, $this->crea( array( 'contenuto' => '<p> </p>' ) )->get_status() );
		$this->assertSame( 0, count( get_posts( array( 'post_status' => 'any', 'numberposts' => -1 ) ) ) );
	}

	public function test_toglie_gli_script_dal_testo(): void {
		$dati = $this->crea( array( 'contenuto' => '<p>Testo valido</p><script>alert(1)</script>' ) )->get_data();

		$this->assertStringNotContainsString( 'script', get_post( $dati['articolo_id'] )->post_content );
	}

	public function test_un_titolo_gia_usato_blocca_il_doppione(): void {
		$primo = $this->crea()->get_data();

		$secondo = $this->crea();

		$this->assertSame( 409, $secondo->get_status() );
		$this->assertSame( 'push_contempt_articolo_duplicato', $secondo->get_data()['code'] );
		$this->assertSame( $primo['articolo_id'], $secondo->get_data()['data']['articolo_id'] );
		$this->assertCount( 1, get_posts( array( 'post_status' => 'any', 'numberposts' => -1 ) ) );

		$this->assertSame( 201, $this->crea( array( 'consenti_duplicato' => true ) )->get_status() );
	}

	public function test_un_articolo_cestinato_non_conta_come_doppione(): void {
		$primo = $this->crea()->get_data();
		wp_trash_post( $primo['articolo_id'] );

		$this->assertSame( 201, $this->crea()->get_status() );
	}

	public function test_rifiuta_immagine_e_prodotti_inesistenti_senza_creare_niente(): void {
		$this->assertSame( 400, $this->crea( array( 'immagine_evidenza_id' => 999999 ) )->get_status() );
		$this->assertSame( 400, $this->crea( array( 'prodotti_ids' => array( 999999 ) ) )->get_status() );
		$this->assertCount( 0, get_posts( array( 'post_status' => 'any', 'numberposts' => -1 ) ) );
	}

	public function test_collega_i_prodotti_e_conta_i_link_interni(): void {
		$prodotto = $this->prodotto();
		$url      = get_permalink( $prodotto );

		$dati = $this->crea(
			array(
				'contenuto'    => '<p>Guarda <a href="' . $url . '">questo prodotto</a> e <a href="https://esempio.org/x">una fonte</a>.</p>',
				'prodotti_ids' => array( $prodotto, $prodotto ),
			)
		)->get_data();

		$this->assertSame( array( $prodotto ), $dati['prodotti_collegati'] );
		$this->assertSame( 1, $dati['n_link_interni'] );
		$this->assertSame( 1, $dati['n_link_esterni'] );
	}

	public function test_imposta_immagine_in_evidenza_e_alt(): void {
		$immagine = self::factory()->attachment->create_object(
			array(
				'file'           => 'prova.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);

		$dati = $this->crea(
			array(
				'immagine_evidenza_id' => $immagine,
				'alt_immagini'         => array( (string) $immagine => 'Uno zaino rosso su un sentiero' ),
			)
		)->get_data();

		$this->assertSame( $immagine, (int) get_post_thumbnail_id( $dati['articolo_id'] ) );
		$this->assertSame( 'Uno zaino rosso su un sentiero', get_post_meta( $immagine, '_wp_attachment_image_alt', true ) );
	}

	public function test_elenco_per_stato_e_ricerca(): void {
		$bozza = $this->crea( array( 'titolo' => 'Zaini leggeri' ) )->get_data()['articolo_id'];
		self::factory()->post->create(
			array(
				'post_title'  => 'Tende da campeggio',
				'post_status' => 'publish',
			)
		);

		$tutti   = $this->richiesta( 'GET', '/blog/articoli' )->get_data();
		$bozze   = $this->richiesta( 'GET', '/blog/articoli', array( 'stato' => 'draft' ) )->get_data();
		$cercati = $this->richiesta( 'GET', '/blog/articoli', array( 'ricerca' => 'Tende' ) )->get_data();

		$this->assertCount( 2, $tutti );
		$this->assertSame( array( $bozza ), array_column( $bozze, 'id' ) );
		$this->assertSame( array( 'Tende da campeggio' ), array_column( $cercati, 'titolo' ) );
		$this->assertNull( $bozze[0]['url'] );
	}

	public function test_legge_un_articolo_per_intero(): void {
		$id = $this->crea( array( 'meta_description' => 'Una meta.', 'categoria' => 'Guide' ) )->get_data()['articolo_id'];

		$dati = $this->richiesta( 'GET', "/blog/articoli/{$id}" )->get_data();

		$this->assertStringContainsString( 'Il peso', $dati['contenuto'] );
		$this->assertSame( 'Una meta.', $dati['meta_description'] );
		$this->assertNull( $dati['titolo_seo'] );
		$this->assertSame( array( 'Guide' ), $dati['categorie'] );
	}

	public function test_non_legge_pagine_prodotti_o_id_inesistenti(): void {
		$this->assertSame( 404, $this->richiesta( 'GET', '/blog/articoli/999999' )->get_status() );
		$this->assertSame( 404, $this->richiesta( 'GET', '/blog/articoli/' . $this->prodotto() )->get_status() );
		$this->assertSame( 404, $this->richiesta( 'POST', '/blog/articoli/' . $this->prodotto(), array( 'titolo' => 'x' ), true )->get_status() );
	}

	public function test_modifica_solo_i_campi_mandati(): void {
		$id = $this->crea( array( 'meta_description' => 'Meta originale', 'estratto' => 'Estratto originale' ) )->get_data()['articolo_id'];

		$risposta = $this->richiesta( 'POST', "/blog/articoli/{$id}", array( 'titolo' => 'Titolo nuovo' ), true );

		$this->assertSame( 200, $risposta->get_status() );
		$articolo = get_post( $id );
		$this->assertSame( 'Titolo nuovo', $articolo->post_title );
		$this->assertStringContainsString( 'Il peso', $articolo->post_content );
		$this->assertSame( 'Estratto originale', $articolo->post_excerpt );
		$this->assertSame( 'Meta originale', get_post_meta( $id, '_yoast_wpseo_metadesc', true ) );
		$this->assertSame( 'draft', $articolo->post_status );
	}

	public function test_non_modifica_un_articolo_pubblicato(): void {
		$id = self::factory()->post->create( array( 'post_title' => 'Gia online', 'post_status' => 'publish' ) );

		$risposta = $this->richiesta( 'POST', "/blog/articoli/{$id}", array( 'titolo' => 'Cambiato' ), true );

		$this->assertSame( 409, $risposta->get_status() );
		$this->assertSame( 'Gia online', get_post( $id )->post_title );
	}

	public function test_non_svuota_titolo_e_testo_in_modifica(): void {
		$id = $this->crea()->get_data()['articolo_id'];

		$this->assertSame( 400, $this->richiesta( 'POST', "/blog/articoli/{$id}", array( 'titolo' => ' ' ), true )->get_status() );
		$this->assertSame( 400, $this->richiesta( 'POST', "/blog/articoli/{$id}", array( 'contenuto' => '<p></p>' ), true )->get_status() );
		$this->assertSame( 'Come scegliere lo zaino giusto', get_post( $id )->post_title );
	}

	public function test_il_contesto_ha_negozio_prodotti_e_articoli(): void {
		$prodotto = $this->prodotto();
		$this->crea( array( 'titolo' => 'Articolo esistente' ) );
		$this->richiesta( 'POST', '/blog/brief', array( 'argomento' => 'Zaini' ), true );

		$contesto = $this->richiesta( 'GET', '/blog/contesto' )->get_data();

		$this->assertSame( get_bloginfo( 'name' ), $contesto['negozio']['nome'] );
		$this->assertTrue( $contesto['brief']['compilato'] );
		$this->assertContains( $prodotto, array_column( $contesto['prodotti'], 'id' ) );
		$this->assertNotEmpty( $contesto['prodotti'][0]['url'] );
		$this->assertSame( array( 'Articolo esistente' ), array_column( $contesto['articoli_esistenti'], 'titolo' ) );
		$this->assertFalse( $contesto['prodotti_limitati'] );
	}

	public function test_il_contesto_da_l_immagine_del_prodotto_per_riusarla(): void {
		$senza  = $this->prodotto();
		$con    = $this->prodotto();
		$foto   = self::factory()->attachment->create_object(
			array(
				'file'           => 'tenda.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		set_post_thumbnail( $con, $foto );

		$righe = array_column( $this->richiesta( 'GET', '/blog/contesto' )->get_data()['prodotti'], null, 'id' );

		$this->assertNull( $righe[ $senza ]['immagine_id'] );
		$this->assertSame( $foto, $righe[ $con ]['immagine_id'] );
		$this->assertStringContainsString( 'tenda.jpg', $righe[ $con ]['immagine_url'] );
	}

	public function test_il_contesto_limita_i_prodotti_alle_categorie_del_brief(): void {
		$tende  = self::factory()->term->create( array( 'taxonomy' => 'product_cat', 'name' => 'Tende' ) );
		$zaini  = self::factory()->term->create( array( 'taxonomy' => 'product_cat', 'name' => 'Zaini' ) );
		$tenda  = $this->prodotto();
		$zaino  = $this->prodotto();
		wp_set_object_terms( $tenda, array( $tende ), 'product_cat' );
		wp_set_object_terms( $zaino, array( $zaini ), 'product_cat' );
		$this->richiesta( 'POST', '/blog/brief', array( 'argomento' => 'Zaini', 'categorie_prodotti' => array( $zaini ) ), true );

		$ids = array_column( $this->richiesta( 'GET', '/blog/contesto' )->get_data()['prodotti'], 'id' );

		$this->assertSame( array( $zaino ), $ids );
	}

	public function test_serve_il_permesso_di_gestire_il_negozio(): void {
		$this->accedi_come( 'subscriber' );
		foreach ( array( '/blog/brief', '/blog/contesto', '/blog/articoli' ) as $rotta ) {
			$this->assertSame( 403, $this->richiesta( 'GET', $rotta )->get_status(), $rotta );
		}
		$this->assertSame( 403, $this->crea()->get_status() );

		$this->accedi_come( '' );
		$this->assertSame( 401, $this->richiesta( 'GET', '/blog/brief' )->get_status() );
	}
}
