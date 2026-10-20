import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, SelectControl, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import LinkProdotto from './LinkProdotto';

const PERIODI = [
	{ label: 'Ultimo mese', value: '1_mese' },
	{ label: 'Ultimi 3 mesi', value: '3_mesi' },
	{ label: 'Ultimi 6 mesi', value: '6_mesi' },
	{ label: 'Ultimo anno', value: '1_anno' },
];

/**
 * Report vendite: chiama direttamente la REST API del plugin, nessuna AI.
 */
export default function VenditeTab() {
	const [ periodo, setPeriodo ] = useState( '1_mese' );
	const [ dati, setDati ] = useState( null );
	const [ caricamento, setCaricamento ] = useState( false );
	const [ errore, setErrore ] = useState( null );

	const carica = () => {
		setCaricamento( true );
		setErrore( null );
		apiFetch( { path: `/push-contempt/v1/vendite?periodo=${ encodeURIComponent( periodo ) }` } )
			.then( setDati )
			.catch( ( err ) => setErrore( err.message || 'Errore nel caricamento.' ) )
			.finally( () => setCaricamento( false ) );
	};

	useEffect( () => {
		carica();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return (
		<div style={ { marginTop: '16px' } }>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Mostra, per il periodo scelto, i prodotti più venduti (quantità e ricavo netto sugli ordini completati o in
				elaborazione) e quelli mai venduti - utile per capire cosa spingere e cosa magari togliere dal catalogo.
				Clicca su un prodotto per aprirne la scheda di modifica.
			</p>
			<div style={ { display: 'flex', gap: '8px', alignItems: 'flex-end', marginBottom: '16px' } }>
				<SelectControl label="Periodo" value={ periodo } options={ PERIODI } onChange={ setPeriodo } />
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

			{ ! caricamento && dati && (
				<>
					<p>
						Dal { dati.dal } · Ricavo netto nel periodo: <strong>{ dati.ricavo_totale_periodo }</strong>
					</p>

					<h3>Più venduti</h3>
					{ 0 === dati.piu_venduti.length && <p>Nessuna vendita nel periodo.</p> }
					{ dati.piu_venduti.length > 0 && (
						<table className="wp-list-table widefat fixed striped" style={ { marginBottom: '24px' } }>
							<thead>
								<tr>
									<th>Prodotto</th>
									<th>Quantità venduta</th>
									<th>Ricavo netto</th>
								</tr>
							</thead>
							<tbody>
								{ dati.piu_venduti.map( ( riga ) => (
									<tr key={ riga.id }>
										<td>
											<LinkProdotto id={ riga.id }>{ riga.titolo }</LinkProdotto>
										</td>
										<td>{ riga.quantita_venduta }</td>
										<td>{ riga.ricavo_netto }</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }

					<h3>Mai venduti</h3>
					{ 0 === dati.mai_venduti.length && <p>Tutti i prodotti pubblicati hanno almeno una vendita nel periodo.</p> }
					{ dati.mai_venduti.length > 0 && (
						<ul>
							{ dati.mai_venduti.map( ( prodotto ) => (
							<li key={ prodotto.id }>
								<LinkProdotto id={ prodotto.id }>{ prodotto.titolo }</LinkProdotto>
							</li>
						) ) }
						</ul>
					) }
				</>
			) }
		</div>
	);
}
