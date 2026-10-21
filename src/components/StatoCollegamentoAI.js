import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

/**
 * Notice di stato del collegamento push_contempt_mcp, letto dal last_used
 * della sua Application Password (vedi CredenzialiController::handle_stato).
 * Condiviso da ogni tab che prepara un messaggio per l'assistente AI invece
 * di eseguire l'azione in prima persona (Carica prodotti, SEO).
 */
export default function StatoCollegamentoAI( { onNavigate } ) {
	const [ stato, setStato ] = useState( null );
	const [ errore, setErrore ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/push-contempt/v1/credenziali' } )
			.then( setStato )
			.catch( ( err ) => setErrore( err.message || 'Errore nel controllo dello stato.' ) );
	}, [] );

	if ( ! stato && ! errore ) {
		return <Spinner />;
	}

	if ( errore ) {
		return (
			<Notice status="error" isDismissible={ false } style={ { maxWidth: '640px', marginBottom: '16px' } }>
				{ errore }
			</Notice>
		);
	}

	if ( ! stato.esiste ) {
		return (
			<Notice status="warning" isDismissible={ false } style={ { maxWidth: '640px', marginBottom: '16px' } }>
				Nessuna credenziale push_contempt_mcp trovata.{ ' ' }
				<Button variant="link" onClick={ () => onNavigate( 'impostazioni' ) }>
					Vai a Impostazioni
				</Button>{ ' ' }
				per generarla.
			</Notice>
		);
	}

	return (
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
	);
}
