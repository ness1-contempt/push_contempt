<?php
/**
 * Configurazione della suite di test di WordPress (wp-phpunit).
 *
 * Tutto arriva da variabili d'ambiente. Il database di prova viene svuotato e
 * ricreato a ogni esecuzione: per sicurezza si rifiuta qualunque nome che non
 * contenga "test", cosi' non si puo' puntare per sbaglio al database del sito.
 *
 * @package PushContempt
 */

$pc_db_nome = getenv( 'WP_TESTS_DB_NAME' ) ? getenv( 'WP_TESTS_DB_NAME' ) : 'wordpress_test';
if ( false === strpos( $pc_db_nome, 'test' ) ) {
	fwrite( STDERR, "Rifiuto di usare il database '{$pc_db_nome}': il nome deve contenere 'test'.\n" );
	exit( 1 );
}

define( 'ABSPATH', rtrim( getenv( 'WP_TESTS_WP_DIR' ) ? getenv( 'WP_TESTS_WP_DIR' ) : '/tmp/wordpress', '/' ) . '/' );

define( 'DB_NAME', $pc_db_nome );
define( 'DB_USER', getenv( 'WP_TESTS_DB_USER' ) ? getenv( 'WP_TESTS_DB_USER' ) : 'root' );
define( 'DB_PASSWORD', false !== getenv( 'WP_TESTS_DB_PASSWORD' ) ? getenv( 'WP_TESTS_DB_PASSWORD' ) : '' );
define( 'DB_HOST', getenv( 'WP_TESTS_DB_HOST' ) ? getenv( 'WP_TESTS_DB_HOST' ) : '127.0.0.1' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Push Contempt test' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
