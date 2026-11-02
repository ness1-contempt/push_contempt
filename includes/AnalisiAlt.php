<?php
/**
 * Giudizio sul testo ALT di un'immagine (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Due problemi, quelli che Yoast segnala sulle immagini di un prodotto:
 * l'ALT manca, oppure c'e' ma non contiene la parola chiave focus (se il
 * prodotto ne ha una). Il confronto ignora maiuscole e spazi in eccesso.
 */
class AnalisiAlt {

	const MANCANTE            = 'mancante';
	const SENZA_PAROLA_CHIAVE = 'senza_parola_chiave';

	/**
	 * Il problema di un ALT, o null se va bene.
	 *
	 * @param string $alt           Testo ALT dell'immagine (vuoto se manca).
	 * @param string $parola_chiave Parola chiave focus del prodotto (vuota se non c'e').
	 */
	public static function problema( string $alt, string $parola_chiave ): ?string {
		$alt = self::normalizza( $alt );
		if ( '' === $alt ) {
			return self::MANCANTE;
		}
		$parola_chiave = self::normalizza( $parola_chiave );
		if ( '' !== $parola_chiave && false === mb_strpos( $alt, $parola_chiave ) ) {
			return self::SENZA_PAROLA_CHIAVE;
		}
		return null;
	}

	/**
	 * Quante immagini hanno ciascun problema.
	 *
	 * @param array $problemi Elenco dei problemi (null per le immagini a posto).
	 *
	 * @return array{mancanti: int, senza_parola_chiave: int}
	 */
	public static function conta( array $problemi ): array {
		$conteggio = array_count_values( array_filter( $problemi ) );
		return array(
			'mancanti'            => $conteggio[ self::MANCANTE ] ?? 0,
			'senza_parola_chiave' => $conteggio[ self::SENZA_PAROLA_CHIAVE ] ?? 0,
		);
	}

	/**
	 * Minuscolo e spazi singoli, per confrontare testi.
	 *
	 * @param string $testo Testo da normalizzare.
	 */
	private static function normalizza( string $testo ): string {
		return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $testo ) ) );
	}
}
