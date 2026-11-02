const TIPI = {
	simple: 'Semplice',
	variable: 'Variabile',
	grouped: 'Raggruppato',
	external: 'Esterno',
};

/**
 * Nome italiano del tipo di prodotto WooCommerce (il valore grezzo se non e' fra quelli noti).
 *
 * @param {string} tipo Tipo WooCommerce: simple, variable, grouped, external.
 * @return {string} Nome da mostrare.
 */
export function etichettaTipo( tipo ) {
	return TIPI[ tipo ] ?? tipo ?? '—';
}

/**
 * Il prezzo di un prodotto: un numero, un intervallo "da–a" se le varianti (o i
 * prodotti di un gruppo) costano cifre diverse, o un trattino se non ha prezzo.
 * Senza simbolo di valuta: dipende dal negozio.
 *
 * @param {{ prezzo_min: string|null, prezzo_max: string|null }} prodotto Riga con i prezzi del plugin.
 * @return {string} Prezzo da mostrare.
 */
export function etichettaPrezzo( prodotto ) {
	const { prezzo_min: min, prezzo_max: max } = prodotto;
	if ( null === min || undefined === min ) {
		return '—';
	}
	return null === max || max === min ? min : `${ min }–${ max }`;
}

/**
 * Per i prodotti variabili, quante varianti hanno ("3 varianti"); vuoto per gli altri.
 *
 * @param {{ numero_varianti: number|null }} prodotto Riga con `numero_varianti`.
 * @return {string} Testo, o stringa vuota.
 */
export function etichettaVarianti( prodotto ) {
	const n = prodotto.numero_varianti;
	if ( null === n || undefined === n ) {
		return '';
	}
	return 1 === n ? '1 variante' : `${ n } varianti`;
}
