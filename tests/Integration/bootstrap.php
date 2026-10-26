<?php
/**
 * Bootstrap dei test di integrazione: WordPress e WooCommerce veri, database di prova.
 *
 * Prerequisiti (vedi README, sezione Test): WordPress e WooCommerce scaricati con
 * bin/install-wp-tests.sh, un database vuoto il cui nome contiene "test", e le variabili
 * WP_TESTS_WP_DIR e WP_TESTS_DB_* (nomi in wp-tests-config.php).
 *
 * @package PushContempt
 */

$pc_radice = dirname( __DIR__, 2 );

require_once $pc_radice . '/vendor/autoload.php';

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );

$pc_libreria = $pc_radice . '/vendor/wp-phpunit/wp-phpunit';
require_once $pc_libreria . '/includes/functions.php';

// WooCommerce e il plugin si caricano prima dell'avvio di WordPress.
tests_add_filter(
	'muplugins_loaded',
	function () use ( $pc_radice ) {
		require_once ABSPATH . 'wp-content/plugins/woocommerce/woocommerce.php';
		require_once $pc_radice . '/push-contempt.php';
	}
);

// Tabelle e ruoli di WooCommerce (il ruolo shop_manager ha il permesso manage_woocommerce).
tests_add_filter(
	'setup_theme',
	function () {
		WC_Install::install();
		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_roles();
	}
);

require $pc_libreria . '/includes/bootstrap.php';
