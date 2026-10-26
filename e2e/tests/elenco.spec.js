const { test, expect, nome, apriTabSeo, riga } = require( './helpers' );

test.describe( 'Tab SEO: elenco prodotti', () => {
	test( 'mostra pubblicati e bozze, con i campi SEO mancanti segnalati', async ( { page, api } ) => {
		const pubblicato = nome( 'Pubblicato' );
		const bozza = nome( 'Bozza' );
		await api.creaProdotto( pubblicato );
		await api.creaProdotto( bozza, 'draft' );

		await apriTabSeo( page );

		await expect( riga( page, pubblicato ) ).toContainText( 'Pubblicato' );
		await expect( riga( page, bozza ) ).toContainText( 'Bozza' );
		await expect( riga( page, pubblicato ).getByText( 'Mancante' ) ).toHaveCount( 2 );
		await expect( riga( page, pubblicato ) ).toContainText( '—' ); // parola chiave focus e versioni
	} );

	test( 'il filtro "In bozza" nasconde i pubblicati', async ( { page, api } ) => {
		const pubblicato = nome( 'Pubblicato' );
		const bozza = nome( 'Bozza' );
		await api.creaProdotto( pubblicato );
		await api.creaProdotto( bozza, 'draft' );
		await apriTabSeo( page );

		await page.getByLabel( 'Mostra' ).selectOption( 'bozza' );

		await expect( riga( page, bozza ) ).toBeVisible();
		await expect( riga( page, pubblicato ) ).toHaveCount( 0 );
	} );

	test( 'dopo una scrittura i campi non risultano più mancanti', async ( { page, api } ) => {
		const titolo = nome( 'Scritto' );
		const id = await api.creaProdotto( titolo );
		await api.scriviSeo( id, { titolo_seo: 'Un titolo di 22 caratteri', meta_description: 'Una meta description', parola_chiave_focus: 'kw scritto' } );

		await apriTabSeo( page );

		await expect( riga( page, titolo ) ).toContainText( '25 caratteri' );
		await expect( riga( page, titolo ) ).toContainText( '20 caratteri' );
		await expect( riga( page, titolo ) ).toContainText( 'kw scritto' );
		await expect( riga( page, titolo ).getByText( 'Mancante' ) ).toHaveCount( 0 );
	} );

	test( '"Prepara messaggio" produce il messaggio per l\'assistente con id e parola chiave', async ( { page, api } ) => {
		const titolo = nome( 'Messaggio' );
		const id = await api.creaProdotto( titolo );
		await apriTabSeo( page );

		await riga( page, titolo ).getByRole( 'button', { name: 'Prepara messaggio' } ).click();

		const messaggio = page.locator( 'pre' ).filter( { hasText: `(ID ${ id })` } );
		await expect( messaggio ).toContainText( `Scrivi un titolo SEO e una meta description` );
		await expect( messaggio ).toContainText( titolo );
		await expect( messaggio ).toContainText( 'parola chiave focus attuale: nessuna' );
	} );

	test( 'le guide sono chiuse all\'inizio e si aprono con un clic', async ( { page } ) => {
		await apriTabSeo( page );
		const guida = page.locator( 'details' ).filter( { hasText: 'Come funzionano le versioni' } );

		await expect( guida ).not.toHaveAttribute( 'open', '' );
		await guida.getByText( 'Come funzionano le versioni' ).click();

		await expect( guida ).toHaveAttribute( 'open', '' );
		await expect( guida.getByText( 'tre versioni', { exact: true } ) ).toBeVisible();
	} );

	test( 'chi non gestisce il negozio non può aprire la pagina', async ( { api, browser, baseURL } ) => {
		const utente = nome( 'abbonato' ).replace( /\s/g, '' ).toLowerCase();
		await api.post( '/wp/v2/users', { username: utente, email: `${ utente }@example.org`, password: 'Password-di-prova-1!', roles: [ 'subscriber' ] } );
		const contesto = await browser.newContext( { baseURL, storageState: { cookies: [], origins: [] } } );
		const pagina = await contesto.newPage();
		await pagina.goto( '/wp-login.php' );
		await pagina.fill( '#user_login', utente );
		await pagina.fill( '#user_pass', 'Password-di-prova-1!' );
		await pagina.click( '#wp-submit' );
		// WooCommerce manda chi non e' amministratore a "Il mio account", non a wp-admin.
		await pagina.waitForURL( ( url ) => ! url.pathname.includes( 'wp-login.php' ), { waitUntil: 'commit' } );

		await pagina.goto( '/wp-admin/admin.php?page=push-contempt' );

		await expect( pagina.getByText( /not allowed|non hai i permessi|non sei autorizzato/i ) ).toBeVisible();
		await expect( pagina.getByRole( 'tab', { name: 'SEO' } ) ).toHaveCount( 0 );
		await contesto.close();
	} );
} );
