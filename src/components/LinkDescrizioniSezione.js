import apiFetch from '@wordpress/api-fetch';
import { Button, CheckboxControl, Notice, SelectControl, Spinner } from '@wordpress/components';
import { Fragment, useState } from '@wordpress/element';
import LinkProdotto from './LinkProdotto';
import MessaggioProntoAI from './MessaggioProntoAI';
import useRestFetch from '../hooks/useRestFetch';
import { esitoLink, messaggioLink } from '../lib/messaggioLink';

const OPZIONI_STATO = [
	{ label: 'Pubblicati', value: 'publish' },
	{ label: 'Bozze', value: 'draft' },
	{ label: 'Tutti', value: 'any' },
];

/**
 * Elenco degli indirizzi di un prodotto (interni, in uscita, altri), caricato
 * solo quando l'utente lo chiede.
 *
 * @param {Object} props
 * @param {Object} props.dettaglio Stato del caricamento ({ caricamento, errore, dati }).
 */
function DettaglioLink( { dettaglio } ) {
	if ( dettaglio.caricamento ) {
		return <Spinner />;
	}
	if ( dettaglio.errore ) {
		return <p style={ { color: '#d63638' } }>{ dettaglio.errore }</p>;
	}
	const { dati } = dettaglio;
	const gruppi = [
		[ 'In uscita', dati.esterni ],
		[ 'Interni', dati.interni ],
		[ 'Altri (ancore, email, telefono)', dati.altri ],
	];
	return (
		<div>
			{ gruppi.map( ( [ nome, link ] ) => (
				<div key={ nome } style={ { marginBottom: '8px' } }>
					<strong>{ nome }</strong>
					{ 0 === link.length && <span style={ { color: '#50575e' } }> — nessuno</span> }
					<ul style={ { margin: '4px 0 0 18px', listStyle: 'disc' } }>
						{ link.map( ( voce, indice ) => (
							<li key={ indice }>
								{ voce.testo || '(senza testo)' } —{ ' ' }
								<code style={ { wordBreak: 'break-all' } }>{ voce.url }</code>
								{ voce.nofollow && ' (nofollow)' }
							</li>
						) ) }
					</ul>
				</div>
			) ) }
		</div>
	);
}

/**
 * Controllo dei link nelle descrizioni: quali prodotti non hanno link in
 * uscita (la verifica che Yoast segnala piu' spesso come mancante). Legge dalla
 * REST API del plugin, nessuna AI; aggiungere un link e' invece un lavoro per
 * l'assistente AI del cliente, di cui qui si prepara il messaggio.
 */
