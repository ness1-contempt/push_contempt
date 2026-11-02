import { messaggioCarica } from './messaggioCarica';

describe( 'messaggioCarica', () => {
	it( 'usa il percorso dato o un segnaposto', () => {
		expect( messaggioCarica( '/home/a/Prodotti' ) ).toContain( 'in /home/a/Prodotti:' );
		expect( messaggioCarica( '' ) ).toContain( '[INCOLLA QUI IL PERCORSO DELLA CARTELLA]' );
	} );

	it( 'spiega i prodotti variabili: tool, formato, anteprima con via libera, prezzo unico', () => {
		const testo = messaggioCarica( '/x' );

		expect( testo ).toContain( 'crea_prodotto_variabile' );
		expect( testo ).toContain( 'varianti: Colore: Nero, Bianco | Memoria: 256 GB, 512 GB' );
		expect( testo ).toContain( 'anteprima' );
		expect( testo ).toContain( 'aspetta il mio ok' );
		expect( testo ).toContain( 'senza inventare' );
		expect( testo ).toContain( 'Prezzo unico' );
	} );

	it( 'spiega che le cartelle sono l\'albero delle categorie e cosa fare in caso di conflitto', () => {
		const testo = messaggioCarica( '/x' );

		expect( testo ).toContain( 'Indumenti/Magliette/<Prodotto>/' );
		expect( testo ).toContain( 'percorso_categoria' );
		expect( testo ).toContain( 'esistere una volta sola' );
		expect( testo ).toContain( 'non caricare quei prodotti' );
		expect( testo ).toContain( 'senza risolverlo da solo' );
	} );
} );
