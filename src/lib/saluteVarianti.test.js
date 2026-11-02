import {
	etichettaSaluteVarianti,
	haProblemiVarianti,
	messaggioProdottoVariabile,
} from './saluteVarianti';

describe( 'etichettaSaluteVarianti', () => {
	it( "e' vuota per i prodotti che non sono variabili", () => {
		expect( etichettaSaluteVarianti( null ) ).toBe( '' );
		expect( etichettaSaluteVarianti( undefined ) ).toBe( '' );
	} );

	it( "dice che e' a posto quando non ci sono problemi", () => {
		expect(
			etichettaSaluteVarianti( { errori: 0, avvisi: 0, suggerimenti: 0 } )
		).toBe( 'Varianti a posto' );
	} );

	it( "conta singolare e plurale e salta le gravita' a zero", () => {
		expect(
			etichettaSaluteVarianti( { errori: 1, avvisi: 0, suggerimenti: 3 } )
		).toBe( '1 errore, 3 suggerimenti' );
		expect(
			etichettaSaluteVarianti( { errori: 2, avvisi: 1, suggerimenti: 1 } )
		).toBe( '2 errori, 1 avviso, 1 suggerimento' );
	} );
} );

describe( 'haProblemiVarianti', () => {
	it( 'conta errori e avvisi, non i suggerimenti', () => {
		expect(
			haProblemiVarianti( { errori: 0, avvisi: 1, suggerimenti: 0 } )
		).toBe( true );
		expect(
			haProblemiVarianti( { errori: 1, avvisi: 0, suggerimenti: 0 } )
		).toBe( true );
		expect(
			haProblemiVarianti( { errori: 0, avvisi: 0, suggerimenti: 5 } )
		).toBe( false );
		expect( haProblemiVarianti( null ) ).toBe( false );
	} );
} );

describe( 'messaggioProdottoVariabile', () => {
	it( "nomina il prodotto, l'id, la parola chiave e i tool da usare", () => {
		const testo = messaggioProdottoVariabile( {
			id: 142,
			titolo: 'Felpa',
			parola_chiave_focus: 'felpa cotone',
		} );
		expect( testo ).toContain( '"Felpa" (ID 142)' );
		expect( testo ).toContain( 'felpa cotone' );
		expect( testo ).toContain( 'leggi_seo_varianti' );
		expect( testo ).toContain( 'aggiorna_seo_varianti' );
	} );

	it( 'senza parola chiave scrive "nessuna"', () => {
		expect(
			messaggioProdottoVariabile( {
				id: 1,
				titolo: 'X',
				parola_chiave_focus: null,
			} )
		).toContain( 'nessuna' );
	} );
} );
