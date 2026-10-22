<?php
/**
 * Logica pura dei filtri sugli ordini (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

// I messaggi delle eccezioni finiscono in una risposta REST JSON (mai in HTML), e questa
// classe resta volutamente pura, senza funzioni di escaping di WordPress.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Traduce i parametri di ricerca ordini (stati, finestre temporali) in
 * argomenti per wc_get_orders(). Separata dal controller per poterla
 * testare senza bootstrap WordPress.
 */
class OrderFilters {

	/**
	 * Stati richiesti (slug WooCommerce senza prefisso "wc-", separati da virgola).
	 *
	 * @param string $stati  Es. "processing,on-hold"; vuoto = nessun filtro.
	 * @param array  $validi Slug validi senza prefisso (da wc_get_order_statuses()).
	 * @return string[] Slug senza prefisso, nell'ordine dato, senza duplicati.
	 * @throws InvalidArgumentException Se uno stato non esiste in WooCommerce.
	 */
	public static function parse_stati( string $stati, array $validi ): array {
		$richiesti = array();
		foreach ( explode( ',', $stati ) as $stato ) {
			$stato = strtolower( trim( $stato ) );
			if ( '' === $stato ) {
				continue;
			}
			// Tollera "wc-processing" oltre a "processing".
			if ( 0 === strpos( $stato, 'wc-' ) ) {
				$stato = substr( $stato, 3 );
			}
			if ( ! in_array( $stato, $validi, true ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Stato ordine sconosciuto "%s". Validi: %s.', $stato, implode( ', ', $validi ) )
				);
			}
			$richiesti[ $stato ] = $stato;
		}

		return array_values( $richiesti );
	}

	/**
	 * Converte una data/ora testuale in timestamp, nel fuso del sito.
	 *
	 * Una data senza orario ("2026-10-22") come estremo superiore vale fino
	 * a fine giornata, cosi' "a=2026-10-22" include tutto il 22.
	 *
	 * @param string       $testo        Data (YYYY-MM-DD) o data e ora ISO 8601.
	 * @param DateTimeZone $fuso         Fuso orario del sito.
	 * @param bool         $fine_giornata Se true, una data senza orario vale 23:59:59.
	 * @throws InvalidArgumentException Se il testo non e' una data valida.
	 */
	public static function parse_data( string $testo, DateTimeZone $fuso, bool $fine_giornata = false ): int {
		$testo = trim( $testo );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?(Z|[+-]\d{2}:?\d{2})?$/', $testo ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Data non valida "%s": usa YYYY-MM-DD oppure YYYY-MM-DDTHH:MM.', $testo )
			);
		}

		$data = new DateTimeImmutable( $testo, $fuso );
		if ( $fine_giornata && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $testo ) ) {
			$data = $data->setTime( 23, 59, 59 );
		}

		return $data->getTimestamp();
	}

	/**
	 * Intervallo [da, a] in timestamp UTC, intersecando tutti i vincoli dati.
	 *
	 * @param int|null $ora_corrente      Timestamp "adesso".
	 * @param int|null $ultime_ore        Solo ordini creati nelle ultime N ore.
	 * @param int|null $piu_vecchi_di_ore Solo ordini creati da piu' di N ore.
	 * @param int|null $da                Estremo inferiore esplicito (timestamp).
	 * @param int|null $a                 Estremo superiore esplicito (timestamp).
	 * @return array{0: int|null, 1: int|null} Estremi, null = illimitato.
	 * @throws InvalidArgumentException Se i vincoli sono incoerenti o negativi.
	 */
	public static function intervallo(
		?int $ora_corrente,
		?int $ultime_ore,
		?int $piu_vecchi_di_ore,
		?int $da,
		?int $a
	): array {
		foreach ( array(
			'ultime_ore'        => $ultime_ore,
			'piu_vecchi_di_ore' => $piu_vecchi_di_ore,
		) as $nome => $ore ) {
			if ( null !== $ore && $ore < 0 ) {
				throw new InvalidArgumentException( sprintf( '%s non puo\' essere negativo.', $nome ) );
			}
		}

		$minimi  = array_filter(
			array(
				$da,
				null === $ultime_ore ? null : $ora_corrente - $ultime_ore * 3600,
			),
			static fn( $v ) => null !== $v
		);
		$massimi = array_filter(
			array(
				$a,
				null === $piu_vecchi_di_ore ? null : $ora_corrente - $piu_vecchi_di_ore * 3600,
			),
			static fn( $v ) => null !== $v
		);

		$inizio = $minimi ? max( $minimi ) : null;
		$fine   = $massimi ? min( $massimi ) : null;

		if ( null !== $inizio && null !== $fine && $inizio > $fine ) {
			throw new InvalidArgumentException( 'I filtri di data non lasciano nessun intervallo valido (inizio dopo la fine).' );
		}

		return array( $inizio, $fine );
	}

	/**
	 * Valore per la chiave "date_created" di wc_get_orders() (timestamp UTC).
	 *
	 * @param int|null $da Estremo inferiore.
	 * @param int|null $a  Estremo superiore.
	 */
	public static function date_created( ?int $da, ?int $a ): ?string {
		if ( null !== $da && null !== $a ) {
			return $da . '...' . $a;
		}
		if ( null !== $da ) {
			return '>=' . $da;
		}
		if ( null !== $a ) {
			return '<=' . $a;
		}

		return null;
	}
}
