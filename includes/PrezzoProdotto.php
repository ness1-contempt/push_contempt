<?php
/**
 * Tipo e prezzo di un prodotto WooCommerce, anche variabile o raggruppato.
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Un prodotto variabile non ha un prezzo suo: ce l'hanno le varianti, e
 * WooCommerce salva sul padre sia il minimo sia il massimo come due righe
 * `_price`. Leggere quel meta con una query (MAX o la prima riga) da' un
 * numero diverso a seconda di come si legge, ed e' proprio il "29 contro 20"
 * visto sul prodotto 581. Qui si usano le API native, le stesse con cui il
 * negozio compone "da X a Y": `get_variation_price()` per i variabili (conta
 * solo le varianti pubblicate, e quelle disponibili se il negozio nasconde gli
 * esauriti) e il prezzo dei figli per i raggruppati.
 *
 * Risposta, uguale per ogni tipo:
 * - `tipo`: simple, variable, grouped, external...
 * - `prezzo`: il prezzo, solo dove ce n'e' uno solo (semplice, esterno); null
 *   per variabili e raggruppati, che hanno un intervallo.
 * - `prezzo_min` / `prezzo_max`: gli estremi (uguali per un prodotto semplice);
 *   null se non c'e' nessun prezzo.
 * - `numero_varianti`: quante varianti ha un variabile (anche non in vendita);
 *   null per gli altri tipi.
 *
 * I prezzi sono stringhe come in WooCommerce, senza imposte (come inseriti).
 */
class PrezzoProdotto {

	/**
	 * Tipo e prezzi del prodotto.
	 *
	 * @param \WC_Product $prodotto Il prodotto.
	 */
	public static function di( \WC_Product $prodotto ): array {
		$tipo = $prodotto->get_type();

		if ( $prodotto instanceof \WC_Product_Variable ) {
			return array(
				'tipo'            => $tipo,
				'prezzo'          => null,
				'prezzo_min'      => self::o_null( $prodotto->get_variation_price( 'min' ) ),
				'prezzo_max'      => self::o_null( $prodotto->get_variation_price( 'max' ) ),
				'numero_varianti' => count( $prodotto->get_children() ),
			);
		}

		if ( $prodotto instanceof \WC_Product_Grouped ) {
			$prezzi = array();
			foreach ( $prodotto->get_children() as $figlio_id ) {
				$figlio = wc_get_product( $figlio_id );
				if ( $figlio && 'publish' === $figlio->get_status() && '' !== (string) $figlio->get_price() ) {
					$prezzi[ $figlio->get_price() ] = (float) $figlio->get_price();
				}
			}
			return array(
				'tipo'            => $tipo,
				'prezzo'          => null,
				'prezzo_min'      => $prezzi ? (string) array_search( min( $prezzi ), $prezzi, true ) : null,
				'prezzo_max'      => $prezzi ? (string) array_search( max( $prezzi ), $prezzi, true ) : null,
				'numero_varianti' => null,
			);
		}

		$prezzo = self::o_null( $prodotto->get_price() );
		return array(
			'tipo'            => $tipo,
			'prezzo'          => $prezzo,
			'prezzo_min'      => $prezzo,
			'prezzo_max'      => $prezzo,
			'numero_varianti' => null,
		);
	}

	/**
	 * Stringa vuota (nessun prezzo) come null, altrimenti il prezzo come stringa.
	 *
	 * @param mixed $valore Prezzo letto da WooCommerce.
	 */
	private static function o_null( $valore ): ?string {
		return '' === (string) $valore ? null : (string) $valore;
	}
}
