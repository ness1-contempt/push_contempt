<?php
/**
 * Codice di conferma per le azioni che cambiano il sito in modo visibile (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Pubblicare un prodotto o spostare una categoria si fa in due tempi: l'anteprima
 * dice cosa succederebbe e restituisce un codice; l'azione vera si accetta solo con
 * quel codice. Il codice e' legato allo stato esatto che e' stato mostrato (quali
 * prodotti, in che condizioni): se nel frattempo qualcosa cambia non corrisponde
 * piu', e l'azione si rifiuta. Cosi' si agisce solo su cio' che l'utente ha visto.
 */
class ConfermaAzione {

	/**
	 * Il codice per un'azione e lo stato che l'anteprima ha mostrato.
	 *
	 * @param string   $azione  Nome dell'azione ("pubblica", "sposta_categoria").
	 * @param string[] $stato   Elementi che descrivono lo stato (verranno ordinati).
	 * @param string   $segreto Chiave del sito (es. wp_salt).
	 */
	public static function codice( string $azione, array $stato, string $segreto ): string {
		$stato = array_map( 'strval', $stato );
		sort( $stato, SORT_STRING );
		return substr( hash_hmac( 'sha256', $azione . "\x1f" . implode( "\x1e", $stato ), $segreto ), 0, 24 );
	}

	/**
	 * Se il codice dato e' quello dell'anteprima (confronto a tempo costante).
	 *
	 * @param string   $dato    Codice ricevuto con la richiesta.
	 * @param string   $azione  Nome dell'azione.
	 * @param string[] $stato   Stato attuale.
	 * @param string   $segreto Chiave del sito.
	 */
	public static function valido( string $dato, string $azione, array $stato, string $segreto ): bool {
		return '' !== $dato && hash_equals( self::codice( $azione, $stato, $segreto ), $dato );
	}
}
