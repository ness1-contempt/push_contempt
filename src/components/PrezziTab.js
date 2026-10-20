import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Prezzi in blocco per categoria: anteprima via REST (nessuna scrittura),
 * poi applicazione solo su conferma esplicita dell'utente - stesso
 * pattern prepara/conferma già usato altrove nel plugin.
 */
export default function PrezziTab() {
	const [ categoria, setCategoria ] = useState( '' );
	const [ percentuale, setPercentuale ] = useState( '' );
	const [ delta, setDelta ] = useState( '' );
	const [ anteprima, setAnteprima ] = useState( null );
	const [ caricamento, setCaricamento ] = useState( false );
	const [ applicazione, setApplicazione ] = useState( false );
	const [ errore, setErrore ] = useState( null );
	const [ esito, setEsito ] = useState( null );

	const calcola = () => {
		if ( '' === categoria || ( '' === percentuale ) === ( '' === delta ) ) {
			setErrore( 'Specificare una categoria ed esattamente uno tra percentuale e delta.' );
			return;
		}

		setCaricamento( true );
		setErrore( null );
		setEsito( null );

		const parametri = new URLSearchParams( { categoria } );
		if ( '' !== percentuale ) {
			parametri.set( 'percentuale', percentuale );
		}
		if ( '' !== delta ) {
			parametri.set( 'delta', delta );
		}

		apiFetch( { path: `/push-contempt/v1/prezzi/calcola?${ parametri.toString() }` } )
			.then( setAnteprima )
			.catch( ( err ) => setErrore( err.message || 'Errore nel calcolo.' ) )
			.finally( () => setCaricamento( false ) );
	};

	const applica = () => {
		setApplicazione( true );
		setErrore( null );

		apiFetch( {
			path: '/push-contempt/v1/prezzi/applica',
			method: 'POST',
			data: { aggiornamenti: anteprima.aggiornamenti },
		} )
			.then( ( risposta ) => {
				setEsito( risposta );
				setAnteprima( null );
			} )
			.catch( ( err ) => setErrore( err.message || "Errore nell'applicazione." ) )
			.finally( () => setApplicazione( false ) );
	};

	return (
		<div style={ { marginTop: '16px' } }>
			<div style={ { display: 'flex', gap: '8px', alignItems: 'flex-end', marginBottom: '16px', flexWrap: 'wrap' } }>
				<TextControl label="Categoria (slug)" value={ categoria } onChange={ setCategoria } />
				<TextControl
					label="Percentuale (es. 10 o -10)"
					type="number"
					value={ percentuale }
					onChange={ ( valore ) => {
						setPercentuale( valore );
						setDelta( '' );
					} }
				/>
				<TextControl
					label="Delta fisso (es. 5 o -5)"
					type="number"
					value={ delta }
					onChange={ ( valore ) => {
						setDelta( valore );
						setPercentuale( '' );
					} }
				/>
				<Button variant="primary" onClick={ calcola } isBusy={ caricamento }>
					Calcola anteprima
				</Button>
			</div>

			{ errore && (
				<Notice status="error" isDismissible={ false }>
					{ errore }
				</Notice>
			) }

			{ esito && (
				<Notice status="success" isDismissible={ false }>
					Prezzi applicati a { esito.aggiornati.length } prodotti.
				</Notice>
			) }

			{ caricamento && <Spinner /> }

			{ ! caricamento && anteprima && (
				<>
					{ anteprima.prodotti_variabili_esclusi.length > 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ anteprima.prodotti_variabili_esclusi.length } prodotti variabili esclusi (prezzo gestito dalle varianti):{ ' ' }
							{ anteprima.prodotti_variabili_esclusi.map( ( p ) => p.titolo ).join( ', ' ) }
						</Notice>
					) }

					{ 0 === anteprima.aggiornamenti.length && <p>Nessun prodotto semplice da aggiornare in questa categoria.</p> }

					{ anteprima.aggiornamenti.length > 0 && (
						<>
							<table className="wp-list-table widefat fixed striped" style={ { marginTop: '16px', marginBottom: '16px' } }>
								<thead>
									<tr>
										<th>Prodotto</th>
										<th>Prezzo attuale</th>
										<th>Prezzo nuovo</th>
									</tr>
								</thead>
								<tbody>
									{ anteprima.aggiornamenti.map( ( riga ) => (
										<tr key={ riga.id }>
											<td>{ riga.titolo }</td>
											<td>{ riga.prezzo_regolare_attuale }</td>
											<td>{ riga.prezzo_regolare_nuovo }</td>
										</tr>
									) ) }
								</tbody>
							</table>

							<Button variant="primary" onClick={ applica } isBusy={ applicazione }>
								Applica { anteprima.aggiornamenti.length } prezzi
							</Button>
						</>
					) }
				</>
			) }
		</div>
	);
}
