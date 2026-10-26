// @ts-check
const base = require( '@playwright/test' );
const { expect } = base;

let contatore = 0;

/**
 * Un nome distinguibile per prodotti e siti di prova (il sito e' condiviso fra i test).
 *
 * @param {string} prefisso Parola che dice a cosa serve.
 */
function nome( prefisso ) {
	contatore += 1;
	return `${ prefisso } ${ Date.now().toString( 36 ) }${ contatore }`;
}

/**
 * Tre valori SEO di esempio, distinguibili dal suffisso.
 *
 * @param {string} suffisso Testo che li rende diversi da altri insiemi.
 */
function valori( suffisso ) {
	return {
		titolo_seo: `Titolo ${ suffisso }`,
		meta_description: `Meta ${ suffisso }`,
		parola_chiave_focus: `kw ${ suffisso }`,
	};
}

/**
 * Un riepilogo di analisi come lo manda il server MCP.
 *
 * @param {string} sito   Nome del sito.
 * @param {Object} extra  Campi da aggiungere o sostituire.
 */
function riepilogo( sito, extra = {} ) {
	return {
		sito,
		url: `https://${ sito }/`,
		data: '2026-10-26',
		punteggio: 7,
		pagine: 11,
		metodo: 'http',
		aree: {
			titoli: {
				punteggio: 8,
				criteri: [ { criterio: 'lunghezza adatta', valore: 0.8, evidenza: 'titoli fra 30 e 60 caratteri: 8 su 10', peso: 2 } ],
			},
			meta_description: { punteggio: 6, criteri: [] },
			contenuto: {
				punteggio: 5,
				criteri: [],
				tetti: [ { regola: 'immagini senza ALT per scaglioni', misura: '33%', effetto: 'abbassa il voto da 8 a 7', scatta: true } ],
			},
			tecnico: { punteggio: 9, criteri: [] },
		},
		...extra,
	};
}

/**
 * Client per la REST API del sito di prova, con la sessione dell'amministratore.
 * Serve a preparare i dati (prodotti, analisi) e a controllare cosa e' successo
 * davvero sul sito dopo un'azione fatta nell'interfaccia.
 */
class Api {
	/**
	 * @param {import('@playwright/test').APIRequestContext} request Contesto con la sessione.
	 * @param {string} nonce Nonce REST dell'utente.
	 */
	constructor( request, nonce ) {
		this.request = request;
		this.headers = { 'X-WP-Nonce': nonce };
	}

	/**
	 * @param {string} rotta Rotta REST completa (es. "/push-contempt/v1/seo/stato").
	 * @param {Record<string, string>} query Parametri in query string.
	 */
	url( rotta, query = {} ) {
		return `/?${ new URLSearchParams( { rest_route: rotta, ...query } ) }`;
	}

	async get( rotta, query = {} ) {
		const risposta = await this.request.get( this.url( rotta, query ), { headers: this.headers } );
		expect( risposta.ok(), `GET ${ rotta }: ${ await risposta.text() }` ).toBeTruthy();
		return risposta.json();
	}

	async post( rotta, dati ) {
		const risposta = await this.request.post( this.url( rotta ), { headers: this.headers, data: dati } );
		expect( risposta.ok(), `POST ${ rotta }: ${ await risposta.text() }` ).toBeTruthy();
		return risposta.json();
	}

	/**
	 * @param {string} titolo Nome del prodotto.
	 * @param {'publish'|'draft'} stato Stato.
	 * @return {Promise<number>} Id del prodotto.
	 */
	async creaProdotto( titolo, stato = 'publish' ) {
		const prodotto = await this.post( '/wc/v3/products', { name: titolo, status: stato, regular_price: '10' } );
		return prodotto.id;
	}

	async scriviSeo( id, campi, origine = '' ) {
		return this.post( '/push-contempt/v1/seo/prodotto', { id, ...campi, ...( origine ? { origine } : {} ) } );
	}

	/**
	 * La riga del prodotto in /seo/stato (con `versioni`).
	 *
	 * @param {number} id Id del prodotto.
	 */
	async statoSeo( id ) {
		const righe = await this.get( '/push-contempt/v1/seo/stato' );
		return righe.find( ( riga ) => riga.id === id );
	}

