import { etichettaPrezzo, etichettaTipo, etichettaVarianti } from './tipoPrezzo';

describe( 'etichettaTipo', () => {
	it( 'traduce i tipi noti e lascia gli altri', () => {
		expect( etichettaTipo( 'variable' ) ).toBe( 'Variabile' );
		expect( etichettaTipo( 'simple' ) ).toBe( 'Semplice' );
		expect( etichettaTipo( 'bundle' ) ).toBe( 'bundle' );
		expect( etichettaTipo( undefined ) ).toBe( '—' );
	} );
} );

describe( 'etichettaPrezzo', () => {
	it( 'mostra un numero, un intervallo o un trattino', () => {
		expect( etichettaPrezzo( { prezzo_min: '19.90', prezzo_max: '19.90' } ) ).toBe( '19.90' );
		expect( etichettaPrezzo( { prezzo_min: '20.00', prezzo_max: '29.00' } ) ).toBe( '20.00–29.00' );
		expect( etichettaPrezzo( { prezzo_min: '5', prezzo_max: null } ) ).toBe( '5' );
		expect( etichettaPrezzo( { prezzo_min: null, prezzo_max: null } ) ).toBe( '—' );
		expect( etichettaPrezzo( {} ) ).toBe( '—' );
	} );
} );

describe( 'etichettaVarianti', () => {
	it( 'conta le varianti solo dove ci sono', () => {
		expect( etichettaVarianti( { numero_varianti: 3 } ) ).toBe( '3 varianti' );
		expect( etichettaVarianti( { numero_varianti: 1 } ) ).toBe( '1 variante' );
		expect( etichettaVarianti( { numero_varianti: 0 } ) ).toBe( '0 varianti' );
		expect( etichettaVarianti( { numero_varianti: null } ) ).toBe( '' );
	} );
} );
