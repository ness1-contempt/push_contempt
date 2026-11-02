<?php
/**
 * Giudizio SEO su un prodotto variabile e sulle sue varianti (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Una variante non ha un indirizzo suo: la pagina che si posiziona e' quella
 * del padre, e i motori leggono le varianti dal testo della pagina e dai dati
 * strutturati. Quindi i problemi sono di due tipi: quelli che rendono una
 * variante invisibile o ambigua (senza prezzo, "qualsiasi", doppioni) e quelli
 * che lasciano la pagina senza le parole con cui la gente cerca (un colore o
 * una memoria che nel testo non compare mai).
 *
 * Ingresso `$padre`:
 * - `attributi`: etichetta => `array{valori: string[], visibile: bool}`, solo
 *   quelli usati per le varianti.
 * - `testo`: descrizione e descrizione breve del padre, senza HTML.
 * - `titolo_seo`, `parola_chiave`: come impostati (vuoti se mancano).
 *
 * Ingresso `$varianti` (una riga per variante):
 * - `id`, `stato` (publish, private...), `prezzo` ('' se manca), `sku`,
 *   `immagine_propria` (bool), `attributi`: etichetta => valore ('' = "qualsiasi").
 *
 * Uscita: `problemi` (ognuno con `codice`, `gravita`, `messaggio`, `varianti`)
 * e `conteggio` per gravita'.
 */
class AnalisiVarianti {

	const ERRORE       = 'errore';
	const AVVISO       = 'avviso';
	const SUGGERIMENTO = 'suggerimento';

	/**
	 * Giudica il prodotto.
	 *
	 * @param array $padre    Dati del padre (vedi la descrizione della classe).
	 * @param array $varianti Le varianti.
	 *
	 * @return array{problemi: array, conteggio: array{errori: int, avvisi: int, suggerimenti: int}}
	 */
	public static function analizza( array $padre, array $varianti ): array {
		$in_vendita = array_values(
			array_filter(
				$varianti,
				static function ( array $variante ): bool {
					return 'publish' === $variante['stato'];
				}
			)
		);

		$problemi = array_merge(
			self::prezzi( $in_vendita ),
			self::combinazioni( $in_vendita ),
			self::attributi_nascosti( $padre ),
			self::titolo_e_parola_chiave( $padre ),
			self::valori_nel_testo( $padre ),
			self::immagini( $in_vendita ),
			self::codici( $in_vendita )
		);

		$ordine = array_flip( array( self::ERRORE, self::AVVISO, self::SUGGERIMENTO ) );
		usort(
			$problemi,
			static function ( array $a, array $b ) use ( $ordine ): int {
				return $ordine[ $a['gravita'] ] <=> $ordine[ $b['gravita'] ];
			}
		);

		$gravita = array_count_values( array_column( $problemi, 'gravita' ) );
		return array(
			'problemi'  => $problemi,
			'conteggio' => array(
				'errori'       => $gravita[ self::ERRORE ] ?? 0,
				'avvisi'       => $gravita[ self::AVVISO ] ?? 0,
				'suggerimenti' => $gravita[ self::SUGGERIMENTO ] ?? 0,
			),
		);
	}

	/**
	 * Nessuna variante acquistabile, o varianti in vendita senza prezzo.
	 *
	 * @param array $in_vendita Varianti pubblicate.
	 */
	private static function prezzi( array $in_vendita ): array {
		if ( ! $in_vendita ) {
			return array( self::problema( 'nessuna_variante_in_vendita', self::ERRORE, 'Nessuna variante e\' pubblicata: il prodotto non ha prezzo ne\' dati di offerta.', array() ) );
		}
		$senza_prezzo = self::ids( $in_vendita, static fn( array $v ): bool => '' === trim( (string) $v['prezzo'] ) );
		if ( ! $senza_prezzo ) {
			return array();
		}
		return array( self::problema( 'variante_senza_prezzo', self::ERRORE, 'Varianti pubblicate senza prezzo: non si possono acquistare e non entrano nell\'intervallo di prezzo.', $senza_prezzo ) );
	}

