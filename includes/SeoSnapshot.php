<?php
/**
 * Logica pura delle versioni dei campi SEO Yoast (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Per ogni prodotto il plugin tiene due versioni dei campi SEO (titolo SEO,
 * meta description, parola chiave focus) oltre a quella attuale, che e' il
 * valore vivo in Yoast e non si copia:
 *
 * - BASE: il punto di partenza, lo stato prima del primo intervento. Non
 *   cambia mentre si lavora; solo l'utente puo' ridefinirla ("usa l'attuale
 *   come base"), e da quel momento non e' piu' l'originale di Yoast.
 * - PRECEDENTE: la versione da cui si viene, cioe' quella che c'era prima
 *   dell'ultima scrittura o dell'ultimo ripristino. Si prende dai valori vivi,
 *   quindi comprende anche eventuali ritocchi a mano fatti in Yoast.
 *
 * Ripristinare la base o il precedente scambia il precedente con la versione
 * che si lascia, cosi' si puo' sempre tornare indietro senza perdere niente.
 * Il meta conserva anche `ultima_scrittura` (cosa ha scritto il plugin e chi):
 * serve a riconoscere una modifica fatta a mano, quando i valori vivi non
 * coincidono piu'.
 */
class SeoSnapshot {

	/**
	 * Chiave del meta del prodotto in cui si conservano le versioni.
	 */
	const META_KEY = '_pc_seo_snapshot';

	/**
	 * I campi SEO coperti (stessi nomi usati dalla REST API del plugin).
	 */
	const CAMPI = array( 'titolo_seo', 'meta_description', 'parola_chiave_focus' );

	/**
	 * Le versioni che si possono ripristinare.
	 */
	const VERSIONI = array( 'base', 'precedente' );

	const ORIGINE_PARTENZA = 'stato iniziale';
	const ORIGINE_MANUALE  = 'modifica manuale';
	const ORIGINE_UTENTE   = 'impostata dall\'utente';

	/**
	 * Una versione: i tre campi, chi l'ha prodotta e quando.
	 *
	 * @param array  $valori  Valori SEO (campo => stringa).
	 * @param string $origine Chi l'ha prodotta (es. "concorrente x.it", "modifica manuale").
	 * @param string $quando  Data e ora in formato ISO 8601 (vuota se non nota).
	 */
	private static function versione( array $valori, string $origine, string $quando ): array {
		$versione = array();
		foreach ( self::CAMPI as $campo ) {
			$versione[ $campo ] = (string) ( $valori[ $campo ] ?? '' );
		}
		$versione['origine']    = $origine;
		$versione['salvato_il'] = $quando;
		return $versione;
	}

