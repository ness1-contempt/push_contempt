<?php
/**
 * Brief del blog: cosa deve raccontare e a chi (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Il brief e' l'insieme di indicazioni che il negozio da' all'assistente AI prima di
 * scrivere gli articoli: argomento, pubblico, tono, lingua, lunghezza, quanti articoli
 * per volta, quali categorie di prodotti far comparire, note libere e se applicare le
 * regole AEO/GEO (attivo di base: il testo delle regole sta nel server MCP). Non dipende dal settore: vale per qualunque negozio.
 *
 * Qui c'e' solo la pulizia dei valori; leggere e salvare il brief e' compito del
 * controller REST.
 */
class BriefBlog {

	const OPZIONE = 'pc_blog_brief';

	const LUNGHEZZA_MIN     = 300;
	const LUNGHEZZA_MAX     = 3000;
	const LUNGHEZZA_DEFAULT = 1200;

	const ARTICOLI_MIN     = 1;
	const ARTICOLI_MAX     = 10;
	const ARTICOLI_DEFAULT = 3;

	/**
	 * Il brief di un negozio che non ha ancora scritto niente.
	 */
	public static function predefinito(): array {
		return array(
			'argomento'          => '',
			'pubblico'           => '',
			'tono'               => '',
			'lingua'             => 'it',
			'lunghezza_parole'   => self::LUNGHEZZA_DEFAULT,
			'n_articoli'         => self::ARTICOLI_DEFAULT,
			'categorie_prodotti' => array(),
			'note'               => '',
			'ottimizza_aeo_geo'  => true,
		);
	}

	/**
	 * Un brief valido a partire da dati qualsiasi: i campi sconosciuti spariscono, i testi
	 * si accorciano e si ripuliscono dall'HTML, i numeri rientrano nei limiti e quello che
	 * manca prende il valore predefinito.
	 *
	 * @param mixed $dati Dati grezzi (per esempio il corpo di una richiesta).
	 */
	public static function pulisci( $dati ): array {
		$dati  = is_array( $dati ) ? $dati : array();
		$brief = self::predefinito();
		$testi = array(
			'argomento' => 300,
			'pubblico'  => 300,
			'tono'      => 200,
		);
		foreach ( $testi as $campo => $massimo ) {
			if ( isset( $dati[ $campo ] ) ) {
				$brief[ $campo ] = self::testo( $dati[ $campo ], $massimo, false );
			}
		}
		if ( isset( $dati['note'] ) ) {
			$brief['note'] = self::testo( $dati['note'], 2000, true );
		}
		if ( isset( $dati['lingua'] ) && is_string( $dati['lingua'] ) && 1 === preg_match( '/^[a-z]{2}(-[A-Z]{2})?$/', trim( $dati['lingua'] ) ) ) {
			$brief['lingua'] = trim( $dati['lingua'] );
		}
		if ( isset( $dati['lunghezza_parole'] ) ) {
			$brief['lunghezza_parole'] = self::intero( $dati['lunghezza_parole'], self::LUNGHEZZA_MIN, self::LUNGHEZZA_MAX, self::LUNGHEZZA_DEFAULT );
		}
		if ( isset( $dati['n_articoli'] ) ) {
			$brief['n_articoli'] = self::intero( $dati['n_articoli'], self::ARTICOLI_MIN, self::ARTICOLI_MAX, self::ARTICOLI_DEFAULT );
		}
		if ( isset( $dati['ottimizza_aeo_geo'] ) ) {
			$scelta = filter_var( $dati['ottimizza_aeo_geo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			if ( null !== $scelta ) {
				$brief['ottimizza_aeo_geo'] = $scelta;
			}
		}
		if ( isset( $dati['categorie_prodotti'] ) && is_array( $dati['categorie_prodotti'] ) ) {
			$ids = array();
			foreach ( $dati['categorie_prodotti'] as $id ) {
				if ( is_numeric( $id ) && (int) $id > 0 ) {
					$ids[ (int) $id ] = (int) $id;
				}
			}
			$brief['categorie_prodotti'] = array_values( $ids );
		}

		return $brief;
	}

	/**
	 * Vero se il brief ha almeno l'argomento: senza non c'e' niente da scrivere.
	 *
	 * @param array $brief Brief gia' pulito.
	 */
	public static function e_compilato( array $brief ): bool {
		return '' !== trim( (string) ( $brief['argomento'] ?? '' ) );
	}

	/**
	 * Un testo senza HTML e con gli spazi in ordine, tagliato a $massimo caratteri.
	 *
	 * @param mixed $valore    Valore grezzo.
	 * @param int   $massimo   Lunghezza massima in caratteri.
	 * @param bool  $a_capo_ok Se true le righe vuote e gli a capo restano.
	 */
	private static function testo( $valore, int $massimo, bool $a_capo_ok ): string {
		if ( ! is_string( $valore ) ) {
			return '';
		}
		$testo = trim( strip_tags( $valore ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- classe pura, senza WordPress: wp_strip_all_tags() qui non esiste.
		if ( $a_capo_ok ) {
			$testo = (string) preg_replace( "/[ \t]+/u", ' ', str_replace( "\r", '', $testo ) );
			$testo = (string) preg_replace( "/\n{3,}/", "\n\n", $testo );
		} else {
			$testo = (string) preg_replace( '/\s+/u', ' ', $testo );
		}

		return trim( mb_substr( $testo, 0, $massimo ) );
	}

	/**
	 * Un intero dentro i limiti; se il valore non e' un numero vale il predefinito.
	 *
	 * @param mixed $valore      Valore grezzo.
	 * @param int   $minimo      Minimo consentito.
	 * @param int   $massimo     Massimo consentito.
	 * @param int   $predefinito Valore se $valore non e' numerico.
	 */
	private static function intero( $valore, int $minimo, int $massimo, int $predefinito ): int {
		if ( ! is_numeric( $valore ) ) {
			return $predefinito;
		}

		return max( $minimo, min( $massimo, (int) $valore ) );
	}
}
