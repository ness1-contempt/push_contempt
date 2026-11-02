<?php
/**
 * Dati strutturati schema.org "ProductGroup" per un prodotto variabile (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Google vuole che un prodotto con varianti sia un `ProductGroup` con dentro
 * un `Product` per ogni variante, ciascuno con la sua `Offer`
 * (https://developers.google.com/search/docs/appearance/structured-data/product-variants).
 * WooCommerce da solo scrive un unico `Product` con un `AggregateOffer`
 * (prezzo minimo e massimo): la pagina non dice quali varianti esistono.
 *
 * `gruppo()` parte dal markup di WooCommerce (nome, indirizzo, descrizione,
 * immagine, valutazioni, marca...) e lo trasforma. Se i dati delle varianti
 * non sono affidabili (prezzo mancante, combinazioni ripetute o "qualsiasi")
 * restituisce il markup invariato: un gruppo sbagliato e' peggio di nessuno.
 *
 * Ingresso `$varianti` (una riga per variante in vendita):
 * `id`, `url`, `nome`, `sku`, `descrizione`, `immagine`, `gtin`, `prezzo`,
 * `prezzo_listino` ('' se non e' in offerta), `valuta`, `iva_inclusa` (bool o
 * null se le imposte sono spente), `disponibilita` (InStock, OutOfStock,
 * BackOrder), `valido_fino`, `attributi` (etichetta => valore), `venditore`
 * (`nome`, `url`).
 */
class ProductGroupSchema {

	/**
	 * Proprieta' schema.org che Google riconosce per `variesBy`, per etichetta dell'attributo.
	 */
	const PROPRIETA = array(
		'color'    => array( 'colore', 'colori', 'color', 'colour', 'tinta' ),
		'size'     => array( 'taglia', 'taglie', 'size', 'misura', 'dimensione', 'dimensioni' ),
		'material' => array( 'materiale', 'material', 'tessuto' ),
		'pattern'  => array( 'fantasia', 'pattern', 'motivo', 'stampa' ),
	);

	/**
	 * Trasforma il markup di un variabile in un ProductGroup, se i dati lo permettono.
	 *
	 * @param array  $markup    Markup `Product` di WooCommerce.
	 * @param string $gruppo_id Identificativo del gruppo (SKU del padre o suo id).
	 * @param array  $varianti  Le varianti in vendita (vedi la descrizione della classe).
	 */
	public static function gruppo( array $markup, string $gruppo_id, array $varianti ): array {
		if ( ! self::affidabile( $varianti ) ) {
			return $markup;
		}

		unset( $markup['offers'], $markup['sku'], $markup['gtin'] );
		$markup['@type']          = 'ProductGroup';
		$markup['productGroupID'] = $gruppo_id;
		$markup['variesBy']       = self::varia_per( $varianti );
		$markup['hasVariant']     = array_map(
			static fn( array $variante ): array => self::variante( $variante, $gruppo_id ),
			$varianti
		);
		return $markup;
	}

	/**
	 * Se si puo' descrivere ogni variante senza ambiguita'.
	 *
	 * @param array $varianti Le varianti in vendita.
	 */
	private static function affidabile( array $varianti ): bool {
		if ( ! $varianti ) {
			return false;
		}
		$viste = array();
		foreach ( $varianti as $variante ) {
			if ( ! is_numeric( $variante['prezzo'] ) || ! $variante['attributi'] ) {
				return false;
			}
			foreach ( $variante['attributi'] as $valore ) {
				if ( '' === trim( (string) $valore ) ) {
					return false;
				}
			}
			$chiave = implode( "\x1f", array_map( 'mb_strtolower', array_map( 'trim', array_map( 'strval', $variante['attributi'] ) ) ) );
			if ( isset( $viste[ $chiave ] ) ) {
				return false;
			}
			$viste[ $chiave ] = true;
		}
		return true;
	}

	/**
	 * Gli attributi per cui le varianti differiscono: URL schema.org dove Google ne conosce uno, altrimenti l'etichetta.
	 *
	 * @param array $varianti Le varianti.
	 *
	 * @return string[]
	 */
	private static function varia_per( array $varianti ): array {
		$varia_per = array();
		foreach ( array_keys( $varianti[0]['attributi'] ) as $etichetta ) {
			$proprieta   = self::proprieta( (string) $etichetta );
			$varia_per[] = $proprieta ? 'https://schema.org/' . $proprieta : (string) $etichetta;
		}
		return $varia_per;
	}

