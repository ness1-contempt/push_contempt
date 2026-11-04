<?php
/**
 * Misure semplici sul testo di un articolo (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Serve a mostrare quanto e' lungo un articolo e a controllare che il testo ricevuto non
 * sia vuoto una volta tolto l'HTML.
 */
class TestoBlog {

	/**
	 * Il testo visibile di un contenuto HTML: senza tag, script e stili, con gli spazi in ordine.
	 *
	 * @param string $html Contenuto HTML (o con i commenti dei blocchi di Gutenberg).
	 */
	public static function testo_visibile( string $html ): string {
		$senza_codice = (string) preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html );
		$con_spazi    = (string) preg_replace( '#<(br|/p|/h[1-6]|/li|/div)\b[^>]*>#i', ' ', $senza_codice );
		$senza_tag    = strip_tags( $con_spazi ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- classe pura, senza WordPress: wp_strip_all_tags() qui non esiste.

		return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( $senza_tag, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	}

	/**
	 * Quante parole ha il testo visibile di un contenuto HTML.
	 *
	 * @param string $html Contenuto HTML.
	 */
	public static function conta_parole( string $html ): int {
		$testo = self::testo_visibile( $html );
		if ( '' === $testo ) {
			return 0;
		}

		$parole = preg_split( '/\s+/u', $testo );

		return false === $parole ? 0 : count( $parole );
	}
}
