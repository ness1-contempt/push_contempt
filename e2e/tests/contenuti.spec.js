const { test, expect, nome, apriContenuti, apriTabSeo, riga, scheda, avviso } = require( './helpers' );

test.describe( 'Tab Contenuti: descrizioni', () => {
	/**
	 * Un prodotto con la descrizione originale e due riscritture dell'assistente.
	 */
	async function prodottoRiscritto( api ) {
		const titolo = nome( 'Descrizione' );
		// Il testo con cui nasce il prodotto e' lo stato di partenza: diventa la base.
		const id = await api.creaProdottoConTesto( titolo, '<p>Testo originale</p>', 'Breve originale' );
		await api.scriviDescrizione( id, '<p>Prima riscrittura</p>', 'Breve uno' );
		await api.scriviDescrizione( id, '<p>Seconda riscrittura</p>' );
		return { id, titolo };
	}

	test( 'mostra base, precedente e attuale delle descrizioni', async ( { page, api } ) => {
		const { titolo } = await prodottoRiscritto( api );
		await apriContenuti( page, 'Descrizioni' );

		await riga( page, titolo ).getByRole( 'button', { name: 'Mostra versioni' } ).click();

		await expect( scheda( page, 'base', 'WooCommerce' ) ).toContainText( 'Testo originale' );
		await expect( scheda( page, 'base', 'WooCommerce' ) ).toContainText( 'Breve originale' );
		await expect( scheda( page, 'precedente', 'WooCommerce' ) ).toContainText( 'Prima riscrittura' );
		await expect( scheda( page, 'attuale', 'WooCommerce' ) ).toContainText( 'Seconda riscrittura' );
		await expect( scheda( page, 'attuale', 'WooCommerce' ) ).toContainText( 'test e2e' );
	} );

	test( 'torna alla base e poi al precedente, e il sito lo conferma', async ( { page, api } ) => {
		const { id, titolo } = await prodottoRiscritto( api );
		await apriContenuti( page, 'Descrizioni' );
		await riga( page, titolo ).getByRole( 'button', { name: 'Mostra versioni' } ).click();

		page.once( 'dialog', ( dialog ) => {
			expect( dialog.message() ).toContain( 'le descrizioni di questo prodotto come nella base' );
			dialog.accept();
		} );
		await scheda( page, 'base', 'WooCommerce' ).getByRole( 'button', { name: 'Torna alla base' } ).click();

		await expect( avviso( page, 'Riportate alla base: 1 su 1.' ) ).toBeVisible();
		expect( await api.descrizioneViva( id ) ).toEqual( { descrizione: '<p>Testo originale</p>', breve: 'Breve originale' } );

		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await scheda( page, 'precedente', 'WooCommerce' ).getByRole( 'button', { name: 'Torna al precedente' } ).click();

		await expect( avviso( page, 'Riportate al precedente: 1 su 1.' ) ).toBeVisible();
		expect( await api.descrizioneViva( id ) ).toEqual( { descrizione: '<p>Seconda riscrittura</p>', breve: 'Breve uno' } );
	} );

	test( 'rifiutando la conferma non cambia niente', async ( { page, api } ) => {
		const { id, titolo } = await prodottoRiscritto( api );
		await apriContenuti( page, 'Descrizioni' );
		await riga( page, titolo ).getByRole( 'button', { name: 'Mostra versioni' } ).click();

		page.once( 'dialog', ( dialog ) => dialog.dismiss() );
		await scheda( page, 'base', 'WooCommerce' ).getByRole( 'button', { name: 'Torna alla base' } ).click();

		expect( ( await api.descrizioneViva( id ) ).descrizione ).toBe( '<p>Seconda riscrittura</p>' );
	} );

	test( 'il filtro "Con versioni salvate" mostra solo quei prodotti, e "Prepara messaggio" nomina il prodotto', async ( { page, api } ) => {
		const { id, titolo } = await prodottoRiscritto( api );
		const senza = nome( 'SenzaVersioni' );
		await api.creaProdotto( senza );
		await apriContenuti( page, 'Descrizioni' );
		await expect( riga( page, senza ) ).toBeVisible();

		await page.getByLabel( 'Mostra' ).selectOption( 'con_versioni' );

		await expect( riga( page, titolo ) ).toBeVisible();
		await expect( riga( page, senza ) ).toHaveCount( 0 );

		await riga( page, titolo ).getByRole( 'button', { name: 'Prepara messaggio' } ).click();
		await expect( page.locator( 'pre' ).filter( { hasText: `(ID ${ id })` } ) ).toContainText( 'aggiorna_descrizione_prodotto' );
	} );
} );

