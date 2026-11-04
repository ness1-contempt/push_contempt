import { messaggioBlog } from './messaggioBlog';

describe( 'messaggioBlog', () => {
	it( 'dice quanti articoli scrivere, al singolare e al plurale', () => {
		expect( messaggioBlog( 1 ) ).toContain( 'Scrivi un articolo per il blog' );
		expect( messaggioBlog( 4 ) ).toContain( 'Scrivi 4 articoli per il blog' );
	} );

	it( 'parte dal brief e dal contesto salvati nel plugin e chiede il piano prima di scrivere', () => {
		const testo = messaggioBlog( 3 );

		expect( testo ).toContain( 'leggi_brief_blog' );
		expect( testo ).toContain( 'contesto_blog' );
		expect( testo ).toContain( 'aspetta il mio ok' );
	} );

	it( 'vieta dati inventati, immagini generate e la pubblicazione', () => {
		const testo = messaggioBlog( 3 );

		expect( testo ).toContain( 'niente dati, statistiche, citazioni o caratteristiche dei prodotti inventati' );
		expect( testo ).toContain( 'non vanno generate' );
		expect( testo ).toContain( 'Non pubblicare niente' );
	} );

	it( 'salva con crea_articolo_bozza', () => {
		expect( messaggioBlog( 2 ) ).toContain( 'crea_articolo_bozza' );
	} );
} );
