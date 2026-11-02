<?php
/**
 * Attributi di variazione di un prodotto variabile, con nomi leggibili.
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Un attributo puo' essere globale (taxonomy `pa_colore`, valori = slug dei
 * termini) o personalizzato del prodotto (nome e valori in chiaro). Qui si
 * riporta tutto a etichetta e valori leggibili ("Colore": "Nero"), che e' cio'
 * che serve sia all'analisi SEO sia ai dati strutturati.
 */
class AttributiVarianti {

	/**
	 * Gli attributi usati per le varianti: etichetta => valori e visibilita' nella scheda.
	 *
	 * @param \WC_Product_Variable $prodotto Il padre.
	 *
	 * @return array<string, array{valori: string[], visibile: bool}>
	 */
	public static function del_padre( \WC_Product_Variable $prodotto ): array {
		$attributi = array();
		foreach ( $prodotto->get_attributes() as $attributo ) {
			if ( ! $attributo->get_variation() ) {
				continue;
			}
			$attributi[ wc_attribute_label( $attributo->get_name(), $prodotto ) ] = array(
				'valori'   => self::valori( $attributo ),
				'visibile' => (bool) $attributo->get_visible(),
			);
		}
		return $attributi;
	}

	/**
	 * Attributi di una variante: etichetta => valore ("" se "qualsiasi").
	 *
	 * @param \WC_Product_Variation $variante Variante.
	 * @param \WC_Product_Variable  $padre    Il padre.
	 *
	 * @return array<string, string>
	 */
	public static function della_variante( \WC_Product_Variation $variante, \WC_Product_Variable $padre ): array {
		$risultato = array();
		foreach ( $variante->get_attributes() as $chiave => $valore ) {
			$valore = (string) $valore;
			if ( '' !== $valore && taxonomy_exists( $chiave ) ) {
				$termine = get_term_by( 'slug', $valore, $chiave );
				$valore  = $termine ? (string) $termine->name : $valore;
			}
			$risultato[ wc_attribute_label( $chiave, $padre ) ] = $valore;
		}
		return $risultato;
	}

	/**
	 * Valori di un attributo del padre (nomi dei termini se e' globale).
	 *
	 * @param \WC_Product_Attribute $attributo Attributo.
	 *
	 * @return string[]
	 */
	private static function valori( \WC_Product_Attribute $attributo ): array {
		if ( $attributo->is_taxonomy() ) {
			return array_map(
				static fn( $termine ): string => (string) $termine->name,
				(array) $attributo->get_terms()
			);
		}
		return array_map( 'strval', $attributo->get_options() );
	}
}
