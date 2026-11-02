import apiFetch from '@wordpress/api-fetch';
import { useState } from '@wordpress/element';

/**
 * Le azioni sulle versioni di un prodotto (torna alla base, al precedente, usa
 * l'attuale come base), uguali per i campi SEO e per le descrizioni: conferma
 * all'utente, chiamata REST, esito da mostrare. Cosa cambia fra i due casi sta
 * nella configurazione `azioni`.
 *
 * @param {Object}   azioni     Per ogni azione: { path, dati, esitoOk, verbo, conferma( quanti ) }.
 * @param {Function} onEseguita Chiamata a operazione riuscita (ricarica l'elenco, azzera la selezione).
 * @return {{ inCorso: boolean, esito: ?Object, rimuoviEsito: Function, esegui: Function }} Stato e azione.
 */
export default function useAzioniVersioni( azioni, onEseguita ) {
	const [ inCorso, setInCorso ] = useState( false );
	const [ esito, setEsito ] = useState( null );

	const esegui = ( azione, ids ) => {
		const { path, dati, esitoOk, conferma, verbo } = azioni[ azione ];
		const quanti = 1 === ids.length ? 'di questo prodotto' : `di ${ ids.length } prodotti`;
		if ( ! window.confirm( conferma( quanti ) ) ) {
			return;
		}
		setInCorso( true );
		setEsito( null );
		apiFetch( { path: `/push-contempt/v1${ path }`, method: 'POST', data: { ids, ...dati } } )
			.then( ( esiti ) => {
				const valori = Object.values( esiti );
				const fatti = valori.filter( ( e ) => esitoOk === e ).length;
				const gia = valori.filter( ( e ) => 'gia_in_uso' === e ).length;
				setEsito( {
					status: fatti + gia === ids.length ? 'success' : 'warning',
					testo:
						`${ verbo }: ${ fatti } su ${ ids.length }.` +
						( gia ? ` ${ gia } già uguali, lasciati come sono.` : '' ),
				} );
				onEseguita();
			} )
			.catch( ( err ) => setEsito( { status: 'error', testo: err.message || 'Operazione non riuscita.' } ) )
			.finally( () => setInCorso( false ) );
	};

	return { inCorso, esito, rimuoviEsito: () => setEsito( null ), esegui };
}
