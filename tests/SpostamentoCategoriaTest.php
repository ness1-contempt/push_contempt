<?php
/**
 * Test delle regole per spostare una categoria.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\SpostamentoCategoria;

/**
 * Logica pura: nessun bootstrap WordPress.
 */
class SpostamentoCategoriaTest extends TestCase {

	/**
	 * Albero: Indumenti(1) > Magliette(2) > Polo(3); Pantaloni(4) alla radice; Senza categoria(9, predefinita).
	 */
	private function albero(): array {
		return array(
			array( 'id' => 1, 'nome' => 'Indumenti', 'madre' => 0 ),
			array( 'id' => 2, 'nome' => 'Magliette', 'madre' => 1 ),
			array( 'id' => 3, 'nome' => 'Polo', 'madre' => 2 ),
			array( 'id' => 4, 'nome' => 'Pantaloni', 'madre' => 0 ),
			array( 'id' => 9, 'nome' => 'Senza categoria', 'madre' => 0 ),
		);
	}

	public function test_agganciare_una_categoria_alla_radice_sotto_un_altra(): void {
		$esito = SpostamentoCategoria::pianifica( $this->albero(), 4, 1, 9 );
		$this->assertArrayNotHasKey( 'errore', $esito );
		$this->assertSame( 'Pantaloni', $esito['piano']['percorso_prima'] );
		$this->assertSame( 'Indumenti > Pantaloni', $esito['piano']['percorso_dopo'] );
		$this->assertSame( array(), $esito['piano']['sottocategorie'] );
	}

	public function test_il_ramo_si_sposta_con_le_sue_sottocategorie(): void {
		$esito = SpostamentoCategoria::pianifica( $this->albero(), 2, 4, 9 );
		$this->assertSame( 'Indumenti > Magliette', $esito['piano']['percorso_prima'] );
		$this->assertSame( 'Pantaloni > Magliette', $esito['piano']['percorso_dopo'] );
		$this->assertSame( array( 3 ), $esito['piano']['sottocategorie'] );
		$this->assertSame( 3, $esito['piano']['livelli_dopo'] );
	}

	public function test_si_puo_portare_una_categoria_alla_radice(): void {
		$esito = SpostamentoCategoria::pianifica( $this->albero(), 2, 0, 9 );
		$this->assertSame( 'Magliette', $esito['piano']['percorso_dopo'] );
		$this->assertSame( 0, $esito['piano']['madre_dopo'] );
	}

	/**
	 * @dataProvider casi_rifiutati
	 *
	 * @param int    $id          Categoria da spostare.
	 * @param int    $nuova_madre Nuova madre.
	 * @param string $codice      Codice d'errore atteso.
	 * @param int    $stato       Codice HTTP atteso.
	 */
	public function test_i_casi_che_romperebbero_l_albero_sono_rifiutati( int $id, int $nuova_madre, string $codice, int $stato ): void {
		$esito = SpostamentoCategoria::pianifica( $this->albero(), $id, $nuova_madre, 9 );
		$this->assertSame( $codice, $esito['errore']['codice'] );
		$this->assertSame( $stato, $esito['errore']['stato'] );
	}

	/**
	 * Spostamenti non permessi.
	 */
	public function casi_rifiutati(): array {
		return array(
			'categoria inesistente'      => array( 77, 1, 'categoria_non_trovata', 404 ),
			'madre inesistente'          => array( 4, 77, 'nuova_madre_non_trovata', 404 ),
			'la predefinita'             => array( 9, 1, 'categoria_predefinita', 400 ),
			'dove gia\' sta'             => array( 2, 1, 'nessun_cambiamento', 400 ),
			'gia\' alla radice'          => array( 4, 0, 'nessun_cambiamento', 400 ),
			'dentro se stessa'           => array( 1, 1, 'spostamento_in_se_stessa', 400 ),
			'dentro una sua nipote'      => array( 1, 3, 'spostamento_in_se_stessa', 400 ),
		);
	}

	public function test_non_si_superano_i_livelli_massimi(): void {
		// Catena di 5 livelli: 10 > 11 > 12 > 13 > 14. Spostare Indumenti(1, 3 livelli) sotto 14 darebbe 8.
		$albero = array_merge(
			$this->albero(),
			array(
				array( 'id' => 10, 'nome' => 'L1', 'madre' => 0 ),
				array( 'id' => 11, 'nome' => 'L2', 'madre' => 10 ),
				array( 'id' => 12, 'nome' => 'L3', 'madre' => 11 ),
				array( 'id' => 13, 'nome' => 'L4', 'madre' => 12 ),
				array( 'id' => 14, 'nome' => 'L5', 'madre' => 13 ),
			)
		);
		$this->assertSame( 'troppi_livelli', SpostamentoCategoria::pianifica( $albero, 1, 14, 9 )['errore']['codice'] );
		// Una foglia sotto il quarto livello arriva al quinto: va bene.
		$this->assertArrayHasKey( 'piano', SpostamentoCategoria::pianifica( $albero, 4, 13, 9 ) );
		// Sotto il quinto sarebbe il sesto: no.
		$this->assertSame( 'troppi_livelli', SpostamentoCategoria::pianifica( $albero, 4, 14, 9 )['errore']['codice'] );
	}
}