	/**
	 * La proprieta' schema.org dell'attributo (color, size...), o null se non ne ha una.
	 *
	 * @param string $etichetta Etichetta dell'attributo ("Colore").
	 */
	public static function proprieta( string $etichetta ): ?string {
		$normale = trim( mb_strtolower( $etichetta ) );
		foreach ( self::PROPRIETA as $proprieta => $nomi ) {
			if ( in_array( $normale, $nomi, true ) ) {
				return $proprieta;
			}
		}
		return null;
	}

	/**
	 * Il `Product` di una variante con la sua `Offer`.
	 *
	 * @param array  $variante  Riga della variante.
	 * @param string $gruppo_id Identificativo del gruppo.
	 */
	private static function variante( array $variante, string $gruppo_id ): array {
		$markup = array(
			'@type'                => 'Product',
			'@id'                  => $variante['url'] . '#variant-' . $variante['id'],
			'name'                 => $variante['nome'],
			'url'                  => $variante['url'],
			'sku'                  => $variante['sku'],
			'inProductGroupWithID' => $gruppo_id,
		);
		if ( '' !== $variante['descrizione'] ) {
			$markup['description'] = $variante['descrizione'];
		}
		if ( '' !== $variante['immagine'] ) {
			$markup['image'] = $variante['immagine'];
		}
		if ( self::gtin_valido( $variante['gtin'] ) ) {
			$markup['gtin'] = $variante['gtin'];
		}

		$altre = array();
		foreach ( $variante['attributi'] as $etichetta => $valore ) {
			$proprieta = self::proprieta( (string) $etichetta );
			if ( $proprieta ) {
				$markup[ $proprieta ] = $valore;
			} else {
				$altre[] = array(
					'@type' => 'PropertyValue',
					'name'  => (string) $etichetta,
					'value' => $valore,
				);
			}
		}
		if ( $altre ) {
			$markup['additionalProperty'] = $altre;
		}

		$markup['offers'] = self::offerta( $variante );
		return $markup;
	}

	/**
	 * L'`Offer` di una variante, nello stesso stile di quella di WooCommerce.
	 *
	 * @param array $variante Riga della variante.
	 */
	private static function offerta( array $variante ): array {
		$specifica = static function ( string $prezzo, ?string $tipo ) use ( $variante ): array {
			$riga = array(
				'@type'         => 'UnitPriceSpecification',
				'price'         => $prezzo,
				'priceCurrency' => $variante['valuta'],
				'validThrough'  => $variante['valido_fino'],
			);
			if ( null !== $variante['iva_inclusa'] ) {
				$riga['valueAddedTaxIncluded'] = (bool) $variante['iva_inclusa'];
			}
			if ( $tipo ) {
				$riga['priceType'] = $tipo;
			}
			return $riga;
		};

		$specifiche = array( $specifica( $variante['prezzo'], null ) );
		if ( '' !== $variante['prezzo_listino'] ) {
			// Solo il prezzo non in vigore porta il tipo (regola di Google per i prezzi scontati).
			$specifiche[] = $specifica( $variante['prezzo_listino'], 'https://schema.org/ListPrice' );
		}

		return array(
			'@type'              => 'Offer',
			'price'              => $variante['prezzo'],
			'priceCurrency'      => $variante['valuta'],
			'priceSpecification' => $specifiche,
			'priceValidUntil'    => $variante['valido_fino'],
			'availability'       => 'https://schema.org/' . $variante['disponibilita'],
			'url'                => $variante['url'],
			'seller'             => array(
				'@type' => 'Organization',
				'name'  => $variante['venditore']['nome'],
				'url'   => $variante['venditore']['url'],
			),
		);
	}

	/**
	 * Se e' un GTIN plausibile (8, 12, 13 o 14 cifre).
	 *
	 * @param string $gtin Codice.
	 */
	private static function gtin_valido( string $gtin ): bool {
		return 1 === preg_match( '/^(\d{8}|\d{12}|\d{13}|\d{14})$/', $gtin );
	}
}
