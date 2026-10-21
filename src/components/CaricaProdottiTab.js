import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

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
	const [ stato, setStato ] = useState( null );
	const [ erroreStato, setErroreStato ] = useState( null );
	const [ cartella, setCartella ] = useState( '' );
	const [ copiato, setCopiato ] = useState( false );

	useEffect( () => {
		apiFetch( { path: '/push-contempt/v1/credenziali' } )
			.then( setStato )
			.catch( ( err ) => setErroreStato( err.message || 'Errore nel controllo dello stato.' ) );
	}, [] );

	const copiaMessaggio = () => {
		navigator.clipboard.writeText( messaggioPronto( cartella.trim() ) ).then( () => setCopiato( true ) );
	};

	return (
		<div style={ { marginTop: '16px' } }>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Il caricamento non avviene da qui: serve leggere file sul tuo computer e scrivere i testi con
				l'AI, cose che un browser non può fare. Lo fa il tuo assistente AI - Claude Code, Cursor, Windsurf
				e simili (con push_contempt_mcp configurato - vedi la tab Impostazioni se non l'hai ancora fatto).
				Qui sotto prepari il percorso e il messaggio da incollargli.
			</p>

			{ ! stato && ! erroreStato && <Spinner /> }

			{ erroreStato && (
				<Notice status="error" isDismissible={ false } style={ { maxWidth: '640px', marginBottom: '16px' } }>
					{ erroreStato }
				</Notice>
			) }

			{ stato && ! stato.esiste && (
				<Notice status="warning" isDismissible={ false } style={ { maxWidth: '640px', marginBottom: '16px' } }>
					Nessuna credenziale push_contempt_mcp trovata.{ ' ' }
					<Button variant="link" onClick={ () => onNavigate( 'impostazioni' ) }>
						Vai a Impostazioni
					</Button>{ ' ' }
					per generarla.
				</Notice>
			) }

			{ stato && stato.esiste && (
				<Notice
					status={ stato.ultimo_utilizzo ? 'success' : 'info' }
					isDismissible={ false }
					style={ { maxWidth: '640px', marginBottom: '16px' } }
				>
					{ stato.ultimo_utilizzo
						? `Ultimo collegamento di push_contempt_mcp: ${ new Date(
								stato.ultimo_utilizzo * 1000
						  ).toLocaleString( 'it-IT' ) }.`
						: "Credenziali generate, ma push_contempt_mcp non si è ancora collegato da qui." }
				</Notice>
			) }

			<ol style={ { maxWidth: '640px', marginBottom: '16px', color: '#1e1e1e' } }>
				<li>Apri il terminale dove hai il tuo assistente AI collegato (con push_contempt_mcp configurato).</li>
				<li>Trascina lì la cartella dei prodotti: comparirà il percorso completo.</li>
				<li>Copia quel percorso e incollalo nel campo qui sotto.</li>
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

			<p style={ { maxWidth: '640px', fontWeight: 600, marginBottom: '8px' } }>
				Messaggio pronto per il tuo assistente AI:
			</p>
			<pre
				style={ {
					maxWidth: '640px',
					background: '#1e1e1e',
					color: '#f0f0f1',
					padding: '12px',
					borderRadius: '4px',
					whiteSpace: 'pre-wrap',
					fontSize: '13px',
					marginBottom: '12px',
				} }
			>
				{ messaggioPronto( cartella.trim() ) }
			</pre>
			<Button variant="primary" onClick={ copiaMessaggio }>
				{ copiato ? 'Copiato ✓' : 'Copia messaggio' }
			</Button>
		</div>
	);
}