export default function LinkDescrizioniSezione() {
	const [ stato, setStato ] = useState( 'publish' );
	const [ soloSenzaUscita, setSoloSenzaUscita ] = useState( true );
	const [ dettagli, setDettagli ] = useState( {} );
	const [ idMessaggio, setIdMessaggio ] = useState( null );

	// La query si ricalcola a ogni carica(): i filtri cambiano l'elenco solo con "Controlla".
	const { dati, caricamento, errore, carica } = useRestFetch(
		() => `/push-contempt/v1/link/prodotti?stato=${ stato }&solo_senza_link_in_uscita=${ soloSenzaUscita }`
	);

	const mostraDettaglio = ( id ) => {
		if ( dettagli[ id ] ) {
			// Secondo clic: chiude.
			setDettagli( ( precedenti ) => {
				const { [ id ]: _, ...resto } = precedenti;
				return resto;
			} );
			return;
		}
		setDettagli( ( precedenti ) => ( { ...precedenti, [ id ]: { caricamento: true } } ) );
		apiFetch( { path: `/push-contempt/v1/link/prodotto?id=${ id }` } )
			.then( ( risposta ) =>
				setDettagli( ( precedenti ) => ( { ...precedenti, [ id ]: { dati: risposta } } ) )
			)
			.catch( ( err ) =>
				setDettagli( ( precedenti ) => ( {
					...precedenti,
					[ id ]: { errore: err.message || 'Errore nel caricamento.' },
				} ) )
			);
	};

	const prodottoMessaggio = dati?.prodotti.find( ( riga ) => riga.id === idMessaggio );

	return (
		<div style={ { marginTop: '16px' } }>
			<h3>Link nelle descrizioni</h3>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Controlla la descrizione di ogni prodotto e dice quanti link ha verso altre pagine del tuo sito
				(interni) e verso altri siti (in uscita). Yoast segnala come problema la mancanza di link in uscita:
				qui vedi subito quali prodotti li mancano. Clicca "Vedi link" per gli indirizzi di un prodotto;
				"Prepara messaggio" ti dà il testo da dare al tuo assistente AI per aggiungerne uno.
			</p>
			<div style={ { display: 'flex', gap: '16px', alignItems: 'flex-end', marginBottom: '16px', flexWrap: 'wrap' } }>
				<SelectControl label="Prodotti" value={ stato } options={ OPZIONI_STATO } onChange={ setStato } />
				<CheckboxControl
					label="Solo quelli senza link in uscita"
					checked={ soloSenzaUscita }
					onChange={ setSoloSenzaUscita }
				/>
				<Button variant="primary" onClick={ carica } isBusy={ caricamento }>
					Controlla
				</Button>
			</div>

			{ errore && (
				<Notice status="error" isDismissible={ false }>
					{ errore }
				</Notice>
			) }

			{ caricamento && <Spinner /> }

			{ ! caricamento && dati && (
				<p style={ { marginBottom: '12px' } }>
					Analizzati <strong>{ dati.analizzati }</strong> prodotti: <strong>{ dati.senza_link_in_uscita }</strong>{ ' ' }
					senza link in uscita, <strong>{ dati.senza_link_interni }</strong> senza link interni.
				</p>
			) }

			{ ! caricamento && dati && 0 === dati.prodotti.length && <p>Nessun prodotto da mostrare.</p> }

			{ ! caricamento && dati && dati.prodotti.length > 0 && (
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style={ { width: '32%' } }>Prodotto</th>
							<th style={ { width: '9%' } }>Interni</th>
							<th style={ { width: '10%' } }>In uscita</th>
							<th style={ { width: '21%' } }>Esito</th>
							<th>Azioni</th>
						</tr>
					</thead>
					<tbody>
						{ dati.prodotti.map( ( riga ) => (
							<Fragment key={ riga.id }>
								<tr>
									<td>
										<LinkProdotto id={ riga.id }>{ riga.titolo }</LinkProdotto>
										{ 'publish' !== riga.stato && ' (bozza)' }
									</td>
									<td>{ riga.totale_interni }</td>
									<td>{ riga.totale_esterni }</td>
									<td>{ esitoLink( riga ) }</td>
									<td style={ { whiteSpace: 'nowrap' } }>
										<Button variant="link" onClick={ () => mostraDettaglio( riga.id ) }>
											{ dettagli[ riga.id ] ? 'Nascondi' : 'Vedi link' }
										</Button>
										{ ! riga.ha_link_in_uscita && (
											<>
												{ ' · ' }
												<Button variant="link" onClick={ () => setIdMessaggio( riga.id ) }>
													Prepara messaggio
												</Button>
											</>
										) }
									</td>
								</tr>
								{ dettagli[ riga.id ] && (
									<tr>
										<td colSpan={ 5 }>
											<DettaglioLink dettaglio={ dettagli[ riga.id ] } />
										</td>
									</tr>
								) }
							</Fragment>
						) ) }
					</tbody>
				</table>
			) }

			{ prodottoMessaggio && (
				<div style={ { marginTop: '24px' } }>
					<MessaggioProntoAI testo={ messaggioLink( prodottoMessaggio ) } />
				</div>
			) }
		</div>
	);
}
