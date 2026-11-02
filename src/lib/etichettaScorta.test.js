import etichettaScorta from './etichettaScorta';

describe( 'etichettaScorta', () => {
	it( 'per un prodotto e il suo titolo', () => {
		expect( etichettaScorta( { titolo: 'Sedia', variante: null } ) ).toBe( 'Sedia' );
		expect( etichettaScorta( { titolo: 'Sedia' } ) ).toBe( 'Sedia' );
	} );

	it( 'per una variante aggiunge quale', () => {
		expect( etichettaScorta( { titolo: 'Maglia', variante: 'Taglia: L' } ) ).toBe( 'Maglia — Taglia: L' );
	} );
} );
