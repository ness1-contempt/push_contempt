<?php
/**
 * Test della logica pura di calcolo prezzi.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\PricingCalculator;

/**
 * Nessun bootstrap WordPress necessario: PricingCalculator e' logica pura.
 */
class PricingCalculatorTest extends TestCase {

	/**
	 * Percentuale positiva aumenta il prezzo.
	 */
	public function test_nuovo_prezzo_percentuale_positiva(): void {
		$this->assertSame( '110.00', PricingCalculator::nuovo_prezzo( '100.00', 10.0, null ) );
	}

	/**
	 * Percentuale negativa diminuisce il prezzo.
	 */
	public function test_nuovo_prezzo_percentuale_negativa(): void {
		$this->assertSame( '85.00', PricingCalculator::nuovo_prezzo( '100.00', -15.0, null ) );
	}

	/**
	 * Delta fisso si applica direttamente.
	 */
	public function test_nuovo_prezzo_delta_fisso(): void {
		$this->assertSame( '34.90', PricingCalculator::nuovo_prezzo( '39.90', null, -5.0 ) );
	}

	/**
	 * Il prezzo non va mai sotto zero.
	 */
	public function test_nuovo_prezzo_non_va_sotto_zero(): void {
		$this->assertSame( '0.00', PricingCalculator::nuovo_prezzo( '3.00', null, -10.0 ) );
	}

	/**
	 * Serve esattamente uno tra percentuale e delta, non zero né entrambi.
	 */
	public function test_nuovo_prezzo_richiede_esattamente_un_parametro(): void {
		$this->expectException( \InvalidArgumentException::class );
		PricingCalculator::nuovo_prezzo( '10.00', 10.0, 5.0 );
	}

	/**
	 * Nessuno dei due parametri specificato e' altrettanto un errore.
	 */
	public function test_nuovo_prezzo_richiede_almeno_un_parametro(): void {
		$this->expectException( \InvalidArgumentException::class );
		PricingCalculator::nuovo_prezzo( '10.00', null, null );
	}
}
