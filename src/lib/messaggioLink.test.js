import { esitoLink, messaggioLink } from './messaggioLink';

describe( 'messaggioLink', () => {
	it( 'identifica il prodotto e nomina gli strumenti da usare', () => {
		const testo = messaggioLink( { id: 42, titolo: 'Sedia rossa' } );

		expect( testo ).toContain( '«Sedia rossa» (ID 42)' );
		expect( testo ).toContain( 'leggi_descrizione_prodotto' );
		expect( testo ).toContain( 'analizza_link_prodotto' );
		expect( testo ).toContain( 'aggiorna_descrizione_prodotto' );
	} );
} );

describe( 'esitoLink', () => {
	it( 'distingue nessun link, solo interni e link in uscita presenti', () => {
		expect( esitoLink( { totale_interni: 0, ha_link_in_uscita: false } ) ).toBe( 'Nessun link' );
		expect( esitoLink( { totale_interni: 2, ha_link_in_uscita: false } ) ).toBe( 'Manca il link in uscita' );
		expect( esitoLink( { totale_interni: 0, ha_link_in_uscita: true } ) ).toBe( 'OK' );
	} );
} );
