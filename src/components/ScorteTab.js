import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import LinkProdotto from './LinkProdotto';
import useRestFetch from '../hooks/useRestFetch';

/**
 * Alert scorte: chiama direttamente la REST API del plugin, nessuna AI.
 */
export default function ScorteTab() {
	const [ soglia, setSoglia ] = useState( '5' );
	// Il path si ricalcola a ogni carica(): la soglia aggiorna la query solo
	// quando l'utente preme "Aggiorna", non a ogni cifra digitata nel campo.
	const { dati: prodotti, caricamento, errore, carica } = useRestFetch(
		() => `/push-contempt/v1/scorte?soglia=${ encodeURIComponent( soglia ) }`
	);

	return (
		<div style={ { marginTop: '16px' } }>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Elenca i prodotti pubblicati esauriti o con scorte residue pari o sotto la soglia indicata (i prodotti
				senza gestione scorte attiva compaiono solo se risultano esauriti). Cambia la soglia e premi "Aggiorna"
				per rivedere l'elenco; clicca su un prodotto per aprirne la scheda di modifica.
			</p>
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
