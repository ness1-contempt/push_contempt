<?php
/**
 * Test delle regole per creare una categoria.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\NuovaCategoria;

/**
 * Logica pura: nessun bootstrap WordPress.
 */
class NuovaCategoriaTest extends TestCase {

	/**
	 * Albero: Smartphone(1); Accessori(2) > Cover(3).
	 */
	private function albero(): array {
		return array(
			array( 'id' => 1, 'nome' => 'Smartphone', 'madre' => 0 ),
			array( 'id' => 2, 'nome' => 'Accessori', 'madre' => 0 ),
			array( 'id' => 3, 'nome' => 'Cover', 'madre' => 2 ),
		);
	}

	public function test_una_categoria_alla_radice(): void {
		$esito = NuovaCategoria::pianifica( $this->albero(), 'Indumenti', 0 );
		$this->assertArrayNotHasKey( 'errore', $esito );
		$this->assertSame( 'Indumenti', $esito['piano']['percorso_dopo'] );
		$this->assertSame( 1, $esito['piano']['livello'] );
	}

	public function test_una_categoria_sotto_un_altra(): void {
		$esito = NuovaCategoria::pianifica( $this->albero(), 'Custodie', 3 );
		$this->assertSame( 'Accessori > Cover > Custodie', $esito['piano']['percorso_dopo'] );
		$this->assertSame( 3, $esito['piano']['livello'] );
	}

	public function test_il_nome_e_ripulito_da_spazi_e_tag(): void {
		$esito = NuovaCategoria::pianifica( $this->albero(), "  <b>Indumenti</b>\n  uomo ", 0 );
		$this->assertSame( 'Indumenti uomo', $esito['piano']['nome'] );
	}

	/**
	 * @dataProvider casi_rifiutati
	 *
	 * @param mixed  $nome   Nome.
	 * @param int    $madre  Madre.
	 * @param string $codice Codice d'errore atteso.
	 * @param int    $stato  Codice HTTP atteso.
	 */
	public function test_i_casi_non_validi_sono_rifiutati( $nome, int $madre, string $codice, int $stato ): void {
		$esito = NuovaCategoria::pianifica( $this->albero(), $nome, $madre );
		$this->assertSame( $codice, $esito['errore']['codice'] );
		$this->assertSame( $stato, $esito['errore']['stato'] );
	}

	/**
	 * Richieste non valide.
	 */
	public function casi_rifiutati(): array {
		return array(
			'nome vuoto'                 => array( '   ', 0, 'nome_vuoto', 400 ),
			'solo tag'                   => array( '<b></b>', 0, 'nome_vuoto', 400 ),
			'non e\' un testo'           => array( array( 'x' ), 0, 'nome_vuoto', 400 ),
			'nome troppo lungo'          => array( str_repeat( 'a', 101 ), 0, 'nome_troppo_lungo', 400 ),
			'madre inesistente'          => array( 'Nuova', 99, 'madre_non_trovata', 404 ),
			'esiste alla radice'         => array( 'smartphone', 0, 'nome_gia_esistente', 409 ),
			'esiste in un altro ramo'    => array( '  COVER ', 1, 'nome_gia_esistente', 409 ),
			'esiste nello stesso posto'  => array( 'Cover', 2, 'nome_gia_esistente', 409 ),
		);
	}

	public function test_il_messaggio_del_nome_doppio_dice_dove_si_trova(): void {
		$esito = NuovaCategoria::pianifica( $this->albero(), 'Cover', 0 );
		$this->assertStringContainsString( 'Accessori > Cover', $esito['errore']['messaggio'] );
	}

	public function test_non_si_superano_i_livelli_massimi(): void {
		$albero = array(
			array( 'id' => 10, 'nome' => 'L1', 'madre' => 0 ),
			array( 'id' => 11, 'nome' => 'L2', 'madre' => 10 ),
			array( 'id' => 12, 'nome' => 'L3', 'madre' => 11 ),
			array( 'id' => 13, 'nome' => 'L4', 'madre' => 12 ),
			array( 'id' => 14, 'nome' => 'L5', 'madre' => 13 ),
		);
		$this->assertArrayHasKey( 'piano', NuovaCategoria::pianifica( $albero, 'Nuova', 13 ), 'Il quinto livello va bene.' );
		$this->assertSame( 'troppi_livelli', NuovaCategoria::pianifica( $albero, 'Nuova', 14 )['errore']['codice'] );
	}
}
