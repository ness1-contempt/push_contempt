import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import MessaggioProntoAI from './MessaggioProntoAI';

/**
 * Genera una Application Password nativa di WordPress per collegare
 * push_contempt_mcp a questo sito, senza passare da wp-admin -> Profilo ne'
 * da un terminale. A differenza dei token JWT usati da altri server MCP per
 * WordPress (che scadono ogni poche ore), questa non scade da sola - resta
 * valida finche' non viene rigenerata o revocata.
 */
export default function ImpostazioniTab() {
	const [ credenziali, setCredenziali ] = useState( null );
	const [ caricamento, setCaricamento ] = useState( false );
	const [ errore, setErrore ] = useState( null );

	const genera = () => {
		setCaricamento( true );
		setErrore( null );
		apiFetch( { path: '/push-contempt/v1/credenziali', method: 'POST' } )
			.then( setCredenziali )
			.catch( ( err ) => setErrore( err.message || 'Errore nella generazione.' ) )
			.finally( () => setCaricamento( false ) );
	};

	const testoEnv = credenziali
		? `WP_SITE_URL=${ credenziali.sito }\nWP_APP_USER=${ credenziali.utente }\nWP_APP_PASSWORD=${ credenziali.password }`
		: '';

	return (
		<div style={ { marginTop: '16px' } }>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Genera le credenziali per collegare il tuo assistente AI (Claude Code, Cursor, Windsurf e simili) a
				questo sito dopo aver installato push_contempt_mcp. Una volta cliccato il bottone "Genera credenziali"
				le vecchie smettono di funzionare, quindi copiale e incollale nel file <code>.env</code> che si trova
				nella cartella push_contempt_mcp sostituendo i vecchi campi con i nuovi generati.
			</p>

			<Button variant="primary" onClick={ genera } isBusy={ caricamento }>
				{ credenziali ? 'Rigenera credenziali' : 'Genera credenziali' }
			</Button>

			{ errore && (
				<Notice status="error" isDismissible={ false } style={ { marginTop: '16px', maxWidth: '640px' } }>
					{ errore }
				</Notice>
			) }

			{ credenziali && (
				<div style={ { marginTop: '16px', maxWidth: '640px' } }>
					<Notice status="warning" isDismissible={ false }>
						Questa password è mostrata una sola volta: WordPress non la rimemorizza in chiaro. Copiala
						subito.
					</Notice>
					<div style={ { marginTop: '12px' } }>
						<MessaggioProntoAI
							testo={ testoEnv }
							etichetta="Incolla in push_contempt_mcp/.env:"
							etichettaBottone="Copia"
						/>
					</div>
				</div>
			) }
		</div>
	);
}
