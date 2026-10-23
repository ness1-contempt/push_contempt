<?php
/**
 * Plugin Name:       Push Contempt
 * Description:       Strumenti WooCommerce: scorte, prezzi in blocco, report vendite, salute catalogo, SEO, caricamento prodotti (con assistente AI via MCP).
 * Version:           0.1.13
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            ness1-contempt
 * Author URI:        https://github.com/ness1-contempt
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       push-contempt
 *
 * @package PushContempt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Niente accesso diretto al file.
}

define( 'PUSH_CONTEMPT_VERSION', '0.1.13' );
define( 'PUSH_CONTEMPT_DIR', plugin_dir_path( __FILE__ ) );
define( 'PUSH_CONTEMPT_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( PUSH_CONTEMPT_DIR . 'vendor/autoload.php' ) ) {
	require_once PUSH_CONTEMPT_DIR . 'vendor/autoload.php';
}

add_action(
	'rest_api_init',
	function () {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return; // WooCommerce non attivo.
		}
		\PushContempt\Rest\ScorteController::register();
		\PushContempt\Rest\VenditeController::register();
		\PushContempt\Rest\SaluteCatalogoController::register();
		\PushContempt\Rest\PrezziController::register();
		\PushContempt\Rest\SeoStatoController::register();
		\PushContempt\Rest\CredenzialiController::register();
		\PushContempt\Rest\SeoProdottoController::register();
		\PushContempt\Rest\ConcorrentiController::register();
		\PushContempt\Rest\ProdottiController::register();
		\PushContempt\Rest\OrdiniController::register();
	}
);

// Le risposte REST del plugin sono dati vivi (stato SEO, analisi, ripristini): una cache di
// pagina (LiteSpeed ne tiene anche quelle REST per giorni) mostrerebbe valori vecchi.
add_filter(
	'rest_post_dispatch',
	function ( $risposta, $server, $richiesta ) {
		if ( 0 === strpos( $richiesta->get_route(), '/push-contempt/v1' ) ) {
			do_action( 'litespeed_control_set_nocache', 'push_contempt REST' );
			$risposta->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
		}
		return $risposta;
	},
	10,
	3
);

\PushContempt\Admin\MenuPage::register();
