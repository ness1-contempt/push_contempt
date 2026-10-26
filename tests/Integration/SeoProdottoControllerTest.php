<?php
/**
 * Test di /seo/prodotto, /seo/ripristina e /seo/base su WordPress e WooCommerce veri.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

use PushContempt\SeoSnapshot;

/**
 * La logica delle versioni e' provata a parte (SeoSnapshotTest); qui si prova
 * che il controller la colleghi bene a prodotti, meta, permessi e validazione.
 */
class SeoProdottoControllerTest extends RestTestCase {

	/**
	 * Scrive i valori SEO di un prodotto passando dall'endpoint.
	 *
	 * @param int    $id      Id del prodotto.
	 * @param array  $valori  Tre campi SEO.
	 * @param string $origine Origine della scrittura (vuota = non inviata).
	 */
	private function scrivi( int $id, array $valori, string $origine = '' ) {
		$params = array_merge( array( 'id' => $id ), $valori );
		if ( '' !== $origine ) {
			$params['origine'] = $origine;
		}
		return $this->richiesta( 'POST', '/seo/prodotto', $params );
	}

	/**
	 * Le versioni salvate nel meta del prodotto.
	 *
	 * @param int $id Id del prodotto.
	 */
	private function snapshot( int $id ): array {
		$snapshot = get_post_meta( $id, SeoSnapshot::META_KEY, true );
		return is_array( $snapshot ) ? $snapshot : array();
	}

	// --- permessi e validazione ------------------------------------------------

	public function test_un_visitatore_non_loggato_viene_rifiutato(): void {
		$id = $this->prodotto();
		$this->accedi_come( '' );

		$this->assertSame( 401, $this->richiesta( 'GET', '/seo/prodotto', array( 'id' => $id ) )->get_status() );
		$this->assertSame( 401, $this->scrivi( $id, $this->valori( 'x' ) )->get_status() );
		$this->assertSame( 401, $this->richiesta( 'POST', '/seo/ripristina', array( 'ids' => array( $id ), 'versione' => 'base' ) )->get_status() );
		$this->assertSame( 401, $this->richiesta( 'POST', '/seo/base', array( 'ids' => array( $id ) ) )->get_status() );
	}

	public function test_chi_non_gestisce_il_negozio_viene_rifiutato_e_non_scrive(): void {
		$id = $this->prodotto( $this->valori( 'Yoast' ) );
		$this->accedi_come( 'subscriber' );

		$this->assertSame( 403, $this->scrivi( $id, $this->valori( 'nuovo' ) )->get_status() );
		$this->assertSame( $this->valori( 'Yoast' ), $this->seo_vivo( $id ) );
		$this->assertSame( array(), $this->snapshot( $id ) );
	}

	public function test_parametri_obbligatori_mancanti_danno_400(): void {
		$id = $this->prodotto();

		$risposta = $this->richiesta( 'POST', '/seo/prodotto', array( 'id' => $id, 'titolo_seo' => 'Solo il titolo' ) );

		$this->assertSame( 400, $risposta->get_status() );
		$this->assertSame( array(), $this->snapshot( $id ) );
	}

	public function test_una_versione_sconosciuta_da_400(): void {
		$id = $this->prodotto();

		$risposta = $this->richiesta( 'POST', '/seo/ripristina', array( 'ids' => array( $id ), 'versione' => 'attuale' ) );

		$this->assertSame( 400, $risposta->get_status() );
	}

	// --- lettura ----------------------------------------------------------------

	public function test_la_lettura_restituisce_i_campi_yoast_o_null_se_vuoti(): void {
		$id = $this->prodotto( array( 'titolo_seo' => 'Titolo Yoast' ) );

		$dati = $this->richiesta( 'GET', '/seo/prodotto', array( 'id' => $id ) )->get_data();

		$this->assertSame( 'Prodotto di prova', $dati['titolo'] );
		$this->assertSame( 'Titolo Yoast', $dati['titolo_seo'] );
		$this->assertNull( $dati['meta_description'] );
		$this->assertNull( $dati['parola_chiave_focus'] );
	}

	public function test_un_prodotto_inesistente_da_404_in_lettura_e_in_scrittura(): void {
		$this->assertSame( 404, $this->richiesta( 'GET', '/seo/prodotto', array( 'id' => 999999 ) )->get_status() );
		$this->assertSame( 404, $this->scrivi( 999999, $this->valori( 'x' ) )->get_status() );
	}

	// --- scrittura e versioni -----------------------------------------------------

