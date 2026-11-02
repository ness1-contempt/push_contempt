<?php
/**
 * Giudizio su una bozza prima di pubblicarla (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Due livelli. I `bloccanti` impediscono la pubblicazione da qui (il prodotto
 * non e' in bozza, non ha prezzo, ha varianti sbagliate): si correggono in
 * wp-admin. Gli `avvisi` si pubblicano comunque ma l'utente li vede prima
 * (niente immagine, campi SEO vuoti, nessuna categoria scelta).
 *
 * Ingresso: `stato`, `titolo`, `prezzo_min` (null se non c'e' un prezzo),
 * `immagine_evidenza` (bool), `titolo_seo`, `meta_description`, `parola_chiave`,
 * `solo_categoria_predefinita` (bool), `errori_varianti` (messaggi degli errori
 * di AnalisiVarianti, vuoto se non e' un variabile).
 */
class ValutaBozza {

	/**
	 * Giudica la bozza.
	 *
	 * @param array $p Dati del prodotto (vedi la descrizione della classe).
	 *
	 * @return array{bloccanti: string[], avvisi: string[]}
	 */
	public static function valuta( array $p ): array {
		$bloccanti = array();
		$avvisi    = array();

		if ( 'draft' !== $p['stato'] ) {
			$bloccanti[] = 'Non e\' in bozza (stato: ' . $p['stato'] . '): si pubblicano solo le bozze.';
		}
		if ( '' === trim( (string) $p['titolo'] ) ) {
			$bloccanti[] = 'Il titolo e\' vuoto.';
		}
		if ( null === $p['prezzo_min'] || '' === trim( (string) $p['prezzo_min'] ) ) {
			$bloccanti[] = 'Nessun prezzo: pubblicato non si potrebbe acquistare.';
		}
		foreach ( $p['errori_varianti'] as $messaggio ) {
			$bloccanti[] = 'Varianti: ' . $messaggio;
		}

		if ( ! $p['immagine_evidenza'] ) {
			$avvisi[] = 'Nessuna immagine in evidenza.';
		}
		foreach (
			array(
				'titolo_seo'       => 'titolo SEO',
				'meta_description' => 'meta description',
				'parola_chiave'    => 'parola chiave focus',
			) as $campo => $nome
		) {
			if ( '' === trim( (string) $p[ $campo ] ) ) {
				$avvisi[] = ucfirst( $nome ) . ' mancante.';
			}
		}
		if ( $p['solo_categoria_predefinita'] ) {
			$avvisi[] = 'Solo nella categoria predefinita («Senza categoria»).';
		}

		return array(
			'bloccanti' => $bloccanti,
			'avvisi'    => $avvisi,
		);
	}
}
