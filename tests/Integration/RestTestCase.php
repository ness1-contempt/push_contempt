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
