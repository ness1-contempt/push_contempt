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

	/**
	 * Un prodotto che nasce gia' con una descrizione (e una breve): sara' lo stato di partenza (base).
	 *
	 * @param {string} titolo Nome del prodotto.
	 * @param {string} descrizione HTML.
	 * @param {string} breve Descrizione breve.
	 * @return {Promise<number>} Id del prodotto.
	 */
	async creaProdottoConTesto( titolo, descrizione, breve ) {
		const prodotto = await this.post( '/wc/v3/products', {
			name: titolo,
			status: 'publish',
			regular_price: '10',
			description: descrizione,
			short_description: breve,
		} );
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

	/**
	 * Scrive la descrizione (e, se data, quella breve) passando dal plugin: tiene base e precedente.
	 *
	 * @param {number} id Id del prodotto.
	 * @param {string} descrizione HTML.
	 * @param {string} breve Descrizione breve (assente = resta com'e').
	 */
	async scriviDescrizione( id, descrizione, breve ) {
		return this.post( '/push-contempt/v1/descrizione/prodotto', {
			id,
			descrizione,
			...( undefined !== breve ? { descrizione_breve: breve } : {} ),
			origine: 'test e2e',
		} );
	}

	/**
	 * Le due descrizioni vive di un prodotto.
	 *
	 * @param {number} id Id del prodotto.
	 */
	async descrizioneViva( id ) {
		const { descrizione, descrizione_breve: breve } = await this.get( '/push-contempt/v1/descrizione/prodotto', { id: String( id ) } );
		return { descrizione, breve };
	}

	/**
	 * Carica un'immagine nella libreria (un PNG di un pixel) e restituisce il suo id.
	 *
	 * @param {string} nomeFile Nome del file.
	 * @return {Promise<number>} Id dell'allegato.
	 */
	async caricaImmagine( nomeFile ) {
		const png = Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==', 'base64' );
		const risposta = await this.request.post( this.url( '/wp/v2/media' ), {
			headers: { ...this.headers, 'Content-Disposition': `attachment; filename="${ nomeFile }.png"`, 'Content-Type': 'image/png' },
			data: png,
		} );
		expect( risposta.ok(), `upload: ${ await risposta.text() }` ).toBeTruthy();
		return ( await risposta.json() ).id;
	}

	/**
	 * Un prodotto con le immagini date (la prima in evidenza, le altre in galleria) e la parola chiave focus.
	 *
	 * @param {string}   titolo Nome del prodotto.
	 * @param {number[]} immagini Id degli allegati.
	 * @param {string}   parolaChiave Parola chiave focus.
	 */
	async creaProdottoConImmagini( titolo, immagini, parolaChiave ) {
		const prodotto = await this.post( '/wc/v3/products', {
			name: titolo,
			status: 'publish',
			regular_price: '10',
			images: immagini.map( ( id ) => ( { id } ) ),
		} );
		await this.scriviSeo( prodotto.id, { titolo_seo: '', meta_description: '', parola_chiave_focus: parolaChiave } );
		return prodotto.id;
	}

	/**
	 * Un prodotto variabile con una variante per ogni prezzo.
	 *
	 * @param {string}   titolo Nome del prodotto.
	 * @param {string[]} prezzi Prezzo regolare di ciascuna variante.
	 * @return {Promise<number>} Id del prodotto.
	 */
	async creaVariabile( titolo, prezzi ) {
		const taglie = prezzi.map( ( _, i ) => `T${ i + 1 }` );
		const prodotto = await this.post( '/wc/v3/products', {
			name: titolo,
			type: 'variable',
			status: 'publish',
			attributes: [ { name: 'Taglia', visible: true, variation: true, options: taglie } ],
		} );
		for ( const [ i, prezzo ] of prezzi.entries() ) {
			await this.post( `/wc/v3/products/${ prodotto.id }/variations`, {
				regular_price: prezzo,
				attributes: [ { name: 'Taglia', option: taglie[ i ] } ],
			} );
		}
		return prodotto.id;
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
 * Apre la tab Contenuti del plugin sulla sezione indicata.
 *
 * @param {import('@playwright/test').Page} page Pagina.
 * @param {'Descrizioni'|'Immagini (ALT)'|'Link'} sezione Nome del pulsante della sezione.
 */
async function apriContenuti( page, sezione ) {
	await page.goto( '/wp-admin/admin.php?page=push-contempt' );
	await page.getByRole( 'tab', { name: 'Contenuti' } ).click();
	await page.getByRole( 'button', { name: sezione } ).click();
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
 * @param {string} fonte Dove stanno i valori in uso: "Yoast" (SEO) o "WooCommerce" (descrizioni).
 */
function scheda( page, quale, fonte = 'Yoast' ) {
	const sottotitolo = {
		base: 'Il punto di partenza',
		precedente: 'La versione da cui vieni',
		attuale: `In uso adesso in ${ fonte }`,
	}[ quale ];
	return page.getByText( sottotitolo, { exact: true } ).locator( '..' );
}

module.exports = { test, expect, nome, valori, riepilogo, apriTabSeo, apriContenuti, riga, scheda, avviso, appuntiDisponibili };
