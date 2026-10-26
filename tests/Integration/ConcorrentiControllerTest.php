<?php
/**
 * Test di /concorrenti (GET, POST, DELETE) e dell'intestazione anti-cache delle risposte.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

use PushContempt\Rest\ConcorrentiController;

class ConcorrentiControllerTest extends RestTestCase {

	/**
	 * Un riepilogo come lo manda il server MCP.
	 *
	 * @param string $sito      Nome del sito.
	 * @param int    $punteggio Punteggio complessivo.
	 * @param array  $extra     Campi da aggiungere o sostituire.
	 */
	private function riepilogo( string $sito, int $punteggio = 7, array $extra = array() ): array {
		return array_merge(
			array(
				'sito'      => $sito,
				'url'       => "https://{$sito}/",
				'data'      => '2026-10-26',
				'punteggio' => $punteggio,
				'pagine'    => 11,
				'aree'      => array(
					'titoli'  => array(
						'punteggio' => 8,
						'criteri'   => array(
							array(
								'criterio' => 'lunghezza adatta',
								'valore'   => 0.8,
								'evidenza' => 'titoli fra 30 e 60 caratteri: 8 su 10',
								'peso'     => 2,
							),
						),
					),
					'tecnico' => array( 'punteggio' => 9 ),
				),
			),
			$extra
		);
	}

	/**
	 * Salva un riepilogo (corpo JSON, come fa l'MCP).
	 *
	 * @param array $riepilogo Riepilogo da inviare.
	 */
	private function salva( array $riepilogo ) {
		return $this->richiesta( 'POST', '/concorrenti', $riepilogo, true );
	}

	/**
	 * I nomi dei siti nell'ordine in cui li mostra la tabella.
	 */
	private function siti(): array {
		return array_map(
			static fn( array $riga ): string => $riga['ultima']['sito'],
			$this->richiesta( 'GET', '/concorrenti' )->get_data()
		);
	}

	public function test_chi_non_gestisce_il_negozio_viene_rifiutato(): void {
		$this->accedi_come( 'subscriber' );
		$this->assertSame( 403, $this->richiesta( 'GET', '/concorrenti' )->get_status() );
		$this->assertSame( 403, $this->salva( $this->riepilogo( 'a.it' ) )->get_status() );
		$this->assertSame( 403, $this->richiesta( 'DELETE', '/concorrenti', array( 'sito' => 'a.it' ) )->get_status() );

		$this->accedi_come( '' );
		$this->assertSame( 401, $this->salva( $this->riepilogo( 'a.it' ) )->get_status() );
	}

	public function test_senza_analisi_l_elenco_e_vuoto(): void {
		$this->assertSame( array(), $this->richiesta( 'GET', '/concorrenti' )->get_data() );
	}

	public function test_salva_un_riepilogo_e_lo_rende_leggibile(): void {
		$risposta = $this->salva( $this->riepilogo( 'Euronics.IT' ) );

		$this->assertSame( 200, $risposta->get_status() );
		$this->assertSame(
			array(
				'sito'    => 'euronics.it',
				'salvata' => true,
			),
			$risposta->get_data()
		);
		$ultima = $this->richiesta( 'GET', '/concorrenti' )->get_data()[0]['ultima'];
		$this->assertSame( 'euronics.it', $ultima['sito'] );
		$this->assertSame( 7, $ultima['punteggio'] );
		$this->assertSame( 8, $ultima['aree']['titoli']['punteggio'] );
		$this->assertSame( 'titoli fra 30 e 60 caratteri: 8 su 10', $ultima['aree']['titoli']['criteri'][0]['evidenza'] );
		$this->assertArrayNotHasKey( 'meta_description', $ultima['aree'], 'le aree senza punteggio non si salvano' );
	}

	public function test_un_riepilogo_senza_sito_o_senza_aree_da_400(): void {
		foreach ( array( $this->riepilogo( '' ), $this->riepilogo( 'a.it', 7, array( 'aree' => array() ) ) ) as $riepilogo ) {
			$risposta = $this->salva( $riepilogo );

			$this->assertSame( 400, $risposta->get_status() );
			$this->assertSame( 'push_contempt_analisi_non_valida', $risposta->get_data()['code'] );
		}
		$this->assertSame( array(), $this->siti() );
	}

	public function test_i_testi_del_riepilogo_vengono_ripuliti_dai_tag(): void {
		$this->salva( $this->riepilogo( 'a.it', 7, array( 'url' => 'https://a.it/<script>x</script>' ) ) );

		$this->assertStringNotContainsString( '<', $this->richiesta( 'GET', '/concorrenti' )->get_data()[0]['ultima']['url'] );
	}

	public function test_una_seconda_analisi_dello_stesso_sito_sposta_la_prima_nello_storico(): void {
		$this->salva( $this->riepilogo( 'a.it', 5 ) );
		$this->salva( $this->riepilogo( 'A.it', 8 ) );

		$righe = $this->richiesta( 'GET', '/concorrenti' )->get_data();

		$this->assertCount( 1, $righe );
		$this->assertSame( 8, $righe[0]['ultima']['punteggio'] );
		$this->assertCount( 1, $righe[0]['storico'] );
		$this->assertSame( 5, $righe[0]['storico'][0]['punteggio'] );
	}

	public function test_il_sito_dell_utente_sta_sempre_per_primo(): void {
		$this->salva( $this->riepilogo( 'concorrente.it' ) );
		$this->salva( $this->riepilogo( 'mio.it', 6, array( 'proprio' => true ) ) );
		$this->salva( $this->riepilogo( 'altro.it' ) );

		$siti = $this->siti();

		$this->assertSame( 'mio.it', $siti[0] );
		$this->assertCount( 3, $siti );
	}

	public function test_elimina_un_sito_senza_distinguere_maiuscole(): void {
		$this->salva( $this->riepilogo( 'euronics.it' ) );
		$this->salva( $this->riepilogo( 'tomato.it' ) );

		$risposta = $this->richiesta( 'DELETE', '/concorrenti', array( 'sito' => 'EURONICS.IT' ) );

		$this->assertSame( array( 'eliminata' => true ), $risposta->get_data() );
		$this->assertSame( array( 'tomato.it' ), $this->siti() );
	}

	public function test_eliminare_un_sito_che_non_c_e_lo_dice_senza_toccare_gli_altri(): void {
		$this->salva( $this->riepilogo( 'tomato.it' ) );

		$risposta = $this->richiesta( 'DELETE', '/concorrenti', array( 'sito' => 'non-c-e.it' ) );

		$this->assertSame( array( 'eliminata' => false ), $risposta->get_data() );
		$this->assertSame( array( 'tomato.it' ), $this->siti() );
	}

	public function test_senza_il_parametro_sito_l_eliminazione_da_400(): void {
		$this->assertSame( 400, $this->richiesta( 'DELETE', '/concorrenti' )->get_status() );
	}

	public function test_l_archivio_non_si_carica_a_ogni_pagina(): void {
		global $wpdb;
		$this->salva( $this->riepilogo( 'a.it' ) );

		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", ConcorrentiController::OPZIONE ) );

		$this->assertContains( $autoload, array( 'no', 'off', 'auto-off' ) );
	}

	// --- intestazione anti-cache --------------------------------------------------

	/**
	 * Applica ai dati di una risposta il filtro che la manderebbe al client.
	 *
	 * @param string $rotta Rotta completa.
	 */
	private function dopo_il_filtro( string $rotta ): \WP_REST_Response {
		$richiesta = new \WP_REST_Request( 'GET', $rotta );
		$risposta  = rest_do_request( $richiesta );
		return apply_filters( 'rest_post_dispatch', $risposta, rest_get_server(), $richiesta );
	}

	public function test_le_risposte_del_plugin_non_si_mettono_in_cache(): void {
		$risposta = $this->dopo_il_filtro( self::BASE . '/concorrenti' );

		$this->assertSame( 'no-cache', $risposta->get_headers()['X-LiteSpeed-Cache-Control'] ?? null );
	}

	public function test_le_altre_rotte_non_hanno_l_intestazione(): void {
		$risposta = $this->dopo_il_filtro( '/wp/v2/types' );

		$this->assertArrayNotHasKey( 'X-LiteSpeed-Cache-Control', $risposta->get_headers() );
	}
}
