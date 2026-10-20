<?php
/**
 * Logica pura del controllo salute catalogo (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Segnala prodotti pubblicati ma incompleti: nessuna immagine in evidenza,
 * nessuna categoria, prezzo mancante/zero/non valido (solo prodotti
 * semplici), descrizione troppo corta.
 */
class CatalogHealth {

	/**
	 * Problemi trovati per un prodotto, a partire dai suoi dati grezzi.
	 *
	 * @param array $dati               Chiavi: ha_immagine (bool), numero_categorie (int),
	 *                                  variabile (bool), prezzo_regolare (string|null),
	 *                                  descrizione (string|null).
	 * @param int   $soglia_descrizione Lunghezza minima della descrizione (tag HTML esclusi).
	 */
	public static function problemi( array $dati, int $soglia_descrizione ): array {
		$problemi = array();

		if ( empty( $dati['ha_immagine'] ) ) {
			$problemi[] = 'Nessuna immagine in evidenza';
		}

		if ( 0 === (int) $dati['numero_categorie'] ) {
			$problemi[] = 'Nessuna categoria';
		}

		if ( empty( $dati['variabile'] ) && ! self::prezzo_valido( $dati['prezzo_regolare'] ?? null ) ) {
			$problemi[] = 'Prezzo mancante, zero o non valido';
		}

		$testo = trim( (string) preg_replace( '/<[^>]+>/', '', (string) ( $dati['descrizione'] ?? '' ) ) );
		if ( strlen( $testo ) < $soglia_descrizione ) {
			$problemi[] = "Descrizione troppo corta (meno di {$soglia_descrizione} caratteri)";
		}

		return $problemi;
	}

	/**
	 * Un prezzo e' valido se numerico e maggiore di zero.
	 *
	 * @param string|null $prezzo_regolare Valore grezzo da validare.
	 */
	public static function prezzo_valido( ?string $prezzo_regolare ): bool {
		if ( empty( $prezzo_regolare ) ) {
			return false;
		}
		return is_numeric( $prezzo_regolare ) && (float) $prezzo_regolare > 0;
	}
}
