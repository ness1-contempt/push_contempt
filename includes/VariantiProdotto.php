<?php
/**
 * Attributi e combinazioni di un prodotto variabile da creare (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Decide se gli attributi proposti per un prodotto variabile sono validi e,
 * se lo sono, quali varianti nascono: tutte le combinazioni dei valori, nell'ordine
 * degli attributi. E' il controllo che sta fra "l'assistente ha ricavato degli
 * attributi" e "il plugin crea dei prodotti": qualunque errore (valori doppi,
 * attributi vuoti, troppe combinazioni) si ferma qui, prima di scrivere
 * qualsiasi cosa.
 */
class VariantiProdotto {

	/**
	 * Quante varianti al massimo: un hosting condiviso ha tempi di esecuzione
	 * brevi, e oltre questo numero un prodotto diventa ingestibile da wp-admin.
	 */
	const MAX_VARIANTI = 50;

	/**
	 * Quanti attributi al massimo (taglia, colore, memoria... oltre non ha senso).
	 */
	const MAX_ATTRIBUTI = 4;

	/**
	 * Lunghezza massima del nome di un attributo o di un valore.
	 */
	const MAX_LUNGHEZZA = 100;

	/**
	 * Attributi normalizzati, combinazioni ed eventuali errori.
	 *
	 * @param mixed $attributi Elenco di `{ nome, valori[] }` come arriva dalla richiesta.
	 *
	 * @return array{attributi: array, combinazioni: array, errori: string[]}
	 *         `combinazioni`: per ogni variante, nome attributo => valore. Se
	 *         `errori` non e' vuoto, `attributi` e `combinazioni` non vanno usati.
	 */
	public static function analizza( $attributi ): array {
		$errori = array();
		$esito  = array(
			'attributi'    => array(),
			'combinazioni' => array(),
			'errori'       => array(),
		);

		if ( ! is_array( $attributi ) || array() === $attributi ) {
			$esito['errori'][] = 'Un prodotto variabile ha bisogno di almeno un attributo (per esempio Colore con i suoi valori).';
			return $esito;
		}
		if ( count( $attributi ) > self::MAX_ATTRIBUTI ) {
			$errori[] = 'Troppi attributi (' . count( $attributi ) . '): al massimo ' . self::MAX_ATTRIBUTI . '.';
		}

		$visti_nomi = array();
		foreach ( array_values( $attributi ) as $posizione => $attributo ) {
			$nome = self::pulisci( is_array( $attributo ) ? ( $attributo['nome'] ?? '' ) : '' );
			if ( '' === $nome ) {
				$errori[] = 'L\'attributo n. ' . ( $posizione + 1 ) . ' non ha un nome.';
				continue;
			}
			$chiave = mb_strtolower( $nome );
			if ( isset( $visti_nomi[ $chiave ] ) ) {
				$errori[] = "L'attributo «{$nome}» compare piu' di una volta.";
				continue;
			}
			$visti_nomi[ $chiave ] = true;
			if ( mb_strlen( $nome ) > self::MAX_LUNGHEZZA ) {
				$errori[] = "Il nome dell'attributo «{$nome}» e' troppo lungo.";
			}

			$valori = self::valori( $nome, $attributo['valori'] ?? null, $errori );
			if ( array() !== $valori ) {
				$esito['attributi'][] = array(
					'nome'   => $nome,
					'valori' => $valori,
				);
			}
		}

		if ( array() !== $errori ) {
			$esito['attributi'] = array();
			$esito['errori']    = $errori;
			return $esito;
		}

		$numero = 1;
		foreach ( $esito['attributi'] as $attributo ) {
			$numero *= count( $attributo['valori'] );
		}
		if ( $numero > self::MAX_VARIANTI ) {
			$esito['attributi'] = array();
			$esito['errori']    = array( "Le combinazioni sono {$numero}: al massimo " . self::MAX_VARIANTI . ' varianti. Riduci i valori o dividi in piu\' prodotti.' );
			return $esito;
		}

		$esito['combinazioni'] = self::combina( $esito['attributi'] );
		return $esito;
	}

	/**
	 * I valori di un attributo, ripuliti e senza doppioni (ignorando le maiuscole).
	 *
	 * @param string $nome   Nome dell'attributo (per i messaggi).
	 * @param mixed  $valori Valori proposti.
	 * @param array  $errori Errori trovati, aggiornati per riferimento.
	 */
	private static function valori( string $nome, $valori, array &$errori ): array {
		if ( ! is_array( $valori ) || array() === $valori ) {
			$errori[] = "L'attributo «{$nome}» non ha nessun valore.";
			return array();
		}
		$puliti = array();
		$visti  = array();
		foreach ( $valori as $valore ) {
			$valore = self::pulisci( is_scalar( $valore ) ? (string) $valore : '' );
			if ( '' === $valore ) {
				$errori[] = "L'attributo «{$nome}» ha un valore vuoto.";
				continue;
			}
			if ( mb_strlen( $valore ) > self::MAX_LUNGHEZZA ) {
				$errori[] = "Il valore «{$valore}» dell'attributo «{$nome}» e' troppo lungo.";
				continue;
			}
			$chiave = mb_strtolower( $valore );
			if ( isset( $visti[ $chiave ] ) ) {
				$errori[] = "Il valore «{$valore}» dell'attributo «{$nome}» compare piu' di una volta.";
				continue;
			}
			$visti[ $chiave ] = true;
			$puliti[]         = $valore;
		}
		return $puliti;
	}

	/**
	 * Tutte le combinazioni dei valori, il primo attributo cambia piu' lentamente.
	 *
	 * @param array $attributi Attributi gia' validati: elenco di `{ nome, valori[] }`.
	 */
	private static function combina( array $attributi ): array {
		$combinazioni = array( array() );
		foreach ( $attributi as $attributo ) {
			$nuove = array();
			foreach ( $combinazioni as $base ) {
				foreach ( $attributo['valori'] as $valore ) {
					$nuove[] = $base + array( $attributo['nome'] => $valore );
				}
			}
			$combinazioni = $nuove;
		}
		return $combinazioni;
	}

	/**
	 * Testo senza spazi in testa/coda e con gli spazi interni ridotti a uno.
	 *
	 * @param string $testo Testo da pulire.
	 */
	private static function pulisci( string $testo ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', $testo ) );
	}
}
