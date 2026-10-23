<?php
/**
 * Logica pura per le analisi SEO dei siti concorrenti (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Il server MCP analizza un sito sul PC dell'utente e ne manda al plugin il
 * riepilogo (punteggi 0-9 per area, con la prova di ogni criterio): qui si
 * convalida quello che arriva e lo si archivia per sito, con un breve storico
 * dei punteggi. Dei testi delle pagine analizzate non si conserva niente.
 */
class AnalisiConcorrenti {

	/**
	 * Le aree della valutazione, nell'ordine in cui si mostrano.
	 */
	const AREE = array( 'titoli', 'meta_description', 'intestazioni', 'dati_strutturati', 'contenuto', 'tecnico' );

	/**
	 * Quante analisi passate di un sito si tengono per lo storico.
	 */
	const MAX_STORICO = 10;

	/**
	 * Lunghezza massima dei testi liberi (evidenze, regole) conservati.
	 */
	const MAX_TESTO = 300;

	/**
	 * Convalida e ripulisce un riepilogo in arrivo. Null se non e' utilizzabile
	 * (manca il sito o non c'e' nessuna area con un punteggio 0-9).
	 *
	 * @param array $dati Riepilogo inviato dal server MCP.
	 */
	public static function normalizza( array $dati ): ?array {
		$sito = self::testo( $dati['sito'] ?? '', 120 );
		if ( '' === $sito ) {
			return null;
		}

		$aree = array();
		foreach ( self::AREE as $nome ) {
			$area = $dati['aree'][ $nome ] ?? null;
			if ( ! is_array( $area ) || ! isset( $area['punteggio'] ) || ! is_numeric( $area['punteggio'] ) ) {
				continue;
			}
			$aree[ $nome ] = array(
				'punteggio' => max( 0, min( 9, (int) $area['punteggio'] ) ),
				'criteri'   => self::criteri( $area['criteri'] ?? array() ),
				'tetti'     => self::tetti( $area['tetti'] ?? array() ),
			);
		}
		if ( empty( $aree ) ) {
			return null;
		}

		$punteggio = $dati['punteggio'] ?? null;

		return array(
			'sito'      => strtolower( $sito ),
			'url'       => self::testo( $dati['url'] ?? '', 300 ),
			'perimetro' => self::testo( $dati['perimetro'] ?? '', 200 ),
			'proprio'   => ! empty( $dati['proprio'] ),
			'data'      => self::testo( $dati['data'] ?? '', 40 ),
			'metodo'    => self::testo( $dati['metodo'] ?? '', 60 ),
			'pagine'    => max( 0, (int) ( $dati['pagine'] ?? 0 ) ),
			'punteggio' => is_numeric( $punteggio ) ? max( 0, min( 9, (int) $punteggio ) ) : null,
			'aree'      => $aree,
		);
	}

	/**
	 * Aggiunge un'analisi all'archivio (sito => ultima analisi + storico): la
	 * precedente dello stesso sito finisce nello storico, di cui si tengono
	 * solo gli ultimi MAX_STORICO punteggi.
	 *
	 * @param array  $archivio Archivio attuale.
	 * @param array  $analisi  Analisi gia' normalizzata.
	 * @param string $quando   Data e ora di ricezione, ISO 8601.
	 */
	public static function aggiungi( array $archivio, array $analisi, string $quando ): array {
		$sito       = $analisi['sito'];
		$storico    = $archivio[ $sito ]['storico'] ?? array();
		$precedente = $archivio[ $sito ]['ultima'] ?? null;
		if ( is_array( $precedente ) ) {
			$storico[] = self::voce_storico( $precedente );
		}

		$analisi['ricevuta_il'] = $quando;
		$archivio[ $sito ]      = array(
			'ultima'  => $analisi,
			'storico' => array_slice( $storico, -self::MAX_STORICO ),
		);
		return $archivio;
	}

	/**
	 * Toglie un sito dall'archivio.
	 *
	 * @param array  $archivio Archivio attuale.
	 * @param string $sito     Sito da togliere.
	 */
	public static function rimuovi( array $archivio, string $sito ): array {
		unset( $archivio[ strtolower( $sito ) ] );
		return $archivio;
	}

	/**
	 * Elenco per la tabella: il sito dell'utente per primo, poi i concorrenti
	 * dal piu' recente.
	 *
	 * @param array $archivio Archivio attuale.
	 */
	public static function elenco( array $archivio ): array {
		$righe = array_values( $archivio );
		usort(
			$righe,
			static function ( array $a, array $b ): int {
				if ( ! empty( $a['ultima']['proprio'] ) !== ! empty( $b['ultima']['proprio'] ) ) {
					return ! empty( $a['ultima']['proprio'] ) ? -1 : 1;
				}
				return strcmp( (string) $b['ultima']['ricevuta_il'], (string) $a['ultima']['ricevuta_il'] );
			}
		);
		return $righe;
	}

	/**
	 * Punteggi di un'analisi, per lo storico.
	 *
	 * @param array $analisi Analisi normalizzata.
	 */
	private static function voce_storico( array $analisi ): array {
		return array(
			'data'      => $analisi['ricevuta_il'] ?? $analisi['data'],
			'punteggio' => $analisi['punteggio'],
			'aree'      => array_map( static fn( array $a ): int => $a['punteggio'], $analisi['aree'] ),
		);
	}

	/**
	 * Criteri di un'area, ripuliti.
	 *
	 * @param mixed $criteri Valore in arrivo.
	 */
	private static function criteri( $criteri ): array {
		$risultato = array();
		foreach ( is_array( $criteri ) ? array_slice( $criteri, 0, 20 ) : array() as $criterio ) {
			if ( ! is_array( $criterio ) ) {
				continue;
			}
			$risultato[] = array(
				'criterio' => self::testo( $criterio['criterio'] ?? '', self::MAX_TESTO ),
				'valore'   => max( 0.0, min( 1.0, (float) ( $criterio['valore'] ?? 0 ) ) ),
				'evidenza' => self::testo( $criterio['evidenza'] ?? '', self::MAX_TESTO ),
				'peso'     => max( 0.0, (float) ( $criterio['peso'] ?? 1 ) ),
			);
		}
		return $risultato;
	}

	/**
	 * Tetti (limiti al voto di un'area) che scattano, ripuliti.
	 *
	 * @param mixed $tetti Valore in arrivo.
	 */
	private static function tetti( $tetti ): array {
		$risultato = array();
		foreach ( is_array( $tetti ) ? array_slice( $tetti, 0, 10 ) : array() as $tetto ) {
			if ( ! is_array( $tetto ) || empty( $tetto['scatta'] ) ) {
				continue;
			}
			$risultato[] = array(
				'regola'  => self::testo( $tetto['regola'] ?? '', self::MAX_TESTO ),
				'misura'  => self::testo( $tetto['misura'] ?? '', self::MAX_TESTO ),
				'effetto' => self::testo( $tetto['effetto'] ?? '', self::MAX_TESTO ),
			);
		}
		return $risultato;
	}

	/**
	 * Una stringa senza tag, tagliata alla lunghezza massima.
	 *
	 * @param mixed $valore    Valore in arrivo.
	 * @param int   $lunghezza Lunghezza massima in caratteri.
	 */
	private static function testo( $valore, int $lunghezza ): string {
		$testo = trim( (string) preg_replace( '/<[^>]*>/', '', (string) $valore ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $testo, 0, $lunghezza ) : substr( $testo, 0, $lunghezza );
	}
}
