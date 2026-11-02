import apiFetch from '@wordpress/api-fetch';
import { Notice, ToggleControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

/**
 * Accende o spegne i dati strutturati "ProductGroup" dei prodotti variabili
 * (vedi SchemaVarianti nel plugin): una riga per variante nel markup della
 * scheda prodotto, invece del solo intervallo di prezzo di WooCommerce.
 */
export default function InterruttoreSchemaVarianti() {
	const [ attivo, setAttivo ] = useState( null );
	const [ errore, setErrore ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/push-contempt/v1/seo/schema-varianti' } )
			.then( ( risposta ) => setAttivo( risposta.attivo ) )
			.catch( ( err ) =>
				setErrore( err.message || 'Errore nel caricamento.' )
			);
	}, [] );

	const cambia = ( valore ) => {
		setErrore( null );
		apiFetch( {
			path: '/push-contempt/v1/seo/schema-varianti',
			method: 'POST',
			data: { attivo: valore },
		} )
			.then( ( risposta ) => setAttivo( risposta.attivo ) )
			.catch( ( err ) =>
				setErrore( err.message || 'Errore nel salvataggio.' )
			);
	};

	if ( null === attivo && ! errore ) {
		return null;
	}

	return (
		<div style={ { margin: '16px 0', maxWidth: '780px' } }>
			{ null !== attivo && (
				<ToggleControl
					label="Dati strutturati dei prodotti variabili"
					help={
						attivo
							? 'Acceso: nella scheda di un prodotto variabile Google riceve una voce per ogni variante (colore, taglia, prezzo, disponibilità). Se i dati delle varianti non sono affidabili, resta il markup di WooCommerce.'
							: 'Spento: la scheda dei prodotti variabili ha solo il markup di WooCommerce (un unico prezzo minimo–massimo).'
					}
					checked={ attivo }
					onChange={ cambia }
					__nextHasNoMarginBottom
				/>
			) }
			{ errore && (
				<Notice status="error" isDismissible={ false }>
					{ errore }
				</Notice>
			) }
		</div>
	);
}
