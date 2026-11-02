<?php
/**
 * Test del percorso di categorie confrontato con l'albero di WooCommerce.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\PercorsoCategorie;

/**
 * Logica pura: nessun bootstrap WordPress.
 */
class PercorsoCategorieTest extends TestCase {

	/**
	 * Una categoria esistente.
	 *
	 * @param int    $id    Id.
	 * @param string $nome  Nome.
	 * @param int    $madre Id della madre (0 = radice).
	 */
	private function c( int $id, string $nome, int $madre = 0 ): array {
		return array(
			'id'    => $id,
			'nome'  => $nome,
			'madre' => $madre,
		);
	}

	public function test_un_albero_vuoto_vuol_dire_tutto_da_creare(): void {
		$esito = PercorsoCategorie::risolvi( array( 'Indumenti', 'Magliette' ), array() );

		$this->assertSame( array(), $esito['errori'] );
		$this->assertSame( array( 'da_creare', 'da_creare' ), array_column( $esito['livelli'], 'stato' ) );
		$this->assertSame( array( 'Indumenti', 'Indumenti > Magliette' ), array_column( $esito['livelli'], 'percorso' ) );
	}

	public function test_le_categorie_nel_posto_giusto_si_riusano_e_le_altre_si_creano_sotto(): void {
		$esito = PercorsoCategorie::risolvi( array( 'Indumenti', 'Magliette', 'Sportive' ), array( $this->c( 1, 'Indumenti' ), $this->c( 2, 'Magliette', 1 ) ) );

		$this->assertSame( array(), $esito['errori'] );
		$this->assertSame( array( 'esiste', 'esiste', 'da_creare' ), array_column( $esito['livelli'], 'stato' ) );
		$this->assertSame( array( 1, 2, null ), array_column( $esito['livelli'], 'id' ) );
	}

	public function test_il_confronto_ignora_maiuscole_e_spazi(): void {
		$esito = PercorsoCategorie::risolvi( array( '  indumenti ', 'MAGLIETTE' ), array( $this->c( 1, 'Indumenti' ), $this->c( 2, 'Magliette', 1 ) ) );

		$this->assertSame( array(), $esito['errori'] );
		$this->assertSame( array( 'esiste', 'esiste' ), array_column( $esito['livelli'], 'stato' ) );
	}

	public function test_una_categoria_con_lo_stesso_nome_in_un_altro_ramo_e_un_conflitto(): void {
		// Su WooCommerce Magliette sta in Abbigliamento, la cartella la vuole in Indumenti.
		$esito = PercorsoCategorie::risolvi(
			array( 'Indumenti', 'Magliette' ),
			array( $this->c( 1, 'Indumenti' ), $this->c( 5, 'Abbigliamento' ), $this->c( 6, 'Magliette', 5 ) )
		);

		$this->assertCount( 1, $esito['errori'] );
		$this->assertStringContainsString( 'esiste gia\' come «Abbigliamento > Magliette»', $esito['errori'][0] );
		$this->assertStringContainsString( 'la cartella la vuole in «Indumenti > Magliette»', $esito['errori'][0] );
		$this->assertSame( 'conflitto', $esito['livelli'][1]['stato'] );
	}

	public function test_una_categoria_che_esiste_in_radice_ma_la_cartella_vuole_dentro_un_altra_e_un_conflitto(): void {
		$esito = PercorsoCategorie::risolvi( array( 'Indumenti', 'Magliette' ), array( $this->c( 1, 'Indumenti' ), $this->c( 2, 'Magliette' ) ) );

		$this->assertStringContainsString( 'esiste gia\' come «Magliette»', $esito['errori'][0] );
	}

	public function test_una_madre_da_creare_con_un_figlio_gia_esistente_altrove_e_un_conflitto(): void {
		// Indumenti non esiste ancora, ma Magliette si': non puo' stare sotto una madre nuova.
		$esito = PercorsoCategorie::risolvi( array( 'Indumenti', 'Magliette' ), array( $this->c( 2, 'Magliette' ) ) );

		$this->assertSame( array( 'da_creare', 'conflitto' ), array_column( $esito['livelli'], 'stato' ) );
		$this->assertNotSame( array(), $esito['errori'] );
	}

	public function test_due_categorie_con_lo_stesso_nome_sul_sito_si_segnalano(): void {
		$esito = PercorsoCategorie::risolvi(
			array( 'Magliette' ),
			array( $this->c( 1, 'Magliette' ), $this->c( 2, 'Abbigliamento' ), $this->c( 3, 'magliette', 2 ) )
		);

		$this->assertStringContainsString( 'Esistono 2 categorie chiamate «Magliette»', $esito['errori'][0] );
		$this->assertStringContainsString( 'Abbigliamento > magliette', $esito['errori'][0] );
		$this->assertSame( 'conflitto', $esito['livelli'][0]['stato'] );
	}

	public function test_pulisci_toglie_gli_spazi_e_segnala_errori_di_forma(): void {
		$this->assertSame( array( 'A', 'B C' ), PercorsoCategorie::pulisci( array( ' A ', 'B   C' ) )['percorso'] );

		foreach ( array( null, array(), 'Indumenti' ) as $caso ) {
			$this->assertNotSame( array(), PercorsoCategorie::pulisci( $caso )['errori'] );
		}
		$this->assertCount( 2, PercorsoCategorie::pulisci( array( 'A', ' ', 'a' ) )['errori'] ); // Vuoto e doppio.
		$this->assertStringContainsString( 'Troppi livelli', PercorsoCategorie::pulisci( array( 'a', 'b', 'c', 'd', 'e', 'f' ) )['errori'][0] );
	}

	public function test_lo_stesso_nome_in_due_punti_delle_cartelle_si_segnala(): void {
		$errori = PercorsoCategorie::conflitti_tra_percorsi(
			array(
				array( 'Indumenti', 'Magliette' ),
				array( 'Sport', 'Magliette' ),
				array( 'Indumenti', 'Pantaloni' ),
			)
		);

		$this->assertCount( 1, $errori );
		$this->assertStringContainsString( 'Indumenti > Magliette', $errori[0] );
		$this->assertStringContainsString( 'Sport > Magliette', $errori[0] );
	}

	public function test_percorsi_diversi_che_condividono_la_madre_non_sono_un_conflitto(): void {
		$this->assertSame(
			array(),
			PercorsoCategorie::conflitti_tra_percorsi(
				array(
					array( 'Indumenti', 'Magliette' ),
					array( 'indumenti', 'Pantaloni' ),
					array( 'Indumenti' ),
				)
			)
		);
	}

	public function test_il_dettaglio_dei_conflitti_dice_nome_e_punti(): void {
		$dettaglio = PercorsoCategorie::conflitti_dettaglio(
			array(
				array( 'Indumenti', 'Magliette' ),
				array( 'Sport', 'Magliette' ),
				array( 'Indumenti', 'Pantaloni' ),
			)
		);

		$this->assertSame(
			array(
				array(
					'nome'  => 'Magliette',
					'posti' => array( 'Indumenti > Magliette', 'Sport > Magliette' ),
				),
			),
			$dettaglio
		);
		$this->assertSame( array(), PercorsoCategorie::conflitti_dettaglio( array( array( 'A', 'B' ), array( 'A', 'C' ) ) ) );
	}
}
