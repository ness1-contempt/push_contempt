<?php
/**
 * Test della logica pura dei filtri ordini.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PushContempt\OrderFilters;

/**
 * Nessun bootstrap WordPress necessario: OrderFilters e' logica pura.
 */
class OrderFiltersTest extends TestCase {

	const VALIDI = array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' );

	/**
	 * Stati multipli, con spazi, prefisso wc- e duplicati.
	 */
	public function test_parse_stati_normalizza_e_deduplica(): void {
		$this->assertSame(
			array( 'processing', 'on-hold' ),
			OrderFilters::parse_stati( ' Processing, wc-on-hold ,processing,', self::VALIDI )
		);
	}

	/**
	 * Stringa vuota = nessun filtro.
	 */
	public function test_parse_stati_vuoto_non_filtra(): void {
		$this->assertSame( array(), OrderFilters::parse_stati( '', self::VALIDI ) );
	}

	/**
	 * Uno stato che WooCommerce non ha (es. "evaso") va rifiutato, elencando i validi.
	 */
	public function test_parse_stati_rifiuta_stato_inesistente(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Validi: pending, processing' );
		OrderFilters::parse_stati( 'evaso', self::VALIDI );
	}

	/**
	 * Data senza orario come estremo inferiore: inizio giornata nel fuso del sito.
	 */
	public function test_parse_data_usa_il_fuso_del_sito(): void {
		$roma = new DateTimeZone( 'Europe/Rome' );

		// Il 22 ottobre 2026 Roma e' UTC+2 (ora legale).
		$this->assertSame( gmmktime( 22, 0, 0, 10, 21, 2026 ), OrderFilters::parse_data( '2026-10-22', $roma ) );
	}

	/**
	 * Data senza orario come estremo superiore: include tutta la giornata.
	 */
	public function test_parse_data_fine_giornata(): void {
		$roma = new DateTimeZone( 'Europe/Rome' );

		$this->assertSame(
			gmmktime( 21, 59, 59, 10, 22, 2026 ),
			OrderFilters::parse_data( '2026-10-22', $roma, true )
		);
		// Con l'orario esplicito, fine_giornata non cambia nulla.
		$this->assertSame(
			gmmktime( 8, 30, 0, 10, 22, 2026 ),
			OrderFilters::parse_data( '2026-10-22T10:30', $roma, true )
		);
	}

	/**
	 * Testo che non e' una data.
	 */
	public function test_parse_data_rifiuta_testo_non_valido(): void {
		$this->expectException( InvalidArgumentException::class );
		OrderFilters::parse_data( 'ieri', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * "Ultime 24 ore": solo estremo inferiore.
	 */
	public function test_intervallo_ultime_ore(): void {
		$this->assertSame( array( 1000000 - 86400, null ), OrderFilters::intervallo( 1000000, 24, null, null, null ) );
	}

	/**
	 * "Piu' vecchi di 24 ore": solo estremo superiore.
	 */
	public function test_intervallo_piu_vecchi_di_ore(): void {
		$this->assertSame( array( null, 1000000 - 86400 ), OrderFilters::intervallo( 1000000, null, 24, null, null ) );
	}

	/**
	 * Piu' vincoli sullo stesso lato: vince il piu' stretto.
	 */
	public function test_intervallo_interseca_i_vincoli(): void {
		// ultime 48h (inizio now-172800) e da esplicito piu' recente: vince da.
		$this->assertSame(
			array( 900000, 950000 ),
			OrderFilters::intervallo( 1000000, 48, null, 900000, 950000 )
		);
	}

	/**
	 * Nessun vincolo: intervallo illimitato.
	 */
	public function test_intervallo_senza_vincoli(): void {
		$this->assertSame( array( null, null ), OrderFilters::intervallo( 1000000, null, null, null, null ) );
	}

	/**
	 * Vincoli che non si sovrappongono: errore chiaro invece di zero risultati silenziosi.
	 */
	public function test_intervallo_incoerente_lancia_eccezione(): void {
		$this->expectException( InvalidArgumentException::class );
		// Ultime 1h (dopo 996400) ma piu' vecchi di 24h (prima di 913600).
		OrderFilters::intervallo( 1000000, 1, 24, null, null );
	}

	/**
	 * Le ore negative non hanno senso.
	 */
	public function test_intervallo_rifiuta_ore_negative(): void {
		$this->expectException( InvalidArgumentException::class );
		OrderFilters::intervallo( 1000000, -1, null, null, null );
	}

	/**
	 * Formato del filtro date_created di wc_get_orders().
	 */
	public function test_date_created_formati(): void {
		$this->assertSame( '100...200', OrderFilters::date_created( 100, 200 ) );
		$this->assertSame( '>=100', OrderFilters::date_created( 100, null ) );
		$this->assertSame( '<=200', OrderFilters::date_created( null, 200 ) );
		$this->assertNull( OrderFilters::date_created( null, null ) );
	}
}
