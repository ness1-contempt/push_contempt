/**
 * Spiegazioni per l'utente della tab SEO. Due argomenti, ciascuno vicino alla
 * parte di schermata che descrive: le versioni dei campi SEO ("versioni") e il
 * confronto con i siti analizzati ("concorrenti"). Sono a scomparsa, chiuse di
 * default, cosi' non occupano spazio a chi le conosce gia'.
 */

const STILE_DETTAGLIO = {
	maxWidth: '780px',
	margin: '0 0 16px',
	padding: '8px 14px',
	background: '#fff',
	border: '1px solid #dcdcde',
	borderLeft: '4px solid #2271b1',
};

const STILE_ELENCO = { margin: '6px 0 6px 18px', listStyle: 'disc' };

function Versioni() {
	return (
		<>
			<p>
				Ogni volta che il tuo assistente AI scrive titolo SEO, meta description e parola chiave di un prodotto,
				il plugin tiene traccia di <strong>tre versioni</strong>, così non perdi mai niente:
			</p>
			<ul style={ STILE_ELENCO }>
				<li>
					<strong>Base</strong>: il punto di partenza, com'era il prodotto prima del primo intervento (di
					norma quello che avevi in Yoast). Non cambia mentre lavori. Puoi ridefinirla tu con «Usa l'attuale
					come nuova base»: da quel momento la base non è più l'originale di Yoast, e quello stato non si
					recupera più.
				</li>
				<li>
					<strong>Precedente</strong>: la versione da cui vieni, cioè quella che c'era prima dell'ultima
					modifica, comprese le correzioni fatte a mano in Yoast.
				</li>
				<li>
					<strong>Attuale</strong>: quella in uso adesso in Yoast, la stessa che vede Google.
				</li>
			</ul>
			<p>
				<strong>Come si usa.</strong> Nella colonna «Versioni» clicca «Mostra versioni» sulla riga di un
				prodotto: vedi le tre versioni affiancate, con chi le ha scritte (es. «concorrente x.it» o «modifica
				manuale») e quando. «Torna alla base» e «Torna al precedente» rimettono quei testi in Yoast. La
				versione che lasci diventa il «precedente», quindi puoi sempre tornare indietro. Per più prodotti
				insieme spunta «Seleziona» e usa la barra che compare sopra la tabella. Prima di ogni azione ti viene
				chiesta una conferma.
			</p>
			<p>
				<strong>Da sapere.</strong> Le versioni riguardano solo i tre campi SEO, non la descrizione del
				prodotto. Un prodotto compare con le versioni solo dopo che il plugin ha scritto i suoi campi SEO
				almeno una volta; fino ad allora nella colonna c'è un trattino.
			</p>
		</>
	);
}

function Concorrenti() {
	return (
		<>
			<p>
				Qui compaiono i siti che hai fatto analizzare al tuo assistente AI, con un punteggio da 0 a 9 per area.
				Serve a capire come sono fatti i siti dei concorrenti e dove il tuo si può migliorare.
			</p>
			<ol style={ { margin: '6px 0 6px 18px', listStyle: 'decimal' } }>
				<li>
					<strong>Chiedi l'analisi</strong> al tuo assistente AI, ad esempio: «Analizza il sito
					https://… e dimmi com'è messo con la SEO». L'analisi gira sul tuo computer, non sul tuo sito, e
					richiede da qualche decina di secondi a un paio di minuti.
				</li>
				<li>
					<strong>Guarda il risultato qui.</strong> Clicca sul nome di un sito per vedere le prove dietro ogni
					voto (es. «meta description con testo proprio: 3 su 14») e i limiti che hanno abbassato un'area.
				</li>
				<li>
					<strong>Fai analizzare anche il tuo sito.</strong> Compare per primo come «Il tuo sito» e per gli
					altri vedi la differenza area per area: (+2) vuol dire due punti meglio di te, (−1) un punto peggio.
				</li>
				<li>
					<strong>Decidi tu come usarlo.</strong> Spunta i prodotti nella tabella in alto e vai al pannello
					«Riscrivi con ispirazione da un concorrente»: scegli il sito, le aree da cui prendere ispirazione
					(titoli SEO e/o meta description) e, se vuoi, fino a che livello di somiglianza accetti (da 0,
					nessuna, a 9, copia). Il pannello prepara un messaggio da incollare al tuo assistente, che scrive
					testi originali e li controlla prima di applicarli: se non hai fissato un livello, lo scegli tu
					dopo aver visto i risultati e non viene applicato niente prima. Le modifiche portano il nome del
					concorrente e le puoi annullare dalle versioni.
				</li>
			</ol>
			<p>
				<strong>Cosa misura il punteggio.</strong> La struttura SEO del sito: titoli, meta description,
				intestazioni, dati strutturati, contenuto e aspetti tecnici, su un campione di pagine. Non misura
				posizionamento, autorevolezza, velocità né qualità dei testi, ed è solo un riferimento: un sito
				mediocre può essere un buon modello per una sola area. Del sito analizzato qui restano solo i
				punteggi, mai i suoi testi.
			</p>
		</>
	);
}

/**
 * @param {Object} props
 * @param {string} props.argomento "versioni" o "concorrenti".
 */
export default function GuidaSeo( { argomento } ) {
	const titolo = 'versioni' === argomento ? 'Come funzionano le versioni' : 'Come usare il confronto con i concorrenti';
	return (
		<details style={ STILE_DETTAGLIO }>
			<summary style={ { cursor: 'pointer', fontWeight: 600, padding: '4px 0' } }>{ titolo }</summary>
			<div style={ { padding: '4px 0 6px' } }>{ 'versioni' === argomento ? <Versioni /> : <Concorrenti /> }</div>
		</details>
	);
}
