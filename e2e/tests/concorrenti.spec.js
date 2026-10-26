const { test, expect, nome, riepilogo, apriTabSeo, valori, appuntiDisponibili } = require( './helpers' );

test.describe( 'Tab SEO: concorrenti e confronto', () => {
	test.beforeEach( async ( { api } ) => {
		await api.svuotaConcorrenti();
	} );

	test.afterEach( async ( { api } ) => {
		await api.svuotaConcorrenti();
	} );

	test( 'senza analisi dice cosa fare', async ( { page } ) => {
		await apriTabSeo( page );

		await expect( page.getByText( 'Nessun sito analizzato per ora.' ) ).toBeVisible();
	} );

	test( 'mostra i punteggi per area, segna il tuo sito e le differenze', async ( { page, api } ) => {
		const mio = `${ nome( 'mio' ).replace( /\s/g, '-' ) }.it`.toLowerCase();
		const altro = `${ nome( 'altro' ).replace( /\s/g, '-' ) }.it`.toLowerCase();
		await api.salvaConcorrente( riepilogo( altro, { punteggio: 8, aree: { titoli: { punteggio: 8 }, tecnico: { punteggio: 9 } } } ) );
		await api.salvaConcorrente( riepilogo( mio, { proprio: true, punteggio: 6, aree: { titoli: { punteggio: 6 }, tecnico: { punteggio: 9 } } } ) );
		await apriTabSeo( page );

		const rigaMia = page.getByRole( 'row' ).filter( { hasText: mio } );
		const rigaAltro = page.getByRole( 'row' ).filter( { hasText: altro } );

		await expect( rigaMia ).toContainText( 'Il tuo sito' );
		await expect( rigaAltro ).not.toContainText( 'Il tuo sito' );
		await expect( rigaAltro ).toContainText( '8(+2)' ); // titoli: 8 contro i 6 del tuo sito
		await expect( rigaAltro ).toContainText( '9' ); // tecnico uguale: nessuna differenza
		await expect( rigaMia.locator( 'small' ) ).toHaveCount( 0 ); // sul proprio sito non ci sono differenze
		// il tuo sito sta sopra i concorrenti
		const righe = await page.getByRole( 'row' ).allTextContents();
		expect( righe.findIndex( ( t ) => t.includes( mio ) ) ).toBeLessThan( righe.findIndex( ( t ) => t.includes( altro ) ) );
	} );

	test( 'il dettaglio mostra criteri, limiti e storico, e permette di togliere il sito', async ( { page, api } ) => {
		const sito = `${ nome( 'dettaglio' ).replace( /\s/g, '-' ) }.it`.toLowerCase();
		await api.salvaConcorrente( riepilogo( sito, { punteggio: 5 } ) );
		await api.salvaConcorrente( riepilogo( sito, { punteggio: 7 } ) );
		await apriTabSeo( page );

		await page.getByRole( 'button', { name: sito } ).click();

		await expect( page.getByText( '11 pagine valutate' ) ).toBeVisible();
		await expect( page.getByText( 'Storico del totale: 5 → 7' ) ).toBeVisible();
		await expect( page.getByText( 'lunghezza adatta — titoli fra 30 e 60 caratteri: 8 su 10' ) ).toBeVisible();
		await expect( page.getByText( /Limite: immagini senza ALT per scaglioni \(33%\) — abbassa il voto da 8 a 7/ ) ).toBeVisible();

		page.once( 'dialog', ( dialog ) => {
			expect( dialog.message() ).toContain( sito );
			dialog.accept();
		} );
		await page.getByRole( 'button', { name: 'Togli dalla lista' } ).click();

		await expect( page.getByText( 'Nessun sito analizzato per ora.' ) ).toBeVisible();
		expect( await api.elencoConcorrenti() ).toHaveLength( 0 );
	} );

	test( 'rifiutando la conferma il sito resta nella lista', async ( { page, api } ) => {
		const sito = `${ nome( 'resta' ).replace( /\s/g, '-' ) }.it`.toLowerCase();
		await api.salvaConcorrente( riepilogo( sito ) );
		await apriTabSeo( page );
		await page.getByRole( 'button', { name: sito } ).click();

		page.once( 'dialog', ( dialog ) => dialog.dismiss() );
		await page.getByRole( 'button', { name: 'Togli dalla lista' } ).click();

		await expect( page.getByRole( 'button', { name: 'Togli dalla lista' } ) ).toBeVisible();
		expect( await api.elencoConcorrenti() ).toHaveLength( 1 );
	} );

	test( '"Aggiorna" mostra le analisi arrivate dopo il caricamento della pagina', async ( { page, api } ) => {
		const sito = `${ nome( 'tardivo' ).replace( /\s/g, '-' ) }.it`.toLowerCase();
		await apriTabSeo( page );
		await expect( page.getByText( 'Nessun sito analizzato per ora.' ) ).toBeVisible();

		await api.salvaConcorrente( riepilogo( sito ) ); // come se l'assistente avesse appena finito un'analisi
		await page.getByRole( 'button', { name: 'Aggiorna' } ).last().click();

		await expect( page.getByRole( 'button', { name: sito } ) ).toBeVisible();
	} );
} );

