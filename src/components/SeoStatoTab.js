import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import LinkProdotto from './LinkProdotto';

/**
 * Vista d'insieme SEO: chiama direttamente la REST API del plugin, nessuna
 * AI. La copywriting SEO per singolo prodotto (lettura/scrittura assistita)
 * resta nella sezione che richiede il ponte agentico - qui solo l'overview.
 */
export default function SeoStatoTab() {
	const [ prodotti, setProdotti ] = useState( null );
	const [ caricamento, setCaricamento ] = useState( false );
	const [ errore, setErrore ] = useState( null );

	const carica = () => {
		setCaricamento( true );
		setErrore( null );
		apiFetch( { path: '/push-contempt/v1/seo/stato' } )
			.then( setProdotti )
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
				Per ogni prodotto pubblicato mostra se titolo SEO, meta description e parola chiave focus (impostati da
				Yoast) sono presenti e quanto sono lunghi - utile per capire da dove iniziare un'ottimizzazione. Clicca su
				un prodotto per aprirne la scheda di modifica e sistemare i campi SEO.
			</p>
			<Notice status="info" isDismissible={ false }>
				La scrittura SEO assistita per singolo prodotto richiede una sessione AI collegata - in arrivo.
			</Notice>

			<div style={ { margin: '16px 0' } }>
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

			{ ! caricamento && prodotti && (
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Prodotto</th>
							<th>Titolo SEO</th>
							<th>Meta description</th>
							<th>Parola chiave focus</th>
						</tr>
					</thead>
					<tbody>
						{ prodotti.map( ( prodotto ) => (
							<tr key={ prodotto.id }>
								<td>
									<LinkProdotto id={ prodotto.id }>{ prodotto.titolo }</LinkProdotto>
								</td>
								<td>
									{ prodotto.titolo_seo ? `${ prodotto.lunghezza_titolo_seo } caratteri` : (
										<span style={ { color: '#b32d2e' } }>Mancante</span>
									) }
								</td>
								<td>
									{ prodotto.meta_description ? `${ prodotto.lunghezza_meta_description } caratteri` : (
										<span style={ { color: '#b32d2e' } }>Mancante</span>
									) }
								</td>
								<td>{ prodotto.parola_chiave_focus || '—' }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