	public function test_la_prima_scrittura_crea_la_base_con_lo_stato_di_partenza(): void {
		$id = $this->prodotto( $this->valori( 'Yoast' ) );

		$risposta = $this->scrivi( $id, $this->valori( 'AI 1' ), 'concorrente x.it' );

		$this->assertSame( 200, $risposta->get_status() );
		$this->assertSame( $this->valori( 'AI 1' ), $risposta->get_data() );
		$this->assertSame( $this->valori( 'AI 1' ), $this->seo_vivo( $id ) );
		$snapshot = $this->snapshot( $id );
		$this->assertSame( 'Titolo Yoast', $snapshot['base']['titolo_seo'] );
		$this->assertSame( 'originale', $snapshot['base']['tipo'] );
		$this->assertSame( 'Titolo Yoast', $snapshot['precedente']['titolo_seo'] );
		$this->assertSame( 'concorrente x.it', $snapshot['ultima_scrittura']['origine'] );
	}

	public function test_senza_origine_la_scrittura_risulta_fatta_dall_api(): void {
		$id = $this->prodotto();

		$this->scrivi( $id, $this->valori( 'AI 1' ) );

		$this->assertSame( 'api', $this->snapshot( $id )['ultima_scrittura']['origine'] );
	}

	public function test_riscrivere_gli_stessi_valori_non_crea_versioni(): void {
		$id = $this->prodotto( $this->valori( 'Yoast' ) );

		$this->scrivi( $id, $this->valori( 'Yoast' ) );

		$this->assertSame( array(), $this->snapshot( $id ) );
	}

	public function test_la_seconda_scrittura_tiene_la_base_e_sposta_il_precedente(): void {
		$id = $this->prodotto( $this->valori( 'Yoast' ) );

		$this->scrivi( $id, $this->valori( 'AI 1' ), 'concorrente a.it' );
		$this->scrivi( $id, $this->valori( 'AI 2' ), 'concorrente b.it' );

		$snapshot = $this->snapshot( $id );
		$this->assertSame( 'Titolo Yoast', $snapshot['base']['titolo_seo'] );
		$this->assertSame( 'Titolo AI 1', $snapshot['precedente']['titolo_seo'] );
		$this->assertSame( 'concorrente a.it', $snapshot['precedente']['origine'] );
		$this->assertSame( 'concorrente b.it', $snapshot['ultima_scrittura']['origine'] );
	}

	public function test_una_modifica_manuale_fra_due_scritture_finisce_nel_precedente(): void {
		$id = $this->prodotto( $this->valori( 'Yoast' ) );
		$this->scrivi( $id, $this->valori( 'AI 1' ) );
		update_post_meta( $id, '_yoast_wpseo_title', 'Ritocco a mano in Yoast' );

		$this->scrivi( $id, $this->valori( 'AI 2' ) );

		$precedente = $this->snapshot( $id )['precedente'];
		$this->assertSame( 'Ritocco a mano in Yoast', $precedente['titolo_seo'] );
		$this->assertSame( SeoSnapshot::ORIGINE_MANUALE, $precedente['origine'] );
	}

	public function test_i_tag_html_vengono_tolti_dai_campi(): void {
		$id = $this->prodotto();

		$this->scrivi(
			$id,
			array(
				'titolo_seo'          => '<b>Titolo</b> <script>alert(1)</script>',
				'meta_description'    => 'Meta <i>pulita</i>',
				'parola_chiave_focus' => 'kw',
			)
		);

		$vivo = $this->seo_vivo( $id );
		$this->assertStringNotContainsString( '<', $vivo['titolo_seo'] );
		$this->assertStringNotContainsString( '<', $vivo['meta_description'] );
	}

	// --- ripristino ---------------------------------------------------------------

	public function test_ripristinare_la_base_e_poi_il_precedente_e_reversibile(): void {
		$id = $this->prodotto( $this->valori( 'Yoast' ) );
		$this->scrivi( $id, $this->valori( 'AI 1' ) );
		$this->scrivi( $id, $this->valori( 'AI 2' ) );

		$esiti = $this->richiesta( 'POST', '/seo/ripristina', array( 'ids' => array( $id ), 'versione' => 'base' ) )->get_data();

		$this->assertSame( array( $id => 'ripristinato' ), $esiti );
		$this->assertSame( $this->valori( 'Yoast' ), $this->seo_vivo( $id ) );
		$this->assertSame( 'Titolo AI 2', $this->snapshot( $id )['precedente']['titolo_seo'], 'il precedente e\' la versione lasciata' );

		$this->richiesta( 'POST', '/seo/ripristina', array( 'ids' => array( $id ), 'versione' => 'precedente' ) );

		$this->assertSame( $this->valori( 'AI 2' ), $this->seo_vivo( $id ) );
		$this->assertSame( 'Titolo Yoast', $this->snapshot( $id )['base']['titolo_seo'], 'la base non cambia' );
	}

