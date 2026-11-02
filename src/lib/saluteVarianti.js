/**
 * Riepilogo a parole dei problemi trovati sulle varianti di un prodotto variabile.
 *
 * @param {{ errori: number, avvisi: number, suggerimenti: number }|null|undefined} problemi
 *   `problemi_varianti` di una riga di /seo/stato (null per i prodotti non variabili).
 * @return {string} Testo da mostrare; vuoto se il prodotto non e' variabile.
 */
export function etichettaSaluteVarianti( problemi ) {
	if ( ! problemi ) {
		return '';
	}
	const pezzi = [
		[ problemi.errori, 'errore', 'errori' ],
		[ problemi.avvisi, 'avviso', 'avvisi' ],
		[ problemi.suggerimenti, 'suggerimento', 'suggerimenti' ],
	]
		.filter( ( [ quanti ] ) => quanti > 0 )
		.map(
			( [ quanti, uno, molti ] ) =>
				`${ quanti } ${ 1 === quanti ? uno : molti }`
		);
	return pezzi.length ? pezzi.join( ', ' ) : 'Varianti a posto';
}

/**
 * Se un variabile ha almeno un errore o un avviso nelle varianti (i suggerimenti non contano).
 *
 * @param {{ errori: number, avvisi: number }|null|undefined} problemi `problemi_varianti` della riga.
 * @return {boolean} true se va controllato.
 */
export function haProblemiVarianti( problemi ) {
	return !! problemi && problemi.errori + problemi.avvisi > 0;
}

/**
 * Colore del testo per una gravita' dell'analisi delle varianti.
 */
export const COLORE_GRAVITA = {
	errore: '#b32d2e',
	avviso: '#996800',
	suggerimento: '#50575e',
};

/**
 * Messaggio pronto per l'assistente AI su un prodotto variabile.
 *
 * @param {{ id: number, titolo: string, parola_chiave_focus: string|null }} prodotto Riga della tabella.
 * @return {string} Testo da incollare nella chat.
 */
export function messaggioProdottoVariabile( prodotto ) {
	return (
		`Ottimizza la SEO del prodotto variabile "${ prodotto.titolo }" (ID ${ prodotto.id }) su WooCommerce: ` +
		`parola chiave focus attuale: ${
			prodotto.parola_chiave_focus || 'nessuna'
		}. ` +
		'Leggi prima le varianti e i problemi con leggi_seo_varianti, poi correggi titolo SEO, meta description ' +
		'e descrizione del prodotto (devono parlare del prodotto con le sue varianti, non di una sola), ' +
		'aggiorna_seo_varianti per le descrizioni e gli SKU delle varianti e gli ALT delle immagini di variante. ' +
		'Fammi vedere cosa cambierai prima di scrivere.'
	);
}
