/**
 * Le aree della valutazione da cui si puo' prendere ispirazione per i campi
 * SEO dei prodotti: solo quelle che corrispondono a un campo che l'assistente
 * scrive (titolo SEO, meta description). Le altre (intestazioni, dati
 * strutturati, contenuto, aspetti tecnici) riguardano la struttura del sito o
 * la descrizione, non questi campi.
 */
export const AREE_ISPIRAZIONE = [
	{ chiave: 'titoli', etichetta: 'Titoli SEO', nome: 'titoli SEO' },
	{ chiave: 'meta_description', etichetta: 'Meta description', nome: 'meta description' },
];

/**
 * Livelli di somiglianza che l'utente puo' fissare in anticipo (scala di
 * controlla_somiglianza: 0 nessuna somiglianza, 9 copia). Senza scelta il
 * livello lo decide lui in chat, dopo aver visto i risultati.
 */
export const LIVELLI_SOMIGLIANZA = [
	{ valore: '', etichetta: 'Lo decido io dopo aver visto i risultati' },
	{ valore: '2', etichetta: 'Fino a 2: solo coincidenze occasionali' },
	{ valore: '4', etichetta: 'Fino a 4: qualche frase in comune' },
	{ valore: '6', etichetta: 'Fino a 6: anche parti ricalcate' },
];

/**
 * Messaggio da incollare nella chat dell'assistente AI: riscrivere titolo SEO e
 * meta description di alcuni prodotti prendendo ispirazione da un sito gia'
 * analizzato, solo per le aree scelte dall'utente. Il messaggio ripete le regole
 * che l'utente ha deciso (aree, livello di somiglianza) e quelle del progetto
 * (testi originali, controllo prima di scrivere, origine tracciata).
 *
 * @param {Object}   dati
 * @param {Object[]} dati.prodotti   Righe di /seo/stato (id, titolo, parola_chiave_focus).
 * @param {Object}   dati.concorrente `ultima` di /concorrenti (sito, url, data, punteggio, aree).
 * @param {string[]} dati.aree       Chiavi delle aree scelte (vedi AREE_ISPIRAZIONE).
 * @param {string}   dati.livello    Livello massimo accettato ('' = lo decide l'utente in chat).
 */
export function messaggioIspirazione( { prodotti, concorrente, aree, livello } ) {
	const scelte = AREE_ISPIRAZIONE.filter( ( area ) => aree.includes( area.chiave ) );
	const nonScelte = AREE_ISPIRAZIONE.filter( ( area ) => ! aree.includes( area.chiave ) );
	const punteggi = scelte
		.map( ( area ) => `${ area.nome } ${ concorrente.aree[ area.chiave ]?.punteggio ?? '—' }/9` )
		.join( ', ' );
	const indirizzo = concorrente.url || `https://${ concorrente.sito }`;
	const elencoProdotti = prodotti
		.map( ( p ) => `- ${ p.titolo } (ID ${ p.id }), parola chiave focus attuale: ${ p.parola_chiave_focus || 'nessuna' }` )
		.join( '\n' );
	const regolaLivello = livello
		? `Il livello massimo di somiglianza che accetto è ${ livello } su 9: passalo come soglia a controlla_somiglianza e, se un testo lo supera, riscrivilo. Mostrami comunque punteggio e passaggi simili prima di applicare.`
		: 'Il livello di somiglianza che accetto lo scelgo io dopo aver visto i risultati: mostrami punteggio, significato e passaggi simili di ogni testo e non applicare niente finché non te lo dico.';
	const lasciare = nonScelte.length
		? ` Per ${ nonScelte.map( ( a ) => a.nome ).join( ' e ' ) } non seguire il concorrente: lascia quelli attuali (aggiorna_seo_prodotto vuole tutti e tre i campi, quindi passa quelli esistenti invariati).`
		: '';

	return [
		`Riscrivi ${ scelte.map( ( a ) => a.nome ).join( ' e ' ) } dei prodotti qui sotto prendendo ispirazione dal sito concorrente ${ concorrente.sito }.`,
		'',
		`Concorrente: ${ indirizzo } (analizzato il ${ concorrente.data || 'data non nota' }, punteggio complessivo ${ concorrente.punteggio ?? '—' }/9; nelle aree scelte: ${ punteggi }).`,
		`Le aree da cui prendere ispirazione le ho scelte io: ${ scelte.map( ( a ) => a.nome ).join( ', ' ) }.${ lasciare }`,
		'',
		'Prodotti:',
		elencoProdotti,
		'',
		'Come procedere:',
		`1. Analizza il sito del concorrente con analizza_seo_sito e usalo solo come ispirazione: scrivi testi ORIGINALI, con parole tue e solo cose vere sui nostri prodotti, senza copiare frasi, titoli o description. Mantieni la parola chiave focus attuale (se manca, proponine una).`,
		`2. Prima di applicare, controlla ogni testo con controlla_somiglianza. ${ regolaLivello }`,
		`3. Applica con aggiorna_seo_prodotto passando origine="concorrente ${ concorrente.sito }".`,
		'4. Alla fine mostrami prima e dopo di ogni prodotto. Le versioni precedenti restano nella tab SEO (base e precedente): da lì posso tornare indietro.',
	].join( '\n' );
}
