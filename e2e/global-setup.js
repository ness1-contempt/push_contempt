// @ts-check
const { chromium } = require( '@playwright/test' );

/**
 * Entra una volta sola come amministratore e salva la sessione per tutti i test.
 */
module.exports = async ( config ) => {
	const { baseURL } = config.projects[ 0 ].use;
	const browser = await chromium.launch();
	const page = await browser.newPage( { baseURL } );
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', process.env.E2E_ADMIN_USER || 'admin' );
	await page.fill( '#user_pass', process.env.E2E_ADMIN_PASSWORD || 'e2e-password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/, { waitUntil: 'commit' } );
	await page.context().storageState( { path: '.auth/admin.json' } );
	await browser.close();
};
