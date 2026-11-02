/**
 * Messaggio pronto per l'assistente AI del cliente: aggiungere un link in
 * uscita alla descrizione di un prodotto che non ne ha. Il testo resta sul
 * sito (l'assistente lo legge con i suoi strumenti), qui basta identificare il
 * prodotto e dire quali strumenti usare, nell'ordine giusto.
 *
 * @param {{ id: number, titolo: string }} prodotto Riga del riepilogo dei link.
 * @return {string} Il messaggio da copiare.
 */
export function messaggioLink( prodotto ) {
	return (
		`Aggiungi un link in uscita alla descrizione del prodotto «${ prodotto.titolo }» (ID ${ prodotto.id }).\n\n` +
		'1. Leggi la descrizione attuale con leggi_descrizione_prodotto e i link che ha già con analizza_link_prodotto.\n' +
		'2. Scegli un link pertinente verso una fonte autorevole (sito del produttore, scheda tecnica ufficiale) ' +
		'e inseriscilo nel testo in modo naturale, senza cambiare il resto.\n' +
		'3. Scrivi la nuova descrizione con aggiorna_descrizione_prodotto e mostrami prima e dopo.'
	);
}

/**
 * Cosa dice la colonna "Esito" per un prodotto del riepilogo.
 *
 * @param {{ totale_interni: number, ha_link_in_uscita: boolean }} riga Riga del riepilogo.
 * @return {string} Esito in italiano.
 */
export function esitoLink( riga ) {
	if ( ! riga.ha_link_in_uscita && 0 === riga.totale_interni ) {
		return 'Nessun link';
	}
	if ( ! riga.ha_link_in_uscita ) {
		return 'Manca il link in uscita';
	}
	return 'OK';
}
