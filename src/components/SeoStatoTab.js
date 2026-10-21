import { Button, Notice, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import LinkProdotto from './LinkProdotto';
import MessaggioProntoAI from './MessaggioProntoAI';
import StatoCollegamentoAI from './StatoCollegamentoAI';
import useRestFetch from '../hooks/useRestFetch';

/**
 * Messaggio pronto da incollare nella chat dell'assistente AI del cliente
 * per un prodotto specifico. A differenza di Carica prodotti qui non serve
 * alcun percorso locale: tutto il contesto (titolo, SEO attuale) e' gia' sul
 * sito, quindi arriva via REST - il messaggio si limita a identificare il
 * prodotto e cosa manca.
 *
 * @param {Object} prodotto Riga della tabella (id, titolo, parola_chiave_focus, ...).
 */
function messaggioPronto( prodotto ) {
	return (
		`Scrivi un titolo SEO e una meta description ottimizzati per il prodotto "${ prodotto.titolo }" ` +
		`(ID ${ prodotto.id }) su WooCommerce - parola chiave focus attuale: ` +
		`${ prodotto.parola_chiave_focus || 'nessuna' } - poi aggiornali con push_contempt_mcp.`
	);
}

/**
 * Vista d'insieme SEO: la tabella chiama direttamente la REST API del
 * plugin, nessuna AI. La scrittura SEO vera e propria serve l'assistente AI
 * del cliente (stesso trattamento di Carica prodotti: nessun bottone-azione,
 * solo stato del collegamento e messaggio pronto - qui per singolo prodotto).
 */
export default function SeoStatoTab( { onNavigate } ) {
	const { dati: prodotti, caricamento, errore, carica } = useRestFetch(
		() => '/push-contempt/v1/seo/stato'
	);
	const [ prodottoSelezionato, setProdottoSelezionato ] = useState( null );

	return (
		<div style={ { marginTop: '16px' } }>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Per ogni prodotto pubblicato mostra se titolo SEO, meta description e parola chiave focus (impostati da
				Yoast) sono presenti e quanto sono lunghi - utile per capire da dove iniziare un'ottimizzazione. Clicca su
				un prodotto per aprirne la scheda di modifica e sistemare i campi SEO.
			</p>
			<StatoCollegamentoAI onNavigate={ onNavigate } />

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
							<th>Scrivi con AI</th>
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
								<td>
									<Button variant="link" onClick={ () => setProdottoSelezionato( prodotto ) }>
										Prepara messaggio
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			{ prodottoSelezionato && (
				<div style={ { marginTop: '16px' } }>
					<MessaggioProntoAI
						testo={ messaggioPronto( prodottoSelezionato ) }
						etichetta={ `Messaggio pronto per "${ prodottoSelezionato.titolo }":` }
					/>
				</div>
			) }
		</div>
	);
}
