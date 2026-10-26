const { test, expect, nome, valori, apriTabSeo, riga, scheda, avviso } = require( './helpers' );

/**
 * Un prodotto con due scritture: base vuota (il prodotto non aveva campi Yoast),
 * precedente "AI 1" (da concorrente a.it), attuale "AI 2" (da concorrente b.it).
 */
async function prodottoConVersioni( api, prefisso = 'Versioni' ) {
	const titolo = nome( prefisso );
	const id = await api.creaProdotto( titolo );
	await api.scriviSeo( id, valori( 'AI 1' ), 'concorrente a.it' );
	await api.scriviSeo( id, valori( 'AI 2' ), 'concorrente b.it' );
	return { id, titolo };
}

test.describe( 'Tab SEO: versioni dei campi', () => {
	test( 'mostra base, precedente e attuale con chi le ha scritte', async ( { page, api } ) => {
		const { titolo } = await prodottoConVersioni( api );
		await apriTabSeo( page );

		await riga( page, titolo ).getByRole( 'button', { name: 'Mostra versioni' } ).click();

		await expect( scheda( page, 'base' ) ).toContainText( 'Originale' );
		await expect( scheda( page, 'precedente' ) ).toContainText( 'Titolo AI 1' );
		await expect( scheda( page, 'precedente' ) ).toContainText( 'concorrente a.it' );
		await expect( scheda( page, 'attuale' ) ).toContainText( 'Titolo AI 2' );
		await expect( scheda( page, 'attuale' ) ).toContainText( 'concorrente b.it' );
		await expect( riga( page, titolo ).getByRole( 'button', { name: 'Nascondi versioni' } ) ).toBeVisible();
	} );

	test( 'torna alla base e poi al precedente, e il sito lo conferma', async ( { page, api } ) => {
		const { id, titolo } = await prodottoConVersioni( api );
		await apriTabSeo( page );
		await riga( page, titolo ).getByRole( 'button', { name: 'Mostra versioni' } ).click();

		page.once( 'dialog', ( dialog ) => {
			expect( dialog.message() ).toContain( 'come nella base' );
			expect( dialog.message() ).toContain( 'potrai sempre tornarci' );
			dialog.accept();
		} );
		await scheda( page, 'base' ).getByRole( 'button', { name: 'Torna alla base' } ).click();

		await expect( avviso( page, 'Riportati alla base: 1 su 1.' ) ).toBeVisible();
		expect( await api.seoVivo( id ) ).toEqual( { titolo_seo: '', meta_description: '', parola_chiave_focus: '' } );

		// Il pannello resta aperto e si aggiorna: il precedente e' diventato la versione lasciata (AI 2).
		await expect( scheda( page, 'precedente' ) ).toContainText( 'Titolo AI 2' );
		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await scheda( page, 'precedente' ).getByRole( 'button', { name: 'Torna al precedente' } ).click();

		await expect( avviso( page, 'Riportati al precedente: 1 su 1.' ) ).toBeVisible();
		expect( await api.seoVivo( id ) ).toEqual( valori( 'AI 2' ) );
	} );

	test( 'rifiutando la conferma non cambia niente', async ( { page, api } ) => {
		const { id, titolo } = await prodottoConVersioni( api );
		await apriTabSeo( page );
		await riga( page, titolo ).getByRole( 'button', { name: 'Mostra versioni' } ).click();

		page.once( 'dialog', ( dialog ) => dialog.dismiss() );
		await scheda( page, 'base' ).getByRole( 'button', { name: 'Torna alla base' } ).click();

		await expect( avviso( page, 'Riportati alla base' ) ).toHaveCount( 0 );
		expect( await api.seoVivo( id ) ).toEqual( valori( 'AI 2' ) );
	} );

	test( 'usare l\'attuale come base avvisa che l\'originale si perde e la rende "impostata da te"', async ( { page, api } ) => {
		const { id, titolo } = await prodottoConVersioni( api );
		await apriTabSeo( page );
		await riga( page, titolo ).getByRole( 'button', { name: 'Mostra versioni' } ).click();

		page.once( 'dialog', ( dialog ) => {
			expect( dialog.message() ).toContain( 'non sarà più recuperabile' );
			dialog.accept();
		} );
		await scheda( page, 'attuale' ).getByRole( 'button', { name: 'Usa l\'attuale come nuova base' } ).click();

		await expect( avviso( page, 'Nuova base impostata: 1 su 1.' ) ).toBeVisible();
		await expect( scheda( page, 'base' ) ).toContainText( 'Impostata da te' );
		await expect( scheda( page, 'base' ) ).toContainText( 'Titolo AI 2' );
		await expect( scheda( page, 'base' ).getByRole( 'button', { name: 'Torna alla base' } ) ).toBeDisabled();
		expect( await api.seoVivo( id ) ).toEqual( valori( 'AI 2' ) ); // i valori vivi non si toccano
	} );

	test( 'azioni in blocco: riguardano solo i prodotti con versioni e lo dicono', async ( { page, api } ) => {
		const uno = await prodottoConVersioni( api, 'Blocco uno' );
		const due = await prodottoConVersioni( api, 'Blocco due' );
		const senza = nome( 'Blocco senza' );
		await api.creaProdotto( senza );
		await apriTabSeo( page );

		for ( const titolo of [ uno.titolo, due.titolo, senza ] ) {
			await page.getByLabel( `Seleziona ${ titolo }`, { exact: true } ).check();
		}
		await expect( page.getByText( '3 selezionati' ) ).toBeVisible();
		await expect( page.getByText( '(2 con versioni salvate' ) ).toBeVisible();

		page.once( 'dialog', ( dialog ) => {
			expect( dialog.message() ).toContain( 'di 2 prodotti' );
			dialog.accept();
		} );
		await page.getByRole( 'button', { name: 'Torna alla base' } ).click();

		await expect( avviso( page, 'Riportati alla base: 2 su 2.' ) ).toBeVisible();
		expect( await api.seoVivo( uno.id ) ).toEqual( { titolo_seo: '', meta_description: '', parola_chiave_focus: '' } );
		expect( await api.seoVivo( due.id ) ).toEqual( { titolo_seo: '', meta_description: '', parola_chiave_focus: '' } );
		await expect( page.getByText( /selezionati/ ) ).toHaveCount( 0 ); // la selezione si azzera
	} );

	test( 'senza prodotti con versioni fra i selezionati le azioni sono disattivate', async ( { page, api } ) => {
		const titolo = nome( 'Solo senza' );
		await api.creaProdotto( titolo );
		await apriTabSeo( page );

		await page.getByLabel( `Seleziona ${ titolo }`, { exact: true } ).check();

		await expect( page.getByText( '(0 con versioni salvate' ) ).toBeVisible();
		for ( const azione of [ 'Torna alla base', 'Torna al precedente', 'Usa l\'attuale come base' ] ) {
			await expect( page.getByRole( 'button', { name: azione, exact: true } ) ).toBeDisabled();
		}
	} );

	test( 'il filtro "Con versioni salvate" mostra solo quei prodotti', async ( { page, api } ) => {
		const { titolo } = await prodottoConVersioni( api, 'Filtro con' );
		const senza = nome( 'Filtro senza' );
		await api.creaProdotto( senza );
		await apriTabSeo( page );

		await page.getByLabel( 'Mostra' ).selectOption( 'con_versioni' );

		await expect( riga( page, titolo ) ).toBeVisible();
		await expect( riga( page, senza ) ).toHaveCount( 0 );
	} );
} );
