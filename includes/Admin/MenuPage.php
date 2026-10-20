<?php
/**
 * Pagina admin del plugin (menu + caricamento della app React).
 *
 * @package PushContempt
 */

namespace PushContempt\Admin;

/**
 * Una sola pagina con tab interni, costruita con @wordpress/scripts - vedi
 * src/components/App.js. Qui solo registrazione menu e caricamento assets.
 */
class MenuPage {

	const HOOK_SUFFIX = 'toplevel_page_push-contempt';

	/**
	 * Aggancia i hook WordPress (chiamato una volta all'avvio del plugin).
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'aggiungi_voce_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'carica_assets' ) );

		$plugin_basename = plugin_basename( PUSH_CONTEMPT_DIR . 'push-contempt.php' );
		add_filter( "plugin_action_links_{$plugin_basename}", array( self::class, 'aggiungi_link_azioni' ) );
	}

	/**
	 * Aggiunge un link "Impostazioni" alla riga del plugin in
	 * wp-admin/plugins.php, cosi' la pagina e' raggiungibile anche da li'.
	 *
	 * @param array $links Link esistenti nella riga del plugin.
	 */
	public static function aggiungi_link_azioni( array $links ): array {
		$url = admin_url( 'admin.php?page=push-contempt' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">Impostazioni</a>' );
		return $links;
	}

	/**
	 * Registra la voce di menu in wp-admin.
	 */
	public static function aggiungi_voce_menu(): void {
		add_menu_page(
			'Push Contempt',
			'Push Contempt',
			'manage_woocommerce',
			'push-contempt',
			array( self::class, 'render' ),
			'dashicons-chart-bar'
		);
	}

	/**
	 * Carica JS/CSS solo sulla nostra pagina, non su tutto wp-admin.
	 *
	 * @param string $hook Hook suffix della pagina admin corrente.
	 */
	public static function carica_assets( string $hook ): void {
		if ( self::HOOK_SUFFIX !== $hook ) {
			return;
		}

		$percorso_asset = PUSH_CONTEMPT_DIR . 'build/index.asset.php';
		if ( ! file_exists( $percorso_asset ) ) {
			return;
		}
		$asset = require $percorso_asset;

		wp_enqueue_script(
			'push-contempt-admin',
			PUSH_CONTEMPT_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style( 'wp-components' );
	}

	/**
	 * Markup minimo: la app React fa il resto.
	 */
	public static function render(): void {
		echo '<div class="wrap"><h1>Push Contempt</h1><div id="push-contempt-admin-root"></div></div>';
	}
}
