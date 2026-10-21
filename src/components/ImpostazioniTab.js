import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';

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
	const [ copiato, setCopiato ] = useState( false );

	const genera = () => {
		setCaricamento( true );
		setErrore( null );
		setCopiato( false );
		apiFetch( { path: '/push-contempt/v1/credenziali', method: 'POST' } )
			.then( setCredenziali )
			.catch( ( err ) => setErrore( err.message || 'Errore nella generazione.' ) )
			.finally( () => setCaricamento( false ) );
	};

	const testoEnv = credenziali
		? `WP_SITE_URL=${ credenziali.sito }\nWP_APP_USER=${ credenziali.utente }\nWP_APP_PASSWORD=${ credenziali.password }`
		: '';

	const copia = () => {
		navigator.clipboard.writeText( testoEnv ).then( () => setCopiato( true ) );
	};

	return (
		<div style={ { marginTop: '16px' } }>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Genera le credenziali per collegare la tua sessione Claude Code (con push_contempt_mcp installato) a
				questo sito: niente terminale, niente da cercare in wp-admin. Copia il blocco e incollalo nel file{ ' ' }
				<code>.env</code> di push_contempt_mcp. Rigenerando, le credenziali precedenti smettono subito di
				funzionare.
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
					<pre
						style={ {
							background: '#1e1e1e',
							color: '#f0f0f1',
							padding: '12px',
							borderRadius: '4px',
							overflowX: 'auto',
							marginTop: '12px',
							fontSize: '13px',
						} }
					>
						{ testoEnv }
					</pre>
					<Button variant="secondary" onClick={ copia }>
						{ copiato ? 'Copiato ✓' : 'Copia' }
					</Button>
				</div>
			) }
		</div>
	);
}
