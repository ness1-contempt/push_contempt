import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import LinkProdotto from './LinkProdotto';

/**
 * Alert scorte: chiama direttamente la REST API del plugin, nessuna AI.
 */
export default function ScorteTab() {
	const [ soglia, setSoglia ] = useState( '5' );
	const [ prodotti, setProdotti ] = useState( null );
	const [ caricamento, setCaricamento ] = useState( false );
	const [ errore, setErrore ] = useState( null );

	const carica = () => {
		setCaricamento( true );
		setErrore( null );
		apiFetch( { path: `/push-contempt/v1/scorte?soglia=${ encodeURIComponent( soglia ) }` } )
			.then( setProdotti )
			.catch( ( err ) => setErrore( err.message || 'Errore nel caricamento.' ) )
			.finally( () => setCaricamento( false ) );
	};

	// Solo al primo caricamento della tab: dopo, è l'utente a chiedere
	// l'aggiornamento premendo il bottone, non ogni cifra digitata nel campo.
	useEffect( () => {
		carica();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return (
		<div style={ { marginTop: '16px' } }>
			<div style={ { display: 'flex', gap: '8px', alignItems: 'flex-end', marginBottom: '16px' } }>
				<TextControl label="Soglia scorte" type="number" value={ soglia } onChange={ setSoglia } />
				<Button variant="primary" onClick={ carica } isBusy={ caricamento }>
					Aggiorna
				</Button>
			</div>

			{ errore && (
				<Notice status="error" isDismissible={ false }>
					{ errore }
				</Notice>
			) }

			{ caricamento && <Spinner /> }

			{ ! caricamento && prodotti && 0 === prodotti.length && <p>Nessun prodotto sotto scorta.</p> }

			{ ! caricamento && prodotti && prodotti.length > 0 && (
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Prodotto</th>
							<th>SKU</th>
							<th>Scorte</th>
							<th>Stato</th>
						</tr>
					</thead>
					<tbody>
						{ prodotti.map( ( prodotto ) => (
							<tr key={ prodotto.id }>
								<td>
									<LinkProdotto id={ prodotto.id }>{ prodotto.titolo }</LinkProdotto>
								</td>
								<td>{ prodotto.sku || '—' }</td>
								<td>{ null === prodotto.scorte ? '—' : prodotto.scorte }</td>
								<td>{ prodotto.esaurito ? 'Esaurito' : 'Sotto soglia' }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
