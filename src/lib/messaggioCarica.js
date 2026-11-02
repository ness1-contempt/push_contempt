/**
 * Messaggio pronto per l'assistente AI del cliente: caricare i prodotti nuovi
 * di una cartella. Spiega le cartelle come albero di categorie e i prodotti variabili, perche' e' il punto in
 * cui un assistente che fa tutto da solo puo' sbagliare: le varianti vanno
 * prima mostrate in anteprima, e se non sono scritte nel formato previsto
 * serve il via libera dell'utente.
 *
 * @param {string} cartella Percorso della cartella (vuoto = segnaposto da riempire).
 * @return {string} Il messaggio da copiare.
 */
export function messaggioCarica( cartella ) {
	const posto = cartella || '[INCOLLA QUI IL PERCORSO DELLA CARTELLA]';
	return (
		`Carica su WooCommerce i prodotti nuovi che trovi in ${ posto }: per ciascuno leggi immagini e testo ` +
		'disponibili, crea il prodotto con titolo, descrizione e categoria adatti, e scrivi anche titolo SEO e ' +
		'meta description con Yoast.\n\n' +
		'Prodotti con varianti (colori, memoria, taglie...): usa crea_prodotto_variabile. ' +
		'Se nel file di testo c\'è una riga "varianti: Colore: Nero, Bianco | Memoria: 256 GB, 512 GB" usala così com\'è. ' +
		'Se le varianti sono solo descritte nel testo, ricavale da quello che c\'è scritto davvero, senza inventare ' +
		'valori, e prima di creare mostrami l\'anteprima delle varianti e aspetta il mio ok. ' +
		'Prezzo unico per tutte le varianti, nessuna scorta.\n\n' +
		'Le cartelle rispecchiano le categorie di WooCommerce (es. Indumenti/Magliette/<Prodotto>/): ' +
		'usa il percorso_categoria che trova la scansione, così le categorie mancanti si creano una ' +
		'dentro l\'altra. Un nome di categoria deve esistere una volta sola: se la scansione segnala ' +
		'problemi con le categorie, o cartelle da controllare, non caricare quei prodotti e riportami ' +
		'il problema così com\'è, senza risolverlo da solo.'
	);
}