test.describe( 'Tab SEO: riscrivere con ispirazione da un concorrente', () => {
	test.beforeEach( async ( { api } ) => {
		await api.svuotaConcorrenti();
	} );

	test.afterEach( async ( { api } ) => {
		await api.svuotaConcorrenti();
	} );

	test( 'senza concorrenti analizzati lo dice', async ( { page } ) => {
		await apriTabSeo( page );

		await expect( page.getByText( 'Nessun concorrente analizzato per ora.' ) ).toBeVisible();
	} );

	test( 'il messaggio compare solo con prodotto, concorrente e area scelti, e riporta le scelte', async ( { page, api } ) => {
		const sito = `${ nome( 'ispira' ).replace( /\s/g, '-' ) }.it`.toLowerCase();
		const titolo = nome( 'Ispirato' );
		const id = await api.creaProdotto( titolo );
		await api.scriviSeo( id, { ...valori( 'x' ), parola_chiave_focus: 'kw ispirato' } );
		await api.salvaConcorrente( riepilogo( sito ) );
		await apriTabSeo( page );
		const segnaposto = page.getByText( 'Il messaggio compare qui quando hai scelto' );
		const messaggio = page.locator( 'pre' ).filter( { hasText: 'Riscrivi' } );

		await expect( segnaposto ).toBeVisible();
		await page.getByLabel( `Seleziona ${ titolo }`, { exact: true } ).check();
		await page.getByLabel( '2. Concorrente' ).selectOption( sito );
		// le aree partono tutte deselezionate: le sceglie l'utente
		await expect( page.getByLabel( /^Titoli SEO/ ) ).not.toBeChecked();
		await expect( page.getByLabel( /^Meta description/ ) ).not.toBeChecked();
		await expect( segnaposto ).toBeVisible();

		await page.getByLabel( /^Titoli SEO/ ).check();

		await expect( segnaposto ).toHaveCount( 0 );
		await expect( messaggio ).toContainText( `dal sito concorrente ${ sito }` );
		await expect( messaggio ).toContainText( `${ titolo } (ID ${ id }), parola chiave focus attuale: kw ispirato` );
		await expect( messaggio ).toContainText( 'titoli SEO 8/9' );
		await expect( messaggio ).toContainText( 'Per meta description non seguire il concorrente' );
		await expect( messaggio ).toContainText( `origine="concorrente ${ sito }"` );
		await expect( messaggio ).toContainText( 'lo scelgo io dopo aver visto i risultati' );

		await page.getByLabel( /^Meta description/ ).check();
		await page.getByLabel( '4. Somiglianza accettata' ).selectOption( '4' );

		await expect( messaggio ).toContainText( 'titoli SEO e meta description' );
		await expect( messaggio ).not.toContainText( 'non seguire il concorrente' );
		await expect( messaggio ).toContainText( 'livello massimo di somiglianza che accetto è 4 su 9' );
	} );

	test( '"Copia messaggio" mette il testo negli appunti', async ( { page, api } ) => {
		const sito = `${ nome( 'copia' ).replace( /\s/g, '-' ) }.it`.toLowerCase();
		const titolo = nome( 'Copiato' );
		await api.creaProdotto( titolo );
		await api.salvaConcorrente( riepilogo( sito ) );
		await appuntiDisponibili( page );
		await apriTabSeo( page );
		await page.getByLabel( `Seleziona ${ titolo }`, { exact: true } ).check();
		await page.getByLabel( '2. Concorrente' ).selectOption( sito );
		await page.getByLabel( /^Titoli SEO/ ).check();
		const messaggio = page.locator( 'pre' ).filter( { hasText: 'Riscrivi' } );
		const testo = await messaggio.innerText();

		await page.getByRole( 'button', { name: 'Copia messaggio' } ).click();

		await expect( page.getByRole( 'button', { name: 'Copiato ✓' } ) ).toBeVisible();
		expect( await page.evaluate( () => navigator.clipboard.readText() ) ).toBe( testo );
	} );

	test( 'il sito dell\'utente non compare fra i concorrenti da cui prendere ispirazione', async ( { page, api } ) => {
		const mio = `${ nome( 'proprio' ).replace( /\s/g, '-' ) }.it`.toLowerCase();
		const altro = `${ nome( 'altro' ).replace( /\s/g, '-' ) }.it`.toLowerCase();
		await api.salvaConcorrente( riepilogo( mio, { proprio: true } ) );
		await api.salvaConcorrente( riepilogo( altro ) );
		await apriTabSeo( page );

		const opzioni = await page.getByLabel( '2. Concorrente' ).locator( 'option' ).allTextContents();

		expect( opzioni.some( ( o ) => o.includes( altro ) ) ).toBe( true );
		expect( opzioni.some( ( o ) => o.includes( mio ) ) ).toBe( false );
	} );
} );
