import apiFetch from '@wordpress/api-fetch';
import { Button, CheckboxControl, Notice, SelectControl, Spinner, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

/**
 * Prezzi in blocco per categoria: anteprima via REST (nessuna scrittura),
 * poi scelta di quali prodotti scontare e applicazione solo su conferma
 * esplicita dell'utente - stesso pattern prepara/conferma già usato altrove
 * nel plugin.
 */
export default function PrezziTab() {
	const [ categorie, setCategorie ] = useState( null );
	const [ categoria, setCategoria ] = useState( '' );
	const [ percentuale, setPercentuale ] = useState( '' );
	const [ delta, setDelta ] = useState( '' );
	const [ anteprima, setAnteprima ] = useState( null );
	const [ selezionati, setSelezionati ] = useState( new Set() );
	const [ caricamento, setCaricamento ] = useState( false );
	const [ applicazione, setApplicazione ] = useState( false );
	const [ errore, setErrore ] = useState( null );
	const [ esito, setEsito ] = useState( null );

	// Categorie prodotto per il menu a tendina: endpoint nativo di WordPress,
	// WooCommerce registra "product_cat" come tassonomia REST-abilitata.
	useEffect( () => {
		apiFetch( { path: '/wp/v2/product_cat?per_page=100&orderby=name&order=asc' } )
			.then( ( risposta ) => setCategorie( risposta.filter( ( cat ) => cat.count > 0 ) ) )
			.catch( () => setCategorie( [] ) );
	}, [] );

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
			.then( ( risposta ) => {
				setAnteprima( risposta );
				// Di default tutti i prodotti proposti sono selezionati per lo sconto.
				setSelezionati( new Set( risposta.aggiornamenti.map( ( riga ) => riga.id ) ) );
			} )
			.catch( ( err ) => setErrore( err.message || 'Errore nel calcolo.' ) )
			.finally( () => setCaricamento( false ) );
	};

	const alternaSelezione = ( id, selezionato ) => {
		setSelezionati( ( precedenti ) => {
			const nuovi = new Set( precedenti );
			if ( selezionato ) {
				nuovi.add( id );
			} else {
				nuovi.delete( id );
			}
			return nuovi;
		} );
	};

	const applica = () => {
		const daApplicare = anteprima.aggiornamenti.filter( ( riga ) => selezionati.has( riga.id ) );
		if ( 0 === daApplicare.length ) {
			setErrore( 'Seleziona almeno un prodotto da scontare.' );
			return;
		}

		setApplicazione( true );
		setErrore( null );

		apiFetch( {
			path: '/push-contempt/v1/prezzi/applica',
			method: 'POST',
			data: { aggiornamenti: daApplicare },
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
				<SelectControl
					label="Categoria"
					value={ categoria }
					options={ [
						{ label: null === categorie ? 'Caricamento…' : 'Seleziona una categoria', value: '' },
						...( categorie || [] ).map( ( cat ) => ( {
							label: `${ cat.name } (${ cat.count })`,
							value: cat.slug,
						} ) ),
					] }
					onChange={ setCategoria }
				/>
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
							<div style={ { margin: '16px 0', display: 'flex', gap: '12px' } }>
								<Button
									variant="link"
									onClick={ () => setSelezionati( new Set( anteprima.aggiornamenti.map( ( r ) => r.id ) ) ) }
								>
									Seleziona tutti
								</Button>
								<Button variant="link" onClick={ () => setSelezionati( new Set() ) }>
									Deseleziona tutti
								</Button>
							</div>

							<table className="wp-list-table widefat fixed striped" style={ { marginBottom: '16px' } }>
								<thead>
									<tr>
										<th style={ { width: '40px' } }></th>
										<th>Prodotto</th>
										<th>Prezzo attuale</th>
										<th>Prezzo nuovo</th>
									</tr>
								</thead>
								<tbody>
									{ anteprima.aggiornamenti.map( ( riga ) => (
										<tr key={ riga.id }>
											<td>
												<CheckboxControl
													__nextHasNoMarginBottom
													checked={ selezionati.has( riga.id ) }
													onChange={ ( selezionato ) => alternaSelezione( riga.id, selezionato ) }
												/>
											</td>
											<td>{ riga.titolo }</td>
											<td>{ riga.prezzo_regolare_attuale }</td>
											<td>{ riga.prezzo_regolare_nuovo }</td>
										</tr>
									) ) }
								</tbody>
							</table>

							<Button variant="primary" onClick={ applica } isBusy={ applicazione }>
								Applica a { selezionati.size } prodotti selezionati
							</Button>
						</>
					) }
				</>
			) }
		</div>
	);
}
