<?php
/**
 * Endpoint REST per generare le credenziali di collegamento a push_contempt_mcp.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use WP_Application_Passwords;
use WP_Error;
use WP_REST_Response;

/**
 * Crea una Application Password nativa di WordPress per l'utente corrente,
 * cosi' il cliente non deve passare da wp-admin -> Profilo -> Password per
 * le applicazioni ne' da un terminale: un solo click qui genera tutto cio'
 * che serve per configurare push_contempt_mcp (WP_SITE_URL/WP_APP_USER/
 * WP_APP_PASSWORD). A differenza dei token JWT usati da altri server MCP per
 * WordPress, le Application Password non scadono da sole - restano valide
 * finche' non vengono rigenerate o revocate.
 */
class CredenzialiController {

	const NOME_APP_PASSWORD = 'push_contempt_mcp';

	/**
	 * Registra la rotta REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/credenziali',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => array( self::class, 'permission' ),
			)
		);
	}

	/**
	 * Solo chi gestisce il negozio puo' generare credenziali di collegamento.
	 */
	public static function permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Rigenera (elimina le precedenti e ne crea una nuova) la Application
	 * Password dedicata a push_contempt_mcp per l'utente corrente.
	 */
	public static function handle(): WP_REST_Response|WP_Error {
		if ( ! wp_is_application_passwords_available() ) {
			return new WP_Error(
				'push_contempt_app_password_non_disponibile',
				"Le Application Password di WordPress non sono attive su questo sito (di solito serve HTTPS). Contatta chi gestisce l'hosting.",
				array( 'status' => 400 )
			);
		}

		$user_id = get_current_user_id();

		// Rigenerazione pulita: elimina le credenziali push_contempt_mcp create
		// in precedenza, cosi' il bottone "Genera" funziona anche da "Rigenera"
		// senza accumulare voci inutilizzate nel profilo dell'utente.
		foreach ( WP_Application_Passwords::get_user_application_passwords( $user_id ) as $esistente ) {
			if ( self::NOME_APP_PASSWORD === $esistente['name'] ) {
				WP_Application_Passwords::delete_application_password( $user_id, $esistente['uuid'] );
			}
		}

		$risultato = WP_Application_Passwords::create_new_application_password(
			$user_id,
			array( 'name' => self::NOME_APP_PASSWORD )
		);
		if ( is_wp_error( $risultato ) ) {
			return $risultato;
		}

		list( $password_in_chiaro, ) = $risultato;
		$utente                      = wp_get_current_user();

		return new WP_REST_Response(
			array(
				'sito'     => untrailingslashit( site_url() ),
				'utente'   => $utente->user_login,
				// Mostrata una sola volta: WordPress salva solo l'hash, non il
				// valore in chiaro - se il cliente la perde deve rigenerarla.
				'password' => $password_in_chiaro,
			),
			200
		);
	}
}
