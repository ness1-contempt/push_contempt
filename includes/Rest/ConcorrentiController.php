<?php
/**
 * Endpoint REST per le analisi SEO dei siti concorrenti.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use PushContempt\AnalisiConcorrenti;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Il server MCP analizza il sito sul PC dell'utente (Altervista blocca le
 * richieste in uscita, quindi il plugin non puo' farlo da solo) e ne manda qui
 * il riepilogo con POST; la tab SEO lo legge con GET e puo' toglierlo con
 * DELETE. Archivio in un'opzione WordPress non caricata a ogni pagina.
 */
class ConcorrentiController {

	const OPZIONE = 'pc_analisi_concorrenti';

	/**
	 * Registra la rotta REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/concorrenti',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'handle_elenco' ),
					'permission_callback' => array( self::class, 'permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'handle_salva' ),
					'permission_callback' => array( self::class, 'permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'handle_elimina' ),
					'permission_callback' => array( self::class, 'permission' ),
					'args'                => array(
						'sito' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Solo chi gestisce il negozio puo' usare questo endpoint.
	 */
	public static function permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * L'archivio salvato (vuoto se non c'e' ancora nulla).
	 */
	private static function archivio(): array {
		$archivio = get_option( self::OPZIONE, array() );
		return is_array( $archivio ) ? $archivio : array();
	}

	/**
	 * Tutte le analisi: il sito dell'utente per primo, poi i concorrenti.
	 */
	public static function handle_elenco(): WP_REST_Response {
		return new WP_REST_Response( AnalisiConcorrenti::elenco( self::archivio() ), 200 );
	}

	/**
	 * Salva (o aggiorna) l'analisi di un sito.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_salva( WP_REST_Request $request ) {
		$analisi = AnalisiConcorrenti::normalizza( (array) $request->get_json_params() );
		if ( null === $analisi ) {
			return new WP_Error(
				'push_contempt_analisi_non_valida',
				'Riepilogo non utilizzabile: servono "sito" e almeno un\'area con punteggio da 0 a 9.',
				array( 'status' => 400 )
			);
		}

		$archivio = AnalisiConcorrenti::aggiungi( self::archivio(), $analisi, gmdate( 'c' ) );
		update_option( self::OPZIONE, $archivio, false );

		return new WP_REST_Response(
			array(
				'sito'    => $analisi['sito'],
				'salvata' => true,
			),
			200
		);
	}

	/**
	 * Toglie un sito dall'archivio.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	public static function handle_elimina( WP_REST_Request $request ): WP_REST_Response {
		$sito     = sanitize_text_field( (string) $request->get_param( 'sito' ) );
		$archivio = self::archivio();
		$esisteva = isset( $archivio[ strtolower( $sito ) ] );
		update_option( self::OPZIONE, AnalisiConcorrenti::rimuovi( $archivio, $sito ), false );

		return new WP_REST_Response( array( 'eliminata' => $esisteva ), 200 );
	}
}
