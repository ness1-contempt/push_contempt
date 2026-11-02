import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import LinkProdotto from './LinkProdotto';
import MessaggioProntoAI from './MessaggioProntoAI';
import StatoCollegamentoAI from './StatoCollegamentoAI';
import useRestFetch from '../hooks/useRestFetch';
import { messaggioCarica } from '../lib/messaggioCarica';

/**
 * Prodotti creati da crea_prodotto e non ancora pubblicati: promemoria
 * persistente di cosa resta da rivedere. Non serve un tracciamento a parte,
 * lo stato "draft" di WooCommerce stesso e' l'elenco (vedi
 * ProdottiController::handle_bozze) - si ricarica da zero ogni volta che si
 * entra in questa tab, cosi' resta aggiornato dopo ogni nuova creazione.
 */
function ProdottiInBozza() {
	// caricamento non serve qui: a differenza delle altre tab-elenco, un
	// refresh manuale lascia visibile la lista precedente invece di
	// nasconderla dietro uno Spinner (nessun flash tra un "Aggiorna" e l'altro).
	const { dati: prodotti, errore, carica } = useRestFetch(
		() => '/push-contempt/v1/prodotti/bozze'
	);

	return (
		<div style={ { marginTop: '32px', maxWidth: '640px' } }>
			<div style={ { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' } }>
				<h3 style={ { margin: 0 } }>Prodotti in bozza</h3>
				<Button variant="link" onClick={ carica }>
					Aggiorna
				</Button>
			</div>
			<p style={ { marginBottom: '16px', color: '#50575e' } }>
				Prodotti creati dal tuo assistente AI ma non ancora pubblicati: apri ciascuno per rivederlo
				(testo, immagini, SEO) e pubblicarlo da wp-admin quando è pronto.
			</p>

			{ errore && (
				<Notice status="error" isDismissible={ false }>
					{ errore }
				</Notice>
			) }

			{ ! prodotti && ! errore && <Spinner /> }

			{ prodotti && 0 === prodotti.length && <p>Nessun prodotto in bozza al momento.</p> }

			{ prodotti && prodotti.length > 0 && (
				<ul>
					{ prodotti.map( ( prodotto ) => (
						<li key={ prodotto.id }>
							<LinkProdotto id={ prodotto.id }>{ prodotto.titolo }</LinkProdotto>
							{ 'variable' === prodotto.tipo && ` — variabile, ${ prodotto.numero_varianti } varianti` }
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

/**
 * Caricamento prodotti: richiede testo libero ed estrazione di contenuto da
 * file sul computer del cliente, impossibile da un browser - serve il suo
 * assistente AI (Claude Code, Cursor, ecc. - con push_contempt_mcp
 * configurato, vedi tab Impostazioni). Questa tab non esegue nulla: prepara
 * il percorso cartella e il messaggio da incollare, e mostra se il bridge si
 * e' gia' collegato.
 */
export default function CaricaProdottiTab( { onNavigate } ) {
	const [ cartella, setCartella ] = useState( '' );

	return (
		<div style={ { marginTop: '16px' } }>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Il caricamento non avviene da qui: serve leggere file sul tuo computer e scrivere i testi con
				l'AI, cose che un browser non può fare. Lo fa il tuo assistente AI - Claude Code, Cursor, Windsurf
				e simili (con push_contempt_mcp configurato - vedi la tab Impostazioni se non l'hai ancora fatto).
				Qui sotto prepari il percorso e il messaggio da incollare nella chat con il tuo assistente AI.
			</p>

			<StatoCollegamentoAI onNavigate={ onNavigate } />

			<ol style={ { maxWidth: '640px', marginBottom: '16px', color: '#1e1e1e' } }>
				<li>Trova la cartella dei prodotti sul tuo computer.</li>
				<li>
					Copiane il percorso completo: su Windows, Maiusc + click destro sulla cartella → "Copia come
					percorso"; su Mac, Option + click destro → "Copia [cartella] come nome percorso" (oppure
					trascinala su un terminale, se ne hai uno aperto).
				</li>
				<li>Incolla quel percorso nel campo qui sotto.</li>
			</ol>

			<div style={ { maxWidth: '640px', marginBottom: '16px' } }>
				<TextControl
					label="Percorso della cartella prodotti"
					value={ cartella }
					onChange={ setCartella }
					placeholder="/Utenti/nome/Scrivania/Prodotti"
					__next40pxDefaultSize
				/>
			</div>

			<MessaggioProntoAI testo={ messaggioCarica( cartella.trim() ) } />

			<details style={ { maxWidth: '640px', margin: '16px 0' } }>
				<summary style={ { cursor: 'pointer', fontWeight: 600 } }>Come organizzare le cartelle (categorie)</summary>
				<p>
					Le cartelle sono lo <strong>specchio delle categorie</strong> di WooCommerce, a qualsiasi profondità
					(fino a 5 livelli). Una cartella con le foto e un file di testo è un prodotto; le cartelle sopra di lei
					sono le sue categorie, una dentro l'altra:
				</p>
				<pre style={ { background: '#f0f0f1', padding: '8px', whiteSpace: 'pre-wrap' } }>
					Prodotti/{ '\n' }
					└ Indumenti/{ '\n' }
					{ '  ' }└ Magliette/{ '\n' }
					{ '    ' }└ Maglietta rossa/   ← foto + file di testo
				</pre>
				<p>
					Le categorie che mancano vengono create nel posto giusto (qui Magliette dentro Indumenti), quelle che
					esistono già si riusano, e il prodotto va nella categoria più in basso.
				</p>
				<p>
					<strong>Un nome di categoria può esistere una volta sola</strong> in tutto l'albero. Se la stessa
					categoria compare in due punti (fra le cartelle, o fra le cartelle e il sito, per esempio Magliette sia
					dentro Indumenti sia dentro Sport) il caricamento di quei prodotti{ ' ' }
					<strong>viene fermato e il problema ti viene segnalato</strong>, senza creare niente: sistema le cartelle
					o le categorie e riprova. Vengono segnalate anche le cartelle dubbie (con foto e sottocartelle insieme, o
					un prodotto fuori da una cartella di categoria).
				</p>
			</details>

			<details style={ { maxWidth: '640px', margin: '16px 0' } }>
				<summary style={ { cursor: 'pointer', fontWeight: 600 } }>Prodotti con varianti (colori, memoria, taglie)</summary>
				<p>
					Un prodotto <strong>variabile</strong> ha più varianti dello stesso articolo. Si creano sempre in
					bozza, con <strong>un prezzo unico</strong> per tutte le varianti e <strong>senza scorte</strong>{ ' ' }
					(prezzi e scorte per singola variante si cambiano poi da wp-admin).
				</p>
				<p>
					<strong>Modo preciso.</strong> Nel file di testo del prodotto scrivi una riga sola con gli attributi
					separati da <code>|</code> e i valori separati da virgola:
				</p>
				<pre style={ { background: '#f0f0f1', padding: '8px', whiteSpace: 'pre-wrap' } }>
					varianti: Colore: Nero, Bianco | Memoria: 256 GB, 512 GB
				</pre>
				<p>
					Le varianti sono tutte le combinazioni (qui 4). Valori e nomi non possono contenere <code>|</code> o
					virgole; al massimo 4 attributi e 50 varianti.
				</p>
				<p>
					<strong>Modo automatico.</strong> Senza quella riga l'assistente prova a ricavare le varianti dal testo
					libero. È più comodo ma può sbagliare: leggere male un valore, inventarne uno, scambiare una
					descrizione per una variante. Per questo prima di creare deve mostrarti l'
					<strong>anteprima</strong> (le varianti che nascerebbero) e aspettare il tuo ok. Controllala sempre.
				</p>
			</details>

			<ProdottiInBozza />
		</div>
	);
}
