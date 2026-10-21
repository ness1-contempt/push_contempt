import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';

/**
 * Stato comune a ogni tab che legge dalla REST API del plugin al primo
 * caricamento e si riaggiorna solo quando l'utente lo chiede esplicitamente
 * (bottone "Aggiorna"): caricamento/errore/dati, con un trigger manuale.
 *
 * `pathFn` viene richiamata a ogni `carica()` (non solo al mount, dove viene
 * chiamata una volta sola), cosi' puo' leggere stato locale del chiamante
 * (es. una soglia scelta con una TextControl) senza che questo hook debba
 * saperne nulla - non serve passare `pathFn` tra le dipendenze dell'effetto.
 *
 * @param {Function} pathFn Restituisce il path REST (relativo, es.
 *   `/push-contempt/v1/scorte?soglia=5`) da chiamare.
 */
export default function useRestFetch( pathFn ) {
	const [ dati, setDati ] = useState( null );
	const [ caricamento, setCaricamento ] = useState( false );
	const [ errore, setErrore ] = useState( null );

	const carica = () => {
		setCaricamento( true );
		setErrore( null );
		apiFetch( { path: pathFn() } )
			.then( setDati )
			.catch( ( err ) => setErrore( err.message || 'Errore nel caricamento.' ) )
			.finally( () => setCaricamento( false ) );
	};

	useEffect( () => {
		carica();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return { dati, caricamento, errore, carica };
}
