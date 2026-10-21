import { TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import MessaggioProntoAI from './MessaggioProntoAI';
import StatoCollegamentoAI from './StatoCollegamentoAI';

/**
 * Messaggio pronto da incollare nella chat dell'assistente AI del cliente.
 *
 * @param {string} cartella Percorso della cartella prodotti (puo' essere vuoto).
 */
function messaggioPronto( cartella ) {
	const posto = cartella || '[INCOLLA QUI IL PERCORSO DELLA CARTELLA]';
	return (
		`Carica su WooCommerce i prodotti nuovi che trovi in ${ posto }: per ciascuno leggi immagini e testo ` +
		'disponibili, crea il prodotto con titolo, descrizione e categoria adatti, e scrivi anche titolo SEO e ' +
		'meta description con Yoast.'
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

			<MessaggioProntoAI testo={ messaggioPronto( cartella.trim() ) } />
		</div>
	);
}
