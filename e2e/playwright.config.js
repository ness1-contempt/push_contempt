// @ts-check
const { defineConfig, devices } = require( '@playwright/test' );

/**
 * Test end-to-end della tab SEO su un sito WordPress di prova (vedi e2e/README.md e
 * e2e/site.sh). Un solo lavoratore: i test condividono lo stesso sito e il suo database.
 */
const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8081';

module.exports = defineConfig( {
	testDir: './tests',
	globalSetup: require.resolve( './global-setup.js' ),
	workers: 1,
	fullyParallel: false,
	timeout: 30_000,
	expect: { timeout: 8_000 },
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [ [ 'list' ], [ 'html', { open: 'never' } ] ] : 'list',
	use: {
		baseURL,
		storageState: '.auth/admin.json',
		locale: 'it-IT',
		permissions: [ 'clipboard-read', 'clipboard-write' ],
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: {
				...devices[ 'Desktop Chrome' ],
			},
		},
	],
} );
