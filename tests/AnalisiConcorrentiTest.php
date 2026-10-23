<?php
/**
 * Test della logica pura delle analisi dei concorrenti.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\AnalisiConcorrenti;

/**
 * Nessun bootstrap WordPress necessario: AnalisiConcorrenti e' logica pura.
 */
class AnalisiConcorrentiTest extends TestCase {

	/**
	 * Un riepilogo come lo manda il server MCP.
	 *
	 * @param array $override Chiavi da sovrascrivere rispetto alla base.
	 */
	private function riepilogo( array $override = array() ): array {
		$base = array(
			'sito'      => 'Euronics.it',
			'url'       => 'https://www.euronics.it/telefonia/smartphone-e-cellulari/',
			'perimetro' => '/telefonia/smartphone-e-cellulari',
			'data'      => '2026-10-23',
			'metodo'    => 'http',
			'pagine'    => 14,
			'punteggio' => 8,
			'aree'      => array(
				'titoli'           => array(
					'punteggio' => 8,
					'criteri'   => array(
						array(
							'criterio' => 'lunghezza adatta',
							'valore'   => 0.93,
							'evidenza' => 'titoli fra 30 e 60 caratteri: 13 su 14',
							'peso'     => 2,
						),
					),
				),
				'meta_description' => array(
					'punteggio' => 6,
					'criteri'   => array(),
					'tetti'     => array(
						array(
							'regola'  => 'H3 senza H2',
							'misura'  => '11 su 11',
							'scatta'  => true,
							'effetto' => 'abbassa il voto da 7 a 6',
						),
						array(
							'regola' => 'ALT',
							'scatta' => false,
						),
					),
				),
			),
		);

		return array_merge( $base, $override );
	}

	/**
	 * Il sito si porta in minuscolo e solo le aree con punteggio restano.
	 */
	public function test_normalizza_riepilogo_valido(): void {
		$analisi = AnalisiConcorrenti::normalizza( $this->riepilogo() );
		$this->assertSame( 'euronics.it', $analisi['sito'] );
		$this->assertSame( 8, $analisi['punteggio'] );
		$this->assertSame( 'https://www.euronics.it/telefonia/smartphone-e-cellulari/', $analisi['url'] );
		$this->assertSame( array( 'titoli', 'meta_description' ), array_keys( $analisi['aree'] ) );
		$this->assertFalse( $analisi['proprio'] );
		$this->assertSame( 0.93, $analisi['aree']['titoli']['criteri'][0]['valore'] );
	}

	/**
	 * Dei tetti si conservano solo quelli che scattano.
	 */
	public function test_conserva_solo_i_tetti_che_scattano(): void {
		$analisi = AnalisiConcorrenti::normalizza( $this->riepilogo() );
		$tetti   = $analisi['aree']['meta_description']['tetti'];
		$this->assertCount( 1, $tetti );
		$this->assertSame( 'H3 senza H2', $tetti[0]['regola'] );
	}

	/**
	 * Senza sito o senza aree misurabili il riepilogo si scarta.
	 */
	public function test_scarta_riepiloghi_inutilizzabili(): void {
		$this->assertNull( AnalisiConcorrenti::normalizza( $this->riepilogo( array( 'sito' => '  ' ) ) ) );
		$this->assertNull( AnalisiConcorrenti::normalizza( $this->riepilogo( array( 'aree' => array() ) ) ) );
		$this->assertNull(
			AnalisiConcorrenti::normalizza(
				$this->riepilogo( array( 'aree' => array( 'titoli' => array( 'punteggio' => null ) ) ) )
			)
		);
	}

	/**
	 * I punteggi fuori scala si riportano a 0-9, il totale puo' mancare.
	 */
	public function test_punteggi_limitati_a_0_9_e_totale_opzionale(): void {
		$dati                                     = $this->riepilogo( array( 'punteggio' => null ) );
		$dati['aree']['titoli']['punteggio']      = 14;
		$analisi                                  = AnalisiConcorrenti::normalizza( $dati );
		$this->assertSame( 9, $analisi['aree']['titoli']['punteggio'] );
		$this->assertNull( $analisi['punteggio'] );
	}