	/**
	 * Se due insiemi di valori SEO coincidono (solo i tre campi).
	 *
	 * @param array $a Primo insieme.
	 * @param array $b Secondo insieme.
	 */
	public static function valori_uguali( array $a, array $b ): bool {
		foreach ( self::CAMPI as $campo ) {
			if ( (string) ( $a[ $campo ] ?? '' ) !== (string) ( $b[ $campo ] ?? '' ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Chi ha prodotto i valori vivi: chi ha scritto per ultimo, a meno che da
	 * allora qualcuno li abbia cambiati a mano.
	 *
	 * @param array $snapshot Versioni salvate.
	 * @param array $attuali  Valori SEO vivi.
	 */
	public static function origine_attuale( array $snapshot, array $attuali ): string {
		$ultima = $snapshot['ultima_scrittura'] ?? null;
		if ( is_array( $ultima ) && self::valori_uguali( $ultima, $attuali ) ) {
			return (string) ( $ultima['origine'] ?? '' );
		}
		return self::ORIGINE_MANUALE;
	}

	/**
	 * La versione "precedente" che nasce mettendo da parte i valori vivi.
	 *
	 * @param array $snapshot Versioni salvate.
	 * @param array $attuali  Valori SEO vivi.
	 */
	private static function precedente_da_attuale( array $snapshot, array $attuali ): array {
		return self::versione(
			$attuali,
			self::origine_attuale( $snapshot, $attuali ),
			(string) ( $snapshot['ultima_scrittura']['salvato_il'] ?? '' )
		);
	}

	/**
	 * Le versioni da salvare dopo una scrittura. Null se la scrittura non
	 * cambia niente. Alla prima scrittura nasce la base (lo stato di partenza).
	 *
	 * @param array|null $esistente Versioni gia' salvate, se ci sono.
	 * @param array      $attuali   Valori SEO in questo momento.
	 * @param array      $nuovi     Valori che si stanno per scrivere.
	 * @param string     $origine   Chi scrive (es. "concorrente x.it").
	 * @param string     $quando    Data e ora ISO 8601.
	 */
	public static function dopo_scrittura( ?array $esistente, array $attuali, array $nuovi, string $origine, string $quando ): ?array {
		if ( self::valori_uguali( $attuali, $nuovi ) ) {
			return null;
		}
		$ultima = self::versione( $nuovi, $origine, $quando );

		if ( empty( $esistente ) ) {
			$base         = self::versione( $attuali, self::ORIGINE_PARTENZA, $quando );
			$base['tipo'] = 'originale';
			return array(
				'base'             => $base,
				'precedente'       => self::versione( $attuali, self::ORIGINE_PARTENZA, $quando ),
				'ultima_scrittura' => $ultima,
			);
		}

		$esistente['precedente']       = self::precedente_da_attuale( $esistente, $attuali );
		$esistente['ultima_scrittura'] = $ultima;
		return $esistente;
	}

	/**
	 * Ripristino della base o del precedente: i valori da scrivere e le nuove
	 * versioni (il precedente diventa quello che si lascia). Null se la
	 * versione scelta coincide gia' con i valori vivi.
	 *
	 * @param array  $snapshot Versioni salvate.
	 * @param string $versione "base" o "precedente".
	 * @param array  $attuali  Valori SEO vivi.
	 * @param string $quando   Data e ora ISO 8601.
	 */
	public static function ripristina( array $snapshot, string $versione, array $attuali, string $quando ): ?array {
		$scelta = $snapshot[ $versione ] ?? null;
		if ( ! in_array( $versione, self::VERSIONI, true ) || ! is_array( $scelta ) || self::valori_uguali( $scelta, $attuali ) ) {
			return null;
		}
		$valori = self::versione( $scelta, '', '' );
		unset( $valori['origine'], $valori['salvato_il'] );

		$snapshot['precedente']       = self::precedente_da_attuale( $snapshot, $attuali );
		$snapshot['ultima_scrittura'] = self::versione(
			$valori,
			'base' === $versione ? 'ripristino della base' : 'ripristino del precedente',
			$quando
		);

		return array(
			'valori'   => $valori,
			'snapshot' => $snapshot,
		);
	}

	/**
	 * Le versioni dopo "usa l'attuale come base": la base diventa lo stato
	 * vivo e non e' piu' l'originale. Il precedente non cambia.
	 *
	 * @param array  $snapshot Versioni salvate.
	 * @param array  $attuali  Valori SEO vivi.
	 * @param string $quando   Data e ora ISO 8601.
	 */
	public static function imposta_base( array $snapshot, array $attuali, string $quando ): array {
		$base             = self::versione( $attuali, self::ORIGINE_UTENTE, $quando );
		$base['tipo']     = 'utente';
		$snapshot['base'] = $base;
		return $snapshot;
	}

	/**
	 * Le versioni per la risposta REST: base, precedente e attuale, piu' i
	 * confronti che servono a sapere quali azioni hanno senso (null se il
	 * prodotto non ha versioni salvate).
	 *
	 * @param mixed $snapshot Valore letto dal meta (array, stringa vuota o altro).
	 * @param array $attuali  Valori SEO vivi.
	 */
	public static function per_risposta( $snapshot, array $attuali ): ?array {
		if ( ! is_array( $snapshot ) || empty( $snapshot['base'] ) ) {
			return null;
		}
		$precedente = is_array( $snapshot['precedente'] ?? null ) ? $snapshot['precedente'] : $snapshot['base'];

		return array(
			'base'                 => array_merge( self::versione( $snapshot['base'], (string) ( $snapshot['base']['origine'] ?? '' ), (string) ( $snapshot['base']['salvato_il'] ?? '' ) ), array( 'tipo' => (string) ( $snapshot['base']['tipo'] ?? 'originale' ) ) ),
			'precedente'           => self::versione( $precedente, (string) ( $precedente['origine'] ?? '' ), (string) ( $precedente['salvato_il'] ?? '' ) ),
			'attuale'              => self::versione(
				$attuali,
				self::origine_attuale( $snapshot, $attuali ),
				(string) ( $snapshot['ultima_scrittura']['salvato_il'] ?? '' )
			),
			'attuale_e_base'       => self::valori_uguali( $attuali, $snapshot['base'] ),
			'attuale_e_precedente' => self::valori_uguali( $attuali, $precedente ),
			'precedente_e_base'    => self::valori_uguali( $precedente, $snapshot['base'] ),
		);
	}
}