test.describe( 'Tab Contenuti: ALT delle immagini', () => {
	test( 'segnala gli ALT mancanti, si correggono a mano e il sito li ha davvero', async ( { page, api } ) => {
		const titolo = nome( 'Cuffie' );
		const foto = [ await api.caricaImmagine( nome( 'fronte' ).replace( / /g, '-' ) ), await api.caricaImmagine( nome( 'lato' ).replace( / /g, '-' ) ) ];
		const id = await api.creaProdottoConImmagini( titolo, foto, 'cuffie rosse' );
		await apriContenuti( page, 'Immagini (ALT)' );

		const r = riga( page, titolo );
		await expect( r ).toContainText( '2' ); // immagini e ALT mancanti
		await r.getByRole( 'button', { name: 'Modifica ALT' } ).click();
		await expect( page.locator( 'td[colspan="7"]' ).getByText( 'ALT mancante' ) ).toHaveCount( 2 );
		await expect( page.getByRole( 'button', { name: 'Salva ALT', exact: true } ) ).toBeDisabled();

		const campi = page.locator( 'td[colspan="7"] input[type=text]' );
		await campi.nth( 0 ).fill( 'Cuffie rosse viste di fronte' );
		await campi.nth( 1 ).fill( 'Un dettaglio del lato' );
		await page.getByRole( 'button', { name: 'Salva ALT (2)' } ).click();

		await expect( avviso( page, `ALT salvati per «${ titolo }»: 2.` ) ).toBeVisible();
		const immagini = ( await api.get( '/push-contempt/v1/alt/prodotto', { id: String( id ) } ) ).immagini;
		expect( immagini.map( ( i ) => [ i.alt, i.problema ] ) ).toEqual( [
			[ 'Cuffie rosse viste di fronte', null ],
			[ 'Un dettaglio del lato', 'senza_parola_chiave' ],
		] );
		// Resta in elenco solo per il secondo, che non ha la parola chiave.
		await expect( riga( page, titolo ) ).toBeVisible();
	} );

	test( 'un prodotto con gli ALT a posto non compare fra quelli con problemi, ma compare con "Tutti"', async ( { page, api } ) => {
		const titolo = nome( 'Apposto' );
		const foto = await api.caricaImmagine( nome( 'ok' ).replace( / /g, '-' ) );
		const id = await api.creaProdottoConImmagini( titolo, [ foto ], 'sedia blu' );
		await api.post( '/push-contempt/v1/alt/prodotto', { id, immagini: [ { allegato_id: foto, alt: 'Sedia blu da giardino' } ] } );
		await apriContenuti( page, 'Immagini (ALT)' );
		await expect( page.getByRole( 'button', { name: 'Aggiorna' } ) ).toBeVisible();

		await expect( riga( page, titolo ) ).toHaveCount( 0 );
		await page.getByLabel( 'Mostra' ).selectOption( 'tutti' );

		await expect( riga( page, titolo ) ).toBeVisible();
	} );
} );

test.describe( 'Tab Contenuti: link', () => {
	test( 'segnala chi non ha link in uscita e lo aggiorna dopo averne aggiunto uno', async ( { page, api } ) => {
		const titolo = nome( 'Link' );
		const id = await api.creaProdotto( titolo );
		await api.scriviDescrizione( id, '<p>Vedi <a href="/negozio/">il negozio</a>.</p>' );
		await apriContenuti( page, 'Link' );
		await expect( riga( page, titolo ) ).toContainText( 'Manca il link in uscita' );

		await riga( page, titolo ).getByRole( 'button', { name: 'Vedi link' } ).click();
		await expect( page.getByText( '/negozio/' ) ).toBeVisible();

		await api.scriviDescrizione( id, '<p>Vedi <a href="/negozio/">il negozio</a> e <a href="https://example.com/">il produttore</a>.</p>' );
		await page.getByRole( 'checkbox', { name: 'Solo quelli senza link in uscita' } ).uncheck();
		await page.getByRole( 'button', { name: 'Controlla' } ).click();

		await expect( riga( page, titolo ) ).toContainText( 'OK' );
		await expect( riga( page, titolo ).getByRole( 'button', { name: 'Prepara messaggio' } ) ).toHaveCount( 0 );
	} );

	test( 'il riepilogo conta i prodotti senza link in uscita', async ( { page, api } ) => {
		await api.creaProdotto( nome( 'ConteggioLink' ) );
		await apriContenuti( page, 'Link' );

		await expect( page.getByText( /Analizzati \d+ prodotti: \d+ senza link in uscita/ ) ).toBeVisible();
	} );
} );

test.describe( 'Tipo e prezzo dei prodotti', () => {
	test( 'un variabile mostra tipo, varianti e intervallo di prezzo in SEO e in Contenuti', async ( { page, api } ) => {
		const titolo = nome( 'Maglia' );
		await api.creaVariabile( titolo, [ '20', '29' ] );

		await apriTabSeo( page );
		await expect( riga( page, titolo ) ).toContainText( 'Variabile · 2 varianti' );
		await expect( riga( page, titolo ) ).toContainText( '20.00–29.00' );

		await apriContenuti( page, 'Descrizioni' );
		await expect( riga( page, titolo ) ).toContainText( 'Variabile · 2 varianti' );
		await expect( riga( page, titolo ) ).toContainText( '20.00–29.00' );
	} );

	test( 'un prodotto semplice mostra un solo prezzo', async ( { page, api } ) => {
		const titolo = nome( 'Semplice' );
		await api.creaProdotto( titolo );

		await apriTabSeo( page );

		await expect( riga( page, titolo ) ).toContainText( 'Semplice' );
		await expect( riga( page, titolo ) ).not.toContainText( '–' );
	} );
} );
