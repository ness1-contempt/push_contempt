/**
 * Messaggio pronto per l'assistente AI del cliente: scrivere articoli per il blog e salvarli in
 * bozza. Brief, prodotti e articoli gia' presenti li legge l'assistente dal plugin con i suoi
 * tool (leggi_brief_blog, contesto_blog): qui si dicono solo le regole che non devono saltare.
 *
 * @param {number}  nArticoli Quanti articoli scrivere in questa sessione.
 * @param {boolean} aeoGeo    Se nel brief e' attiva l'ottimizzazione AEO/GEO.
 * @return {string} Il messaggio da copiare.
 */
export function messaggioBlog( nArticoli, aeoGeo = true ) {
	const quanti = 1 === nArticoli ? 'un articolo' : `${ nArticoli } articoli`;
	return (
		`Scrivi ${ quanti } per il blog del negozio e salvali in bozza. Parti da leggi_brief_blog e ` +
		'contesto_blog: argomento, pubblico, tono, lunghezza, prodotti e articoli già presenti sono ' +
		'salvati nel plugin.\n\n' +
		'Prima di scrivere proponimi il piano (per ogni articolo titolo, frase chiave e prodotti da ' +
		'linkare, senza ripetere argomenti già presenti) e aspetta il mio ok.\n\n' +
		( aeoGeo
			? 'Ottimizza ogni articolo per AEO e GEO seguendo le regole che trovi in leggi_brief_blog ' +
			  '(regole_aeo_geo): domande come sottotitoli con risposta breve, elenchi, tabelle, FAQ.\n\n'
			: '' ) +
		'Poi scrivi ogni articolo in HTML con H2/H3, usando solo informazioni che puoi verificare ' +
		'(cerca le fonti sul web e linkane almeno una): niente dati, statistiche, citazioni o ' +
		'caratteristiche dei prodotti inventati, e niente prezzi nel testo. Linka i prodotti con gli ' +
		'indirizzi di contesto_blog. Le immagini non vanno generate: usa quelle dei prodotti o ' +
		'quelle che ti indico io, con un ALT descrittivo.\n\n' +
		'Salva con crea_articolo_bozza, correggi gli avvisi che restituisce e alla fine riportami per ' +
		'ogni articolo titolo, parole, link e indirizzo di modifica. Non pubblicare niente: le bozze ' +
		'le rivedo e le pubblico io.'
	);
}
