<?php
/**
 * Aggiornamento del plugin da wp-admin, leggendo le release GitHub.
 *
 * @package PushContempt
 */

namespace PushContempt;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Usa la libreria Plugin Update Checker: controlla le release del repo
 * pubblico e, se ce n'e' una piu' recente, la propone nella pagina Plugin
 * di WordPress come un normale aggiornamento.
 */
class Updater {

	const REPO_URL = 'https://github.com/ness1-contempt/push_contempt/';

	/**
	 * Cartella del plugin: lo zip della release la contiene con questo nome,
	 * e l'aggiornamento deve lasciarla invariata.
	 */
	const SLUG = 'push_contempt';

	/**
	 * Aggancia il controllo aggiornamenti (chiamato una volta all'avvio).
	 */
	public static function register(): void {
		if ( ! class_exists( PucFactory::class ) ) {
			return; // Installazione da sorgente senza `composer install`.
		}

		$checker = PucFactory::buildUpdateChecker( self::REPO_URL, PUSH_CONTEMPT_DIR . 'push-contempt.php', self::SLUG );

		// Lo zip compilato (con build/ e vendor/) e' l'allegato della release,
		// non l'archivio sorgente generato da GitHub.
		$checker->getVcsApi()->enableReleaseAssets( '/push_contempt-[\d.]+\.zip($|[?&#])/i' );
	}
}
