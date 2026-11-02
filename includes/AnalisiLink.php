<?php
/**
 * Elenco dei link di un testo HTML, divisi in interni, esterni e altri (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Serve a capire se una descrizione ha link in uscita (la verifica che Yoast
 * segnala piu' spesso come mancante) e dove puntano quelli interni.
 *
 * - interno: indirizzo relativo, oppure assoluto con lo stesso dominio del sito
 *   (senza contare un "www." iniziale);
 * - esterno: indirizzo http(s) o "//" verso un altro dominio (anche un
 *   sottodominio diverso);
 * - altri: ancore sulla stessa pagina (#...), mailto:, tel: e simili, che non
 *   sono ne' link interni ne' in uscita.
 */
class AnalisiLink {

	/**
	 * Link del testo, nell'ordine in cui compaiono.
	 *
	 * Ogni link: `url` (come scritto), `testo` (il testo cliccabile) e
	 * `nofollow` (true se `rel` contiene nofollow). Un link scritto due volte
	 * compare due volte.
	 *
	 * @param string $html       Testo HTML (per esempio la descrizione di un prodotto).
	 * @param string $dominio    Dominio del sito (per esempio "esempio.it").
	 *
	 * @return array{interni: array, esterni: array, altri: array}
	 */
	public static function analizza( string $html, string $dominio ): array {
		$risultato = array(
			'interni' => array(),
			'esterni' => array(),
			'altri'   => array(),
		);
		if ( '' === trim( $html ) ) {
			return $risultato;
		}

		$usa_errori_interni = libxml_use_internal_errors( true );
		$documento          = new \DOMDocument();
		$documento->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $usa_errori_interni );

		$dominio_sito = self::normalizza_dominio( $dominio );
		foreach ( $documento->getElementsByTagName( 'a' ) as $ancora ) {
			$url = trim( $ancora->getAttribute( 'href' ) );
			if ( '' === $url ) {
				continue; // Un <a> senza destinazione (per esempio un segnaposto) non e' un link.
			}
			$testo = $ancora->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- proprieta' di DOMNode.
			$link  = array(
				'url'      => $url,
				'testo'    => trim( (string) preg_replace( '/\s+/u', ' ', $testo ) ),
				'nofollow' => in_array( 'nofollow', preg_split( '/\s+/', strtolower( $ancora->getAttribute( 'rel' ) ) ), true ),
			);
			$risultato[ self::tipo( $url, $dominio_sito ) ][] = $link;
		}

		return $risultato;
	}

	/**
	 * Dove porta un indirizzo: "interni", "esterni" o "altri".
	 *
	 * @param string $url          Indirizzo come scritto nel link.
	 * @param string $dominio_sito Dominio del sito, gia' normalizzato.
	 */
	private static function tipo( string $url, string $dominio_sito ): string {
		if ( preg_match( '/^[a-z][a-z0-9+.\-]*:/i', $url ) && ! preg_match( '#^https?://#i', $url ) ) {
			return 'altri'; // Altri schemi: posta, telefono, script.
		}
		if ( '#' === $url[0] ) {
			return 'altri'; // Ancora sulla stessa pagina.
		}
		if ( ! preg_match( '#^(https?:)?//#i', $url ) ) {
			return 'interni'; // Relativo: resta sul sito.
		}
		$host = (string) parse_url( $url, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- classe senza WordPress, provata a parte.
		if ( '' === $host ) {
			return 'altri'; // Indirizzo malformato: non si sa dove porta.
		}
		return self::normalizza_dominio( $host ) === $dominio_sito ? 'interni' : 'esterni';
	}

	/**
	 * Dominio in minuscolo, senza "www." iniziale.
	 *
	 * @param string $dominio Dominio o host.
	 */
	public static function normalizza_dominio( string $dominio ): string {
		$dominio = strtolower( trim( $dominio ) );
		return 0 === strpos( $dominio, 'www.' ) ? substr( $dominio, 4 ) : $dominio;
	}
}
