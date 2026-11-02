/**
 * Messaggi pronti per l'assistente AI del cliente, per i due lavori della tab
 * Contenuti che richiedono di scrivere testo. Il testo vero sta sul sito:
 * l'assistente lo legge con i suoi strumenti, qui basta dire quale prodotto e
 * in che ordine usarli.
 */

/**
 * Riscrivere la descrizione di un prodotto.
 *
 * @param {{ id: number, titolo: string, parola_chiave_focus: ?string }} prodotto Riga di /descrizione/stato.
 * @return {string} Il messaggio da copiare.
 */
export function messaggioDescrizione( prodotto ) {
	return (
		`Riscrivi la descrizione del prodotto «${ prodotto.titolo }» (ID ${ prodotto.id }) ` +
		`- parola chiave focus: ${ prodotto.parola_chiave_focus || 'nessuna' }.\n\n` +
		'1. Leggi la descrizione attuale con leggi_descrizione_prodotto.\n' +
		'2. Scrivila di nuovo in HTML: la parola chiave nel testo e in un titolo H2, almeno un link interno ' +
		'e uno in uscita, senza inventare dati tecnici che non conosci.\n' +
		'3. Salvala con aggiorna_descrizione_prodotto e mostrami prima e dopo (potrò sempre tornare alla versione precedente).'
	);
}

/**
 * Scrivere gli ALT delle immagini di un prodotto.
 *
 * @param {{ id: number, titolo: string, parola_chiave_focus: ?string }} prodotto Riga di /alt/stato.
 * @return {string} Il messaggio da copiare.
 */
export function messaggioAlt( prodotto ) {
	return (
		`Scrivi il testo ALT delle immagini del prodotto «${ prodotto.titolo }» (ID ${ prodotto.id }) ` +
		`- parola chiave focus: ${ prodotto.parola_chiave_focus || 'nessuna' }.\n\n` +
		'1. Leggi le immagini con leggi_alt_immagini_prodotto.\n' +
		'2. Per ciascuna scrivi un ALT breve che descriva davvero cosa si vede e contenga la parola chiave ' +
		'(non ripeterla uguale in tutte).\n' +
		'3. Salvali con aggiorna_alt_immagini_prodotto e mostrami prima e dopo.'
	);
}

/**
 * Testo di una descrizione HTML senza tag, accorciato per un'anteprima.
 *
 * @param {?string} html   Descrizione in HTML.
 * @param {number}  massimo Caratteri massimi dell'anteprima.
 * @return {string} Testo semplice, con i puntini se tagliato.
 */
export function anteprimaHtml( html, massimo = 220 ) {
	const testo = ( html ?? '' )
		.replace( /<[^>]*>/g, ' ' )
		.replace( /&nbsp;/g, ' ' )
		.replace( /&amp;/g, '&' )
		.replace( /\s+/g, ' ' )
		.trim();
	return testo.length > massimo ? `${ testo.slice( 0, massimo ).trimEnd() }…` : testo;
}
