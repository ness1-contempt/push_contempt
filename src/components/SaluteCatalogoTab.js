import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import LinkProdotto from './LinkProdotto';
import useRestFetch from '../hooks/useRestFetch';

/**
 * Salute catalogo: chiama direttamente la REST API del plugin, nessuna AI.
 */
export default function SaluteCatalogoTab() {
	const [ sogliaDescrizione, setSogliaDescrizione ] = useState( '50' );
	const { dati: prodotti, caricamento, errore, carica } = useRestFetch(
		() =>
			`/push-contempt/v1/salute-catalogo?soglia_descrizione=${ encodeURIComponent(
				sogliaDescrizione
			) }`
	);

	return (
		<div style={ { marginTop: '16px' } }>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Elenca i prodotti pubblicati con almeno un problema: nessuna immagine, nessuna categoria, prezzo mancante o
				descrizione troppo corta rispetto alla soglia indicata. Clicca su un prodotto per aprirne la scheda di
				modifica e sistemarlo.
			</p>
			<div style={ { display: 'flex', gap: '8px', alignItems: 'flex-end', marginBottom: '16px' } }>
				<TextControl
					label="Soglia minima descrizione (caratteri)"
					type="number"
					value={ sogliaDescrizione }
					onChange={ setSogliaDescrizione }
				/>
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

			{ ! caricamento && prodotti && 0 === prodotti.length && <p>Nessun problema trovato nel catalogo.</p> }

			{ ! caricamento && prodotti && prodotti.length > 0 && (
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Prodotto</th>
							<th>Problemi</th>
						</tr>
					</thead>
					<tbody>
						{ prodotti.map( ( prodotto ) => (
							<tr key={ prodotto.id }>
								<td>
									<LinkProdotto id={ prodotto.id }>{ prodotto.titolo }</LinkProdotto>
								</td>
								<td>{ prodotto.problemi.join( ', ' ) }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