	/**
	 * Varianti "qualsiasi" (un attributo senza valore) e combinazioni ripetute.
	 *
	 * @param array $in_vendita Varianti pubblicate.
	 */
	private static function combinazioni( array $in_vendita ): array {
		$problemi = array();

		$qualsiasi = self::ids(
			$in_vendita,
			static function ( array $v ): bool {
				return in_array( '', array_map( 'trim', array_map( 'strval', $v['attributi'] ) ), true );
			}
		);
		if ( $qualsiasi ) {
			$problemi[] = self::problema( 'variante_qualsiasi', self::AVVISO, 'Varianti con un attributo su "qualsiasi": la combinazione e\' ambigua e non si puo\' descrivere come offerta precisa nei dati strutturati.', $qualsiasi );
		}

		$viste    = array();
		$doppioni = array();
		foreach ( $in_vendita as $variante ) {
			$chiave = serialize( self::minuscole( $variante['attributi'] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- solo una chiave di confronto.
			if ( isset( $viste[ $chiave ] ) ) {
				$doppioni[ $viste[ $chiave ] ] = $viste[ $chiave ];
				$doppioni[ $variante['id'] ]   = $variante['id'];
			} else {
				$viste[ $chiave ] = $variante['id'];
			}
		}
		if ( $doppioni ) {
			$problemi[] = self::problema( 'combinazione_duplicata', self::ERRORE, 'Piu\' varianti pubblicate hanno la stessa combinazione di attributi: il cliente non le distingue e le offerte si sovrappongono.', array_values( $doppioni ) );
		}

		return $problemi;
	}

	/**
	 * Attributi di variazione nascosti nella scheda: il valore non compare come testo nella pagina.
	 *
	 * @param array $padre Dati del padre.
	 */
	private static function attributi_nascosti( array $padre ): array {
		$nascosti = array();
		foreach ( $padre['attributi'] as $etichetta => $attributo ) {
			if ( ! $attributo['visibile'] ) {
				$nascosti[] = $etichetta;
			}
		}
		if ( ! $nascosti ) {
			return array();
		}
		return array( self::problema( 'attributo_nascosto', self::AVVISO, 'Attributi non mostrati nella scheda prodotto (' . implode( ', ', $nascosti ) . '): i loro valori restano fuori dal testo della pagina.', array() ) );
	}

	/**
	 * Titolo SEO o parola chiave che puntano a una sola variante quando ce ne sono piu' d'una.
	 *
	 * @param array $padre Dati del padre.
	 */
	private static function titolo_e_parola_chiave( array $padre ): array {
		$problemi = array();
		$campi    = array(
			'titolo_seo_con_una_variante'    => array( self::AVVISO, 'titolo_seo', 'Il titolo SEO cita solo una variante' ),
			'parola_chiave_con_una_variante' => array( self::SUGGERIMENTO, 'parola_chiave', 'La parola chiave focus cita solo una variante' ),
		);
		foreach ( $campi as $codice => list( $gravita, $campo, $inizio ) ) {
			$citati = self::valori_citati( (string) ( $padre[ $campo ] ?? '' ), $padre['attributi'] );
			if ( $citati ) {
				$problemi[] = self::problema( $codice, $gravita, $inizio . ' (' . implode( ', ', $citati ) . '): la pagina e\' una sola per tutte le varianti, quel testo la farebbe sembrare solo quella.', array() );
			}
		}
		return $problemi;
	}

	/**
	 * Valori delle varianti che non compaiono mai nel testo del padre.
	 *
	 * @param array $padre Dati del padre.
	 */
	private static function valori_nel_testo( array $padre ): array {
		$testo   = (string) $padre['testo'];
		$mancano = array();
		foreach ( $padre['attributi'] as $etichetta => $attributo ) {
			foreach ( $attributo['valori'] as $valore ) {
				if ( '' !== trim( $valore ) && ! self::contiene_parola( $testo, $valore ) ) {
					$mancano[] = $etichetta . ': ' . $valore;
				}
			}
		}
		if ( ! $mancano ) {
			return array();
		}
		return array( self::problema( 'valori_non_nel_testo', self::SUGGERIMENTO, 'Valori che nel testo della pagina non compaiono (' . implode( '; ', $mancano ) . '): chi cerca "' . self::esempio( $mancano ) . '" non trova parole corrispondenti.', array() ) );
	}

	/**
	 * Varianti senza immagine propria.
	 *
	 * @param array $in_vendita Varianti pubblicate.
	 */
	private static function immagini( array $in_vendita ): array {
		if ( count( $in_vendita ) < 2 ) {
			return array();
		}
		$senza = self::ids( $in_vendita, static fn( array $v ): bool => ! $v['immagine_propria'] );
		if ( ! $senza ) {
			return array();
		}
		if ( count( $senza ) === count( $in_vendita ) ) {
			return array( self::problema( 'varianti_senza_immagine_propria', self::SUGGERIMENTO, 'Nessuna variante ha un\'immagine propria: tutte mostrano quella del prodotto, anche dove il colore o il modello cambia.', $senza ) );
		}
		return array( self::problema( 'varianti_senza_immagine_propria', self::AVVISO, 'Alcune varianti hanno un\'immagine propria e altre no: chi le sceglie vede quella del padre.', $senza ) );
	}

	/**
	 * Varianti senza SKU (serve per i dati strutturati e per i feed).
	 *
	 * @param array $in_vendita Varianti pubblicate.
	 */
	private static function codici( array $in_vendita ): array {
		$senza = self::ids( $in_vendita, static fn( array $v ): bool => '' === trim( (string) $v['sku'] ) );
		if ( ! $senza ) {
			return array();
		}
		return array( self::problema( 'varianti_senza_sku', self::SUGGERIMENTO, 'Varianti senza SKU: nei dati strutturati e nei feed non hanno un codice che le identifichi.', $senza ) );
	}

	/**
	 * Valori di variazione (con almeno due scelte) citati nel testo, per intero.
	 *
	 * @param string $testo     Titolo o parola chiave.
	 * @param array  $attributi Attributi di variazione del padre.
	 *
	 * @return string[]
	 */
	private static function valori_citati( string $testo, array $attributi ): array {
		$citati = array();
		foreach ( $attributi as $attributo ) {
			if ( count( $attributo['valori'] ) < 2 ) {
				continue;
			}
			foreach ( $attributo['valori'] as $valore ) {
				if ( '' !== trim( $valore ) && self::contiene_parola( $testo, $valore ) ) {
					$citati[] = mb_strtolower( trim( $valore ) );
				}
			}
		}
		return $citati;
	}

	/**
	 * Se il testo contiene il valore come parola intera ("L" non e' in "felpa"), senza badare alle maiuscole.
	 *
	 * @param string $testo  Testo in cui cercare.
	 * @param string $valore Valore dell'attributo.
	 */
	private static function contiene_parola( string $testo, string $valore ): bool {
		$valore = preg_quote( trim( $valore ), '/' );
		return 1 === preg_match( '/(?<![\p{L}\p{N}])' . $valore . '(?![\p{L}\p{N}])/iu', $testo );
	}

	/**
	 * Un esempio di ricerca dal primo valore mancante ("Colore: Nero" -> "Nero").
	 *
	 * @param string[] $mancano Voci "Attributo: valore".
	 */
	private static function esempio( array $mancano ): string {
		$parti = explode( ': ', $mancano[0], 2 );
		return $parti[1] ?? $mancano[0];
	}

	/**
	 * Gli id delle varianti che soddisfano la condizione.
	 *
	 * @param array    $varianti  Varianti.
	 * @param callable $condizione Riceve la riga, dice se e' un caso da segnalare.
	 *
	 * @return int[]
	 */
	private static function ids( array $varianti, callable $condizione ): array {
		$ids = array();
		foreach ( $varianti as $variante ) {
			if ( $condizione( $variante ) ) {
				$ids[] = (int) $variante['id'];
			}
		}
		return $ids;
	}

	/**
	 * Valori in minuscolo e senza spazi ai bordi, per confrontare combinazioni.
	 *
	 * @param array $attributi Etichetta => valore.
	 */
	private static function minuscole( array $attributi ): array {
		return array_map(
			static fn( $valore ): string => mb_strtolower( trim( (string) $valore ) ),
			$attributi
		);
	}

	/**
	 * Un problema nel formato di uscita.
	 *
	 * @param string $codice   Identificativo stabile.
	 * @param string $gravita  errore, avviso o suggerimento.
	 * @param string $messaggio Spiegazione per l'utente.
	 * @param int[]  $varianti Varianti coinvolte (vuoto se riguarda il padre).
	 */
	private static function problema( string $codice, string $gravita, string $messaggio, array $varianti ): array {
		return array(
			'codice'    => $codice,
			'gravita'   => $gravita,
			'messaggio' => $messaggio,
			'varianti'  => $varianti,
		);
	}
}
