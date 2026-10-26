import { AREE_ISPIRAZIONE, LIVELLI_SOMIGLIANZA, messaggioIspirazione } from './messaggioIspirazione';

const concorrente = {
	sito: 'euronics.it',
	url: 'https://www.euronics.it/telefonia/',
	data: '2026-10-26',
	punteggio: 7,
	aree: {
		titoli: { punteggio: 8 },
		meta_description: { punteggio: 6 },
	},
};

const prodotti = [
	{ id: 11, titolo: 'iPhone 16', parola_chiave_focus: 'iphone 16 ricondizionato' },
	{ id: 12, titolo: 'Pixel 8a', parola_chiave_focus: null },
];

const crea = ( extra = {} ) =>
	messaggioIspirazione( {
		prodotti,
		concorrente,
		aree: [ 'titoli', 'meta_description' ],
		livello: '',
		...extra,
	} );

describe( 'messaggioIspirazione', () => {
	it( 'nomina il concorrente, la data e i punteggi delle aree scelte', () => {
		const messaggio = crea();

		expect( messaggio ).toContain( 'dal sito concorrente euronics.it' );
		expect( messaggio ).toContain( 'https://www.euronics.it/telefonia/' );
		expect( messaggio ).toContain( 'analizzato il 2026-10-26' );
		expect( messaggio ).toContain( 'punteggio complessivo 7/9' );
		expect( messaggio ).toContain( 'titoli SEO 8/9, meta description 6/9' );
	} );

	it( 'elenca i prodotti con id e parola chiave focus, o "nessuna" se manca', () => {
		const messaggio = crea();

		expect( messaggio ).toContain( '- iPhone 16 (ID 11), parola chiave focus attuale: iphone 16 ricondizionato' );
		expect( messaggio ).toContain( '- Pixel 8a (ID 12), parola chiave focus attuale: nessuna' );
	} );

	it( 'chiede di tracciare l\'origine e di controllare la somiglianza prima di applicare', () => {
		const messaggio = crea();

		expect( messaggio ).toContain( 'origine="concorrente euronics.it"' );
		expect( messaggio ).toContain( 'controlla_somiglianza' );
		expect( messaggio ).toContain( 'testi ORIGINALI' );
	} );

	it( 'con un livello fissato lo passa come soglia', () => {
		const messaggio = crea( { livello: '4' } );

		expect( messaggio ).toContain( 'livello massimo di somiglianza che accetto è 4 su 9' );
		expect( messaggio ).not.toContain( 'lo scelgo io dopo aver visto i risultati' );
	} );

	it( 'senza livello lo lascia scegliere all\'utente e vieta di applicare prima', () => {
		const messaggio = crea( { livello: '' } );

		expect( messaggio ).toContain( 'lo scelgo io dopo aver visto i risultati' );
		expect( messaggio ).toContain( 'non applicare niente finché non te lo dico' );
	} );

	it( 'per le aree non scelte dice di lasciare i valori attuali', () => {
		const messaggio = crea( { aree: [ 'titoli' ] } );

		expect( messaggio ).toContain( 'Riscrivi titoli SEO dei prodotti' );
		expect( messaggio ).toContain( 'Per meta description non seguire il concorrente' );
		expect( messaggio ).not.toContain( 'meta description 6/9' );
	} );

	it( 'se tutte le aree sono scelte non nomina aree da lasciare', () => {
		expect( crea() ).not.toContain( 'non seguire il concorrente' );
	} );

	it( 'usa valori di riserva quando mancano indirizzo, data o punteggi', () => {
		const messaggio = crea( {
			concorrente: { sito: 'tomato.it', aree: {} },
		} );

		expect( messaggio ).toContain( 'https://tomato.it' );
		expect( messaggio ).toContain( 'data non nota' );
		expect( messaggio ).toContain( 'punteggio complessivo —/9' );
		expect( messaggio ).toContain( 'titoli SEO —/9' );
	} );
} );

describe( 'scelte offerte all\'utente', () => {
	it( 'le aree sono solo quelle che corrispondono a campi che l\'assistente scrive', () => {
		expect( AREE_ISPIRAZIONE.map( ( a ) => a.chiave ) ).toEqual( [ 'titoli', 'meta_description' ] );
	} );

	it( 'il primo livello di somiglianza è "lo decido io" e gli altri sono numeri da 0 a 9', () => {
		expect( LIVELLI_SOMIGLIANZA[ 0 ].valore ).toBe( '' );
		LIVELLI_SOMIGLIANZA.slice( 1 ).forEach( ( livello ) => {
			expect( Number( livello.valore ) ).toBeGreaterThanOrEqual( 0 );
			expect( Number( livello.valore ) ).toBeLessThanOrEqual( 9 );
		} );
	} );
} );
