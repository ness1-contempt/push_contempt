<?php
/**
 * Base dei test dei controller REST: utente con i permessi del negozio e richieste interne.
 *
 * @package PushContempt
 */

namespace PushContempt\Tests\Integration;

use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * Le richieste passano da rest_do_request(): stessa registrazione delle rotte,
 * stessi permessi e stessa validazione degli argomenti di una chiamata HTTP vera.
 */
abstract class RestTestCase extends WP_UnitTestCase {

	const BASE = '/push-contempt/v1';

	/**
	 * Parte come gestore del negozio (shop_manager ha manage_woocommerce).
	 */
	public function set_up(): void {
		parent::set_up();
		$this->accedi_come( 'shop_manager' );
	}

	/**
	 * Cambia l'utente corrente (ruolo vuoto = nessun utente).
	 *
	 * @param string $ruolo Ruolo WordPress, o stringa vuota per un visitatore.
	 */
	protected function accedi_come( string $ruolo ): void {
		wp_set_current_user( '' === $ruolo ? 0 : self::factory()->user->create( array( 'role' => $ruolo ) ) );
	}

	/**
	 * Esegue una richiesta REST sul namespace del plugin.
	 *
	 * @param string $metodo  GET, POST, DELETE.
	 * @param string $rotta   Rotta senza prefisso (es. "/seo/prodotto").
	 * @param array  $params  Parametri (query per GET/DELETE, corpo per POST).
	 * @param bool   $in_json Se true il corpo di un POST e' JSON (come lo manda il server MCP).
	 */
	protected function richiesta( string $metodo, string $rotta, array $params = array(), bool $in_json = false ): WP_REST_Response {
		$richiesta = new WP_REST_Request( $metodo, self::BASE . $rotta );
		if ( 'POST' === $metodo && $in_json ) {
			$richiesta->set_header( 'content-type', 'application/json' );
			$richiesta->set_body( wp_json_encode( $params ) );
		} elseif ( 'POST' === $metodo ) {
			$richiesta->set_body_params( $params );
		} else {
			$richiesta->set_query_params( $params );
		}
		return rest_do_request( $richiesta );
	}

	/**
	 * Un variabile Colore x Taglia con una variante per combinazione.
	 *
	 * @param array $prezzi Prezzo delle varianti nell'ordine Nero-M, Nero-L, Grigio-M, Grigio-L.
	 * @param array $testo  Descrizione e descrizione breve.
	 */
	protected function felpa( array $prezzi = array( '30', '30', '35', '35' ), string $testo = 'Felpa nero e grigio, taglie M e L.' ): \WC_Product_Variable {
		$colore = new \WC_Product_Attribute();
		$colore->set_name( 'Colore' );
		$colore->set_options( array( 'Nero', 'Grigio' ) );
		$colore->set_visible( true );
		$colore->set_variation( true );
		$taglia = new \WC_Product_Attribute();
		$taglia->set_name( 'Taglia' );
		$taglia->set_options( array( 'M', 'L' ) );
		$taglia->set_visible( true );
		$taglia->set_variation( true );

		$padre = new \WC_Product_Variable();
		$padre->set_name( 'Felpa' );
		$padre->set_status( 'publish' );
		$padre->set_description( $testo );
		$padre->set_attributes( array( $colore, $taglia ) );
		$id = $padre->save();

		$i = 0;
		foreach ( array( 'Nero', 'Grigio' ) as $c ) {
			foreach ( array( 'M', 'L' ) as $t ) {
				$variante = new \WC_Product_Variation();
				$variante->set_parent_id( $id );
				$variante->set_attributes( array( 'colore' => $c, 'taglia' => $t ) );
				if ( '' !== $prezzi[ $i ] ) {
					$variante->set_regular_price( $prezzi[ $i ] );
				}
				$variante->set_status( 'publish' );
				$variante->save();
				++$i;
			}
		}
		\WC_Product_Variable::sync( $id );
		return wc_get_product( $id );
	}

	/**
	 * Un prodotto semplice, con eventuali campi SEO Yoast gia' impostati.
	 *
	 * @param array  $yoast Campi (titolo_seo, meta_description, parola_chiave_focus).
	 * @param string $stato Stato del prodotto.
	 */
	protected function prodotto( array $yoast = array(), string $stato = 'publish' ): int {
		$prodotto = new \WC_Product_Simple();
		$prodotto->set_name( 'Prodotto di prova' );
		$prodotto->set_status( $stato );
		$prodotto->set_regular_price( '10' );
		$id = $prodotto->save();
		foreach ( $yoast as $campo => $valore ) {
			update_post_meta( $id, \PushContempt\Rest\SeoProdottoController::CAMPI_YOAST[ $campo ], $valore );
		}
		return $id;
	}

	/**
	 * I tre campi SEO vivi di un prodotto.
	 *
	 * @param int $id Id del prodotto.
	 */
	protected function seo_vivo( int $id ): array {
		$vivo = array();
		foreach ( \PushContempt\Rest\SeoProdottoController::CAMPI_YOAST as $campo => $meta_key ) {
			$vivo[ $campo ] = (string) get_post_meta( $id, $meta_key, true );
		}
		return $vivo;
	}

	/**
	 * Tre valori SEO di esempio, distinguibili dal suffisso.
	 *
	 * @param string $suffisso Testo che li rende diversi da altri insiemi.
	 */
	protected function valori( string $suffisso ): array {
		return array(
			'titolo_seo'          => "Titolo {$suffisso}",
			'meta_description'    => "Meta {$suffisso}",
			'parola_chiave_focus' => "kw {$suffisso}",
		);
	}
}
