import { Button } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

/**
 * Blocco "messaggio pronto da copiare" condiviso da ogni tab che prepara un
 * testo per l'assistente AI del cliente invece di eseguire l'azione in
 * prima persona (Carica prodotti, SEO). Il bottone torna a "Copia messaggio"
 * ogni volta che il testo cambia (es. cambio di prodotto selezionato).
 */
export default function MessaggioProntoAI( {
	testo,
	etichetta = 'Messaggio pronto per il tuo assistente AI:',
	etichettaBottone = 'Copia messaggio',
} ) {
	const [ copiato, setCopiato ] = useState( false );

	useEffect( () => {
		setCopiato( false );
	}, [ testo ] );

	const copia = () => {
		navigator.clipboard.writeText( testo ).then( () => setCopiato( true ) );
	};

	return (
		<div style={ { maxWidth: '640px' } }>
			<p style={ { fontWeight: 600, marginBottom: '8px' } }>{ etichetta }</p>
			<pre
				style={ {
					background: '#1e1e1e',
					color: '#f0f0f1',
					padding: '12px',
					borderRadius: '4px',
					whiteSpace: 'pre-wrap',
					fontSize: '13px',
					marginBottom: '12px',
				} }
			>
				{ testo }
			</pre>
			<Button variant="primary" onClick={ copia }>
				{ copiato ? 'Copiato ✓' : etichettaBottone }
			</Button>
		</div>
	);
}
