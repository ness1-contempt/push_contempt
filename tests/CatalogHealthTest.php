<?php
/**
 * Test della logica pura del controllo salute catalogo.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests;

use PHPUnit\Framework\TestCase;
use PushContempt\CatalogHealth;

/**
 * Nessun bootstrap WordPress necessario: CatalogHealth e' logica pura.
 */
class CatalogHealthTest extends TestCase {

	/**
	 * Dati di un prodotto completo, come base per i singoli test.
	 *
	 * @param array $override Chiavi da sovrascrivere rispetto alla base.
	 */
	private function prodotto( array $override = array() ): array {
		$base = array(
			'ha_immagine'      => true,
			'numero_categorie' => 1,
			'variabile'        => false,
			'prezzo_regolare'  => '19.90',
			'descrizione'      => '<p>' . str_repeat( 'Testo descrittivo abbastanza lungo. ', 3 ) . '</p>',
		);

		return array_merge( $base, $override );
	}

	/**
	 * Verifica i casi base di prezzo_valido().
	 */
	public function test_prezzo_valido_casi(): void {
		$this->assertTrue( CatalogHealth::prezzo_valido( '19.90' ) );
		$this->assertFalse( CatalogHealth::prezzo_valido( '0' ) );
		$this->assertFalse( CatalogHealth::prezzo_valido( null ) );
		$this->assertFalse( CatalogHealth::prezzo_valido( '' ) );
		$this->assertFalse( CatalogHealth::prezzo_valido( 'non-un-prezzo' ) );
	}

	/**
	 * Un prodotto completo non deve avere problemi.
	 */
	public function test_prodotto_completo_nessun_problema(): void {
		$this->assertSame( array(), CatalogHealth::problemi( $this->prodotto(), 50 ) );
	}

	/**
	 * Segnala l'assenza di immagine in evidenza.
	 */
	public function test_segnala_nessuna_immagine(): void {
		$problemi = CatalogHealth::problemi( $this->prodotto( array( 'ha_immagine' => false ) ), 50 );
		$this->assertContains( 'Nessuna immagine in evidenza', $problemi );
	}

	/**
	 * Segnala l'assenza di categoria.
	 */
	public function test_segnala_nessuna_categoria(): void {
		$problemi = CatalogHealth::problemi( $this->prodotto( array( 'numero_categorie' => 0 ) ), 50 );
		$this->assertContains( 'Nessuna categoria', $problemi );
	}

	/**
	 * Il prezzo mancante va segnalato solo per prodotti non variabili.
	 */
	public function test_segnala_prezzo_mancante_solo_se_non_variabile(): void {
		$semplice = CatalogHealth::problemi( $this->prodotto( array( 'prezzo_regolare' => null ) ), 50 );
		$this->assertTrue( $this->contiene_sottostringa( $semplice, 'Prezzo' ) );

		$variabile = CatalogHealth::problemi(
			$this->prodotto(
				array(
					'prezzo_regolare' => null,
					'variabile'       => true,
				)
			),
			50
		);
		$this->assertFalse( $this->contiene_sottostringa( $variabile, 'Prezzo' ) );
	}

	/**
	 * La descrizione corta va segnalata ignorando i tag HTML nel conteggio.
	 */
	public function test_segnala_descrizione_corta_ignorando_i_tag_html(): void {
		$problemi = CatalogHealth::problemi(
			$this->prodotto( array( 'descrizione' => '<p>Troppo corta</p>' ) ),
			50
		);
		$this->assertTrue( $this->contiene_sottostringa( $problemi, 'Descrizione' ) );
	}

	/**
	 * Più problemi insieme vengono tutti segnalati.
	 */
	public function test_prodotto_con_piu_problemi_insieme(): void {
		$problemi = CatalogHealth::problemi(
			$this->prodotto(
				array(
					'ha_immagine'      => false,
					'numero_categorie' => 0,
					'prezzo_regolare'  => null,
					'descrizione'      => '',
				)
			),
			50
		);
		$this->assertCount( 4, $problemi );
	}

	/**
	 * True se almeno un elemento di $lista contiene $sottostringa.
	 *
	 * @param array  $lista        Elenco di stringhe.
	 * @param string $sottostringa Sottostringa da cercare.
	 */
	private function contiene_sottostringa( array $lista, string $sottostringa ): bool {
		foreach ( $lista as $elemento ) {
			if ( str_contains( $elemento, $sottostringa ) ) {
				return true;
			}
		}
		return false;
	}
}
