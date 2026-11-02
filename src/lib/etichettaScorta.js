/**
 * Come si chiama una riga dell'elenco scorte: il prodotto e, se la riga e' una
 * sua variante, quale (es. "Maglia — Taglia: L").
 *
 * @param {{ titolo: string, variante?: string|null }} riga Riga di /scorte.
 * @return {string} Etichetta da mostrare.
 */
export default function etichettaScorta( riga ) {
	return riga.variante ? `${ riga.titolo } — ${ riga.variante }` : riga.titolo;
}
