<?php
/**
 * Logica pura del calcolo prezzi (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Calcola un nuovo prezzo a partire da una percentuale o un delta fisso.
 */
class PricingCalculator {

	/**
	 * Nuovo prezzo, arrotondato a 2 decimali, mai sotto zero.
	 *
	 * @param string     $prezzo_attuale Prezzo di partenza (es. "39.90").
	 * @param float|null $percentuale    Variazione percentuale (es. 10, -15). Esclusiva con $delta.
	 * @param float|null $delta          Variazione fissa in valuta (es. -5). Esclusiva con $percentuale.
	 *
	 * @throws \InvalidArgumentException Se non viene specificato esattamente uno tra i due parametri.
	 */
	public static function nuovo_prezzo( string $prezzo_attuale, ?float $percentuale, ?float $delta ): string {
		if ( ( null === $percentuale ) === ( null === $delta ) ) {
			throw new \InvalidArgumentException( 'Specificare esattamente uno tra percentuale e delta.' );
		}

		$attuale = (float) $prezzo_attuale;
		$nuovo   = null !== $percentuale ? $attuale * ( 1 + ( $percentuale / 100 ) ) : $attuale + $delta;
		$nuovo   = max( $nuovo, 0.0 );

		return number_format( $nuovo, 2, '.', '' );
	}
}
