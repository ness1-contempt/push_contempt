const { test, expect, apriTabSeo, nome, riga } = require( './helpers' );

test.describe( 'SEO dei prodotti variabili', () => {
	test( 'la riga di un variabile riassume i problemi delle varianti e le mostra a richiesta', async ( { page, api } ) => {
		const titolo = nome( 'Felpa' );
		// La seconda variante non ha prezzo: e' un errore. Nessuna ha SKU o immagine propria: suggerimenti.
		const id = await api.creaVariabile( titolo, [ '20', '' ] );

		await apriTabSeo( page );
		const rigaProdotto = riga( page, titolo );
		await expect( rigaProdotto ).toContainText( '1 errore' );
		await expect( rigaProdotto ).toContainText( /\d suggerimenti/ );

		await rigaProdotto.getByRole( 'button', { name: 'Mostra varianti' } ).click();
		await expect( page.getByText( 'Varianti pubblicate senza prezzo' ) ).toBeVisible();
		await expect( page.getByRole( 'cell', { name: 'Taglia: T2', exact: true } ) ).toBeVisible();
		await expect( page.getByText( 'Mancante' ).last() ).toBeVisible();

		// L'interfaccia mostra quello che dice il plugin.
		const dati = await api.get( '/push-contempt/v1/seo/varianti', { id: String( id ) } );
		expect( dati.conteggio.errori ).toBe( 1 );
		expect( dati.varianti ).toHaveLength( 2 );

		await rigaProdotto.getByRole( 'button', { name: 'Nascondi varianti' } ).click();
		await expect( page.getByText( 'Varianti pubblicate senza prezzo' ) ).toBeHidden();
	} );

	test( 'il filtro «Variabili con problemi» tiene i variabili da controllare e toglie gli altri', async ( { page, api } ) => {
		const daControllare = nome( 'ConProblemi' );
		const semplice = nome( 'Semplice' );
		await api.creaVariabile( daControllare, [ '20', '' ] );
		await api.creaProdotto( semplice );

		await apriTabSeo( page );
		await expect( riga( page, semplice ) ).toBeVisible();
		await page.getByLabel( 'Mostra' ).selectOption( 'varianti_con_problemi' );

		await expect( riga( page, daControllare ) ).toBeVisible();
		await expect( riga( page, semplice ) ).toHaveCount( 0 );
	} );

	test( 'un prodotto semplice non mostra il riepilogo delle varianti', async ( { page, api } ) => {
		const titolo = nome( 'Semplice' );
		await api.creaProdotto( titolo );

		await apriTabSeo( page );

		await expect( riga( page, titolo ) ).not.toContainText( 'Mostra varianti' );
		await expect( riga( page, titolo ) ).not.toContainText( 'Varianti a posto' );
	} );

	test( 'l\'interruttore dei dati strutturati si spegne, si riaccende e il sito lo ricorda', async ( { page, api } ) => {
		await apriTabSeo( page );
		const interruttore = page.getByLabel( 'Dati strutturati dei prodotti variabili' );
		await expect( interruttore ).toBeChecked();

		await interruttore.click();
		await expect( interruttore ).not.toBeChecked();
		expect( ( await api.get( '/push-contempt/v1/seo/schema-varianti' ) ).attivo ).toBe( false );

		await page.reload();
		await page.getByRole( 'tab', { name: 'SEO' } ).click();
		await expect( page.getByLabel( 'Dati strutturati dei prodotti variabili' ) ).not.toBeChecked();

		await page.getByLabel( 'Dati strutturati dei prodotti variabili' ).click();
		await expect( page.getByLabel( 'Dati strutturati dei prodotti variabili' ) ).toBeChecked();
		expect( ( await api.get( '/push-contempt/v1/seo/schema-varianti' ) ).attivo ).toBe( true );
	} );
} );
