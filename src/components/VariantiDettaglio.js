import { Notice, Spinner } from '@wordpress/components';
import useRestFetch from '../hooks/useRestFetch';
import { COLORE_GRAVITA } from '../lib/saluteVarianti';

/**
 * Dettaglio SEO delle varianti di un prodotto variabile, letto su richiesta da
 * /seo/varianti: i problemi (con le varianti coinvolte) e la tabella delle
 * varianti con prezzo, SKU, immagine e descrizione. Sola lettura: i testi si
 * scrivono con l'assistente AI (leggi_seo_varianti / aggiorna_seo_varianti).
 *
 * @param {Object} props
 * @param {number} props.id Id del prodotto variabile.
 */
export default function VariantiDettaglio( { id } ) {
	const { dati, caricamento, errore } = useRestFetch(
		() => `/push-contempt/v1/seo/varianti?id=${ id }`
	);

	if ( caricamento || ( ! dati && ! errore ) ) {
		return <Spinner />;
	}
	if ( errore ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ errore }
			</Notice>
		);
	}

	const etichette = Object.fromEntries(
		dati.varianti.map( ( v ) => [ v.id, v.etichetta ] )
	);

	return (
		<div style={ { padding: '8px 0' } }>
			{ 0 === dati.problemi.length ? (
				<p style={ { color: '#00a32a', margin: 0 } }>
					Nessun problema trovato sulle varianti.
				</p>
			) : (
				<ul style={ { margin: '0 0 12px 18px', listStyle: 'disc' } }>
					{ dati.problemi.map( ( problema ) => (
						<li key={ problema.codice }>
							<strong
								style={ {
									color: COLORE_GRAVITA[ problema.gravita ],
								} }
							>
								{ problema.gravita }
							</strong>
							{ ': ' }
							{ problema.messaggio }
							{ problema.varianti.length > 0 && (
								<span style={ { color: '#50575e' } }>
									{ ' — ' }
									{ problema.varianti
										.map(
											( vid ) => etichette[ vid ] ?? vid
										)
										.join( '; ' ) }
								</span>
							) }
						</li>
					) ) }
				</ul>
			) }

			<table
				className="wp-list-table widefat fixed striped"
				style={ { maxWidth: '900px' } }
			>
				<thead>
					<tr>
						<th>Variante</th>
						<th style={ { width: '90px' } }>Prezzo</th>
						<th style={ { width: '130px' } }>SKU</th>
						<th style={ { width: '120px' } }>Immagine propria</th>
						<th>Descrizione</th>
					</tr>
				</thead>
				<tbody>
					{ dati.varianti.map( ( variante ) => (
						<tr key={ variante.id }>
							<td>
								{ variante.etichetta }
								{ 'publish' !== variante.stato && (
									<span style={ { color: '#50575e' } }>
										{ ' ' }
										({ variante.stato })
									</span>
								) }
							</td>
							<td>
								{ variante.prezzo || (
									<span style={ { color: '#b32d2e' } }>
										Mancante
									</span>
								) }
							</td>
							<td>{ variante.sku || '—' }</td>
							<td>{ variante.immagine_propria ? 'Sì' : 'No' }</td>
							<td>{ variante.descrizione || '—' }</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