	/**
	 * I tre campi SEO vivi di un prodotto.
	 *
	 * @param {number} id Id del prodotto.
	 */
	async seoVivo( id ) {
		const { titolo_seo: t, meta_description: m, parola_chiave_focus: k } = await this.get( '/push-contempt/v1/seo/prodotto', { id: String( id ) } );
		return { titolo_seo: t ?? '', meta_description: m ?? '', parola_chiave_focus: k ?? '' };
	}

	async salvaConcorrente( dati ) {
		return this.post( '/push-contempt/v1/concorrenti', dati );
	}

	async elencoConcorrenti() {
		return this.get( '/push-contempt/v1/concorrenti' );
	}

	async eliminaConcorrente( sito ) {
		const risposta = await this.request.delete( this.url( '/push-contempt/v1/concorrenti', { sito } ), { headers: this.headers } );
		expect( risposta.ok() ).toBeTruthy();
	}

	async svuotaConcorrenti() {
		for ( const riga of await this.elencoConcorrenti() ) {
			await this.eliminaConcorrente( riga.ultima.sito );
		}
	}
}

const test = base.test.extend( {
	// Il sito di prova non ha bisogno di Internet (gravatar, font, telemetria di WooCommerce...):
	// le richieste verso altri siti si bloccano, cosi' le pagine non aspettano risposte che non arrivano.
	context: async ( { context, baseURL }, use ) => {
		const host = new URL( baseURL ).host;
		await context.route( ( url ) => url.host !== host, ( route ) => route.abort() );
		await use( context );
	},
	api: async ( { request }, use ) => {
		const risposta = await request.get( '/wp-admin/admin-ajax.php?action=rest-nonce' );
		await use( new Api( request, ( await risposta.text() ).trim() ) );
	},
} );

/**
 * Apre la tab SEO del plugin. La tabella dei prodotti compare solo se ce ne sono (su un sito
 * appena creato non ce ne sono), quindi si aspetta la sezione dei concorrenti, che c'e' sempre:
 * i test che hanno bisogno di una riga la aspettano da soli.
 *
 * @param {import('@playwright/test').Page} page Pagina.
 */
async function apriTabSeo( page ) {
	await page.goto( '/wp-admin/admin.php?page=push-contempt' );
	await page.getByRole( 'tab', { name: 'SEO' } ).click();
	await expect( page.getByRole( 'heading', { name: 'Concorrenti e confronto' } ) ).toBeVisible();
}

/**
 * La riga della tabella dei prodotti con quel nome.
 *
 * @param {import('@playwright/test').Page} page Pagina.
 * @param {string} titolo Nome del prodotto.
 */
function riga( page, titolo ) {
	return page.getByRole( 'row' ).filter( { has: page.getByRole( 'link', { name: titolo, exact: true } ) } );
}

/**
 * Gli appunti (navigator.clipboard) esistono solo in contesti sicuri: http://127.0.0.1 e localhost
 * lo sono (CI), un sito di prova su http://<indirizzo del container> no. In quel caso si simulano
 * con lo stesso comportamento (scrivi/leggi); dove esistono si usano quelli veri.
 *
 * @param {import('@playwright/test').Page} page Pagina.
 */
async function appuntiDisponibili( page ) {
	await page.addInitScript( () => {
		if ( ! navigator.clipboard ) {
			let contenuto = '';
			Object.defineProperty( navigator, 'clipboard', {
				value: {
					writeText: async ( testo ) => {
						contenuto = testo;
					},
					readText: async () => contenuto,
				},
			} );
		}
	} );
}

/**
 * L'avviso (Notice) della tab con quel testo: lo stesso testo e' anche nella regione
 * di accessibilita' della pagina, quindi getByText da solo troverebbe due elementi.
 *
 * @param {import('@playwright/test').Page} page Pagina.
 * @param {string} testo Testo dell'avviso.
 */
function avviso( page, testo ) {
	return page.locator( '.components-notice__content', { hasText: testo } );
}

/**
 * La scheda di una versione (Base, Precedente, Attuale), cercata dal suo sottotitolo.
 *
 * @param {import('@playwright/test').Page} page Pagina.
 * @param {'base'|'precedente'|'attuale'} quale Versione.
 */
function scheda( page, quale ) {
	const sottotitolo = {
		base: 'Il punto di partenza',
		precedente: 'La versione da cui vieni',
		attuale: 'In uso adesso in Yoast',
	}[ quale ];
	return page.getByText( sottotitolo, { exact: true } ).locator( '..' );
}

module.exports = { test, expect, nome, valori, riepilogo, apriTabSeo, riga, scheda, avviso, appuntiDisponibili };