	public function test_ripristinare_una_versione_gia_in_uso_non_cambia_niente(): void {
		$id = $this->prodotto( $this->valori( 'Yoast' ) );
		$this->scrivi( $id, $this->valori( 'AI 1' ) );
		$this->richiesta( 'POST', '/seo/ripristina', array( 'ids' => array( $id ), 'versione' => 'base' ) );
		$prima = $this->snapshot( $id );

		$esiti = $this->richiesta( 'POST', '/seo/ripristina', array( 'ids' => array( $id ), 'versione' => 'base' ) )->get_data();

		$this->assertSame( array( $id => 'gia_in_uso' ), $esiti );
		$this->assertSame( $prima, $this->snapshot( $id ) );
	}

	public function test_il_ripristino_in_blocco_da_un_esito_per_ogni_prodotto(): void {
		$modificato  = $this->prodotto( $this->valori( 'Yoast' ) );
		$mai_toccato = $this->prodotto( $this->valori( 'Yoast' ) );
		$this->scrivi( $modificato, $this->valori( 'AI 1' ) );

		$esiti = $this->richiesta(
			'POST',
			'/seo/ripristina',
			array(
				'ids'      => array( $modificato, $mai_toccato, 999999, $modificato ),
				'versione' => 'base',
			)
		)->get_data();

		$this->assertSame(
			array(
				$modificato  => 'ripristinato',
				$mai_toccato => 'nessuno_snapshot',
				999999       => 'non_trovato',
			),
			$esiti
		);
		$this->assertSame( $this->valori( 'Yoast' ), $this->seo_vivo( $modificato ) );
	}

	public function test_se_la_base_ha_un_campo_vuoto_il_ripristino_lo_toglie_da_yoast(): void {
		$id = $this->prodotto( array( 'titolo_seo' => 'Solo il titolo' ) );
		$this->scrivi( $id, $this->valori( 'AI 1' ) );

		$this->richiesta( 'POST', '/seo/ripristina', array( 'ids' => array( $id ), 'versione' => 'base' ) );

		$this->assertFalse( metadata_exists( 'post', $id, '_yoast_wpseo_metadesc' ) );
		$this->assertFalse( metadata_exists( 'post', $id, '_yoast_wpseo_focuskw' ) );
		$this->assertSame( 'Solo il titolo', get_post_meta( $id, '_yoast_wpseo_title', true ) );
	}

	// --- nuova base ---------------------------------------------------------------

	public function test_usare_l_attuale_come_base_la_rende_una_base_dell_utente(): void {
		$id = $this->prodotto( $this->valori( 'Yoast' ) );
		$this->scrivi( $id, $this->valori( 'AI 1' ) );

		$esiti = $this->richiesta( 'POST', '/seo/base', array( 'ids' => array( $id ) ) )->get_data();

		$this->assertSame( array( $id => 'impostata' ), $esiti );
		$snapshot = $this->snapshot( $id );
		$this->assertSame( 'Titolo AI 1', $snapshot['base']['titolo_seo'] );
		$this->assertSame( 'utente', $snapshot['base']['tipo'] );
		$this->assertSame( 'Titolo Yoast', $snapshot['precedente']['titolo_seo'], 'il precedente non cambia' );
		$this->assertSame( $this->valori( 'AI 1' ), $this->seo_vivo( $id ), 'i valori vivi non cambiano' );

		$esiti = $this->richiesta( 'POST', '/seo/ripristina', array( 'ids' => array( $id ), 'versione' => 'base' ) )->get_data();
		$this->assertSame( array( $id => 'gia_in_uso' ), $esiti );
	}

	public function test_la_nuova_base_ha_esiti_per_i_prodotti_senza_versioni_o_inesistenti(): void {
		$mai_toccato = $this->prodotto();

		$esiti = $this->richiesta( 'POST', '/seo/base', array( 'ids' => array( $mai_toccato, 999999 ) ) )->get_data();

		$this->assertSame(
			array(
				$mai_toccato => 'nessuno_snapshot',
				999999       => 'non_trovato',
			),
			$esiti
		);
	}
}