	/**
	 * I tag HTML nei testi liberi vengono tolti e la lunghezza e' limitata.
	 */
	public function test_ripulisce_i_testi(): void {
		$dati = $this->riepilogo( array( 'sito' => '<b>x.it</b>' ) );
		$dati['aree']['titoli']['criteri'][0]['evidenza'] = '<script>alert(1)</script>' . str_repeat( 'a', 500 );
		$analisi = AnalisiConcorrenti::normalizza( $dati );
		$this->assertSame( 'x.it', $analisi['sito'] );
		$evidenza = $analisi['aree']['titoli']['criteri'][0]['evidenza'];
		$this->assertStringNotContainsString( '<', $evidenza );
		$this->assertSame( 300, strlen( $evidenza ) );
	}

	/**
	 * Una nuova analisi dello stesso sito manda la precedente nello storico.
	 */
	public function test_aggiungi_conserva_lo_storico(): void {
		$archivio = AnalisiConcorrenti::aggiungi( array(), AnalisiConcorrenti::normalizza( $this->riepilogo() ), '2026-10-23T10:00:00+00:00' );
		$this->assertSame( array(), $archivio['euronics.it']['storico'] );

		$nuova    = AnalisiConcorrenti::normalizza( $this->riepilogo( array( 'punteggio' => 6 ) ) );
		$archivio = AnalisiConcorrenti::aggiungi( $archivio, $nuova, '2026-10-24T10:00:00+00:00' );
		$this->assertSame( 6, $archivio['euronics.it']['ultima']['punteggio'] );
		$this->assertCount( 1, $archivio['euronics.it']['storico'] );
		$this->assertSame( 8, $archivio['euronics.it']['storico'][0]['punteggio'] );
		$this->assertSame( 8, $archivio['euronics.it']['storico'][0]['aree']['titoli'] );
	}

	/**
	 * Lo storico tiene solo gli ultimi MAX_STORICO punteggi.
	 */
	public function test_storico_limitato(): void {
		$archivio = array();
		for ( $i = 0; $i < AnalisiConcorrenti::MAX_STORICO + 5; $i++ ) {
			$analisi  = AnalisiConcorrenti::normalizza( $this->riepilogo( array( 'punteggio' => $i % 10 ) ) );
			$archivio = AnalisiConcorrenti::aggiungi( $archivio, $analisi, sprintf( '2026-10-%02dT10:00:00+00:00', $i + 1 ) );
		}
		$this->assertCount( AnalisiConcorrenti::MAX_STORICO, $archivio['euronics.it']['storico'] );
	}

	/**
	 * L'elenco mette per primo il sito dell'utente, poi i piu' recenti.
	 */
	public function test_elenco_proprio_per_primo_poi_i_piu_recenti(): void {
		$archivio = array();
		$altri    = array(
			array( 'a.it', false, '2026-10-20T00:00:00+00:00' ),
			array( 'b.it', false, '2026-10-22T00:00:00+00:00' ),
			array( 'nostro.it', true, '2026-10-01T00:00:00+00:00' ),
		);
		foreach ( $altri as list( $sito, $proprio, $quando ) ) {
			$analisi  = AnalisiConcorrenti::normalizza( $this->riepilogo( array( 'sito' => $sito, 'proprio' => $proprio ) ) );
			$archivio = AnalisiConcorrenti::aggiungi( $archivio, $analisi, $quando );
		}
		$siti = array_map( static fn( array $r ): string => $r['ultima']['sito'], AnalisiConcorrenti::elenco( $archivio ) );
		$this->assertSame( array( 'nostro.it', 'b.it', 'a.it' ), $siti );
	}

	/**
	 * Si puo' togliere un sito dall'archivio.
	 */
	public function test_rimuovi(): void {
		$archivio = AnalisiConcorrenti::aggiungi( array(), AnalisiConcorrenti::normalizza( $this->riepilogo() ), '2026-10-23T10:00:00+00:00' );
		$this->assertSame( array(), AnalisiConcorrenti::rimuovi( $archivio, 'EURONICS.it' ) );
	}
}
