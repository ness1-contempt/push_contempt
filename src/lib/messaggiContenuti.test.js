import { anteprimaHtml, messaggioAlt, messaggioDescrizione } from './messaggiContenuti';

const prodotto = { id: 7, titolo: 'Sedia rossa', parola_chiave_focus: 'sedia rossa' };

describe( 'messaggioDescrizione', () => {
	it( 'identifica il prodotto, la parola chiave e gli strumenti', () => {
		const testo = messaggioDescrizione( prodotto );
		expect( testo ).toContain( '«Sedia rossa» (ID 7)' );
		expect( testo ).toContain( 'parola chiave focus: sedia rossa' );
		expect( testo ).toContain( 'leggi_descrizione_prodotto' );
		expect( testo ).toContain( 'aggiorna_descrizione_prodotto' );
	} );

	it( 'senza parola chiave dice "nessuna"', () => {
		expect( messaggioDescrizione( { ...prodotto, parola_chiave_focus: null } ) ).toContain( 'focus: nessuna' );
	} );
} );

describe( 'messaggioAlt', () => {
	it( 'nomina gli strumenti di lettura e scrittura degli ALT', () => {
		const testo = messaggioAlt( prodotto );
		expect( testo ).toContain( '(ID 7)' );
		expect( testo ).toContain( 'leggi_alt_immagini_prodotto' );
		expect( testo ).toContain( 'aggiorna_alt_immagini_prodotto' );
	} );
} );

describe( 'anteprimaHtml', () => {
	it( 'toglie i tag e normalizza gli spazi', () => {
		expect( anteprimaHtml( '<h2>Titolo</h2>\n<p>Testo&nbsp;con   <b>grassetto</b> &amp; altro</p>' ) ).toBe(
			'Titolo Testo con grassetto & altro'
		);
	} );

	it( 'accorcia con i puntini solo se serve', () => {
		expect( anteprimaHtml( '<p>abcdefghij</p>', 5 ) ).toBe( 'abcde…' );
		expect( anteprimaHtml( '<p>abc</p>', 5 ) ).toBe( 'abc' );
		expect( anteprimaHtml( null ) ).toBe( '' );
	} );
} );
