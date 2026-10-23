<?php
/**
 * Logica pura dello snapshot dei campi SEO Yoast (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Prima che un'operazione sovrascriva titolo SEO, meta description e parola
 * chiave focus di un prodotto, il plugin conserva i valori che c'erano: cosi'
 * l'utente puo' tornare indietro. Si conserva solo il PRIMO stato originale
 * (quello prima di qualunque intervento): riscritture successive non lo
 * sostituiscono, altrimenti il ripristino riporterebbe a una versione
 * intermedia dell'AI invece che a quella di Yoast.
 */
class SeoSnapshot {

	/**
	 * Chiave del meta del prodotto in cui si conserva lo snapshot.
	 */
	const META_KEY = '_pc_seo_originale';

	/**
	 * I campi SEO coperti (stessi nomi usati dalla REST API del plugin).
	 */
	const CAMPI = array( 'titolo_seo', 'meta_description', 'parola_chiave_focus' );

	/**
	 * Se va salvato uno snapshot: solo se non ce n'e' gia' uno e la scrittura
	 * cambia davvero almeno un campo.
	 *
	 * @param array|null $esistente Snapshot gia' salvato, se c'e'.
	 * @param array      $attuali   Valori SEO in questo momento (campo => stringa).
	 * @param array      $nuovi     Valori che si stanno per scrivere (campo => stringa).
	 */
	public static function da_salvare( ?array $esistente, array $attuali, array $nuovi ): bool {
		if ( ! empty( $esistente ) ) {
			return false;
		}
		foreach ( self::CAMPI as $campo ) {
			if ( (string) ( $attuali[ $campo ] ?? '' ) !== (string) ( $nuovi[ $campo ] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Lo snapshot da conservare.
	 *
	 * @param array  $attuali Valori SEO prima della scrittura (campo => stringa).
	 * @param string $origine Chi sta scrivendo (es. "manuale", "concorrente tomatosmartphone.it").
	 * @param string $quando  Data e ora in formato ISO 8601.
	 */
	public static function crea( array $attuali, string $origine, string $quando ): array {
		$snapshot = array();
		foreach ( self::CAMPI as $campo ) {
			$snapshot[ $campo ] = (string) ( $attuali[ $campo ] ?? '' );
		}
		$snapshot['origine']    = $origine;
		$snapshot['salvato_il'] = $quando;
		return $snapshot;
	}

	/**
	 * I valori da rimettere nei campi SEO partendo da uno snapshot. Un campo
	 * che originariamente era vuoto va rimesso vuoto (chi li applica lo cancella).
	 *
	 * @param array $snapshot Snapshot salvato.
	 */
	public static function valori_da_ripristinare( array $snapshot ): array {
		$valori = array();
		foreach ( self::CAMPI as $campo ) {
			$valori[ $campo ] = (string) ( $snapshot[ $campo ] ?? '' );
		}
		return $valori;
	}

	/**
	 * Snapshot ripulito per la risposta REST (null se non c'e').
	 *
	 * @param mixed $snapshot Valore letto dal meta (array, stringa vuota o altro).
	 */
	public static function per_risposta( $snapshot ): ?array {
		if ( ! is_array( $snapshot ) || empty( $snapshot ) ) {
			return null;
		}
		return array_merge(
			self::valori_da_ripristinare( $snapshot ),
			array(
				'origine'    => (string) ( $snapshot['origine'] ?? '' ),
				'salvato_il' => (string) ( $snapshot['salvato_il'] ?? '' ),
			)
		);
	}
}
