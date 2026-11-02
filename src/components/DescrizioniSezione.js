import { Button, Notice, SelectControl, Spinner } from '@wordpress/components';
import { Fragment, useState } from '@wordpress/element';
import BarraAzioniVersioni from './BarraAzioniVersioni';
import CellaTipoPrezzo from './CellaTipoPrezzo';
import LinkProdotto from './LinkProdotto';
import MessaggioProntoAI from './MessaggioProntoAI';
import VersioniTesti from './VersioniTesti';
import useAzioniVersioni from '../hooks/useAzioniVersioni';
import useRestFetch from '../hooks/useRestFetch';
import { SOGLIA_DESCRIZIONE_DEFAULT } from '../constants';
import { anteprimaHtml, messaggioDescrizione } from '../lib/messaggiContenuti';

const CAMPI_DESCRIZIONE = [
	{ chiave: 'descrizione', etichetta: 'Descrizione' },
	{ chiave: 'descrizione_breve', etichetta: 'Descrizione breve' },
];

const FILTRI = {
	tutti: () => true,
	con_versioni: ( prodotto ) => !! prodotto.versioni,
	da_scrivere: ( prodotto ) => prodotto.lunghezza_descrizione < SOGLIA_DESCRIZIONE_DEFAULT,
};

const OPZIONI_FILTRO = [
	{ label: 'Tutti', value: 'tutti' },
	{ label: 'Con versioni salvate (base e precedente)', value: 'con_versioni' },
	{ label: `Descrizione assente o sotto i ${ SOGLIA_DESCRIZIONE_DEFAULT } caratteri`, value: 'da_scrivere' },
];

const AVVISO_SCAMBIO = 'La versione attuale diventerà il «precedente»: potrai sempre tornarci.';

/**
 * Le azioni sulle versioni delle descrizioni: stesso schema di quelle SEO, su
 * altre rotte e con testi che parlano di descrizioni.
 */
const AZIONI = {
	base: {
		path: '/descrizione/ripristina',
		dati: { versione: 'base' },
		esitoOk: 'ripristinato',
		verbo: 'Riportate alla base',
		conferma: ( quanti ) => `Rimettere le descrizioni ${ quanti } come nella base? ${ AVVISO_SCAMBIO }`,
	},
	precedente: {
		path: '/descrizione/ripristina',
		dati: { versione: 'precedente' },
		esitoOk: 'ripristinato',
		verbo: 'Riportate al precedente',
		conferma: ( quanti ) => `Rimettere le descrizioni ${ quanti } come nel precedente? ${ AVVISO_SCAMBIO }`,
	},
	nuova_base: {
		path: '/descrizione/base',
		dati: {},
		esitoOk: 'impostata',
		verbo: 'Nuova base impostata',
		conferma: ( quanti ) =>
			`Usare le descrizioni attuali ${ quanti } come nuova base? La base smette di essere il testo ` +
			'originale prima dei nostri interventi e quel testo non sarà più recuperabile.',
	},
};

/**
 * Descrizioni dei prodotti: quanto sono lunghe, e le versioni salvate (base,
 * precedente, attuale) con il ripristino. Legge e ripristina dalla REST API del
 * plugin, nessuna AI; scrivere una descrizione nuova e' invece un lavoro per
 * l'assistente AI del cliente, di cui qui si prepara il messaggio.
 */
export default function DescrizioniSezione() {
	const { dati: prodotti, caricamento, errore, carica } = useRestFetch( () => '/push-contempt/v1/descrizione/stato' );
	const [ filtro, setFiltro ] = useState( 'tutti' );
	const [ selezionati, setSelezionati ] = useState( new Set() );
	const [ aperto, setAperto ] = useState( null );
	const [ prodottoMessaggio, setProdottoMessaggio ] = useState( null );
	const { inCorso, esito, rimuoviEsito, esegui } = useAzioniVersioni( AZIONI, () => {
		setSelezionati( new Set() );
		carica();
	} );

	const visibili = prodotti ? prodotti.filter( FILTRI[ filtro ] ) : null;
	const scelti = prodotti ? prodotti.filter( ( p ) => selezionati.has( p.id ) ) : [];
	const sceltiConVersioni = scelti.filter( ( p ) => p.versioni );

	const attivaSelezione = ( id, attiva ) => {
		setSelezionati( ( precedenti ) => {
			const nuovi = new Set( precedenti );
			if ( attiva ) {
				nuovi.add( id );
			} else {
				nuovi.delete( id );
			}
			return nuovi;
		} );
	};

	return (
		<div style={ { marginTop: '16px' } }>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Per ogni prodotto mostra quanto sono lunghe la descrizione e la descrizione breve. Quando l'assistente AI
				riscrive una descrizione, il plugin conserva la <strong>base</strong> (il testo prima del primo
				intervento) e il <strong>precedente</strong> (quello appena sostituito): da qui puoi rimetterli, anche su
				più prodotti insieme, e potrai sempre tornare indietro. "Prepara messaggio" ti dà il testo da dare
				all'assistente per riscriverne una.
			</p>

			<div style={ { margin: '16px 0', display: 'flex', gap: '16px', alignItems: 'flex-end', flexWrap: 'wrap' } }>
				<Button variant="primary" onClick={ carica } isBusy={ caricamento }>
					Aggiorna
				</Button>
				{ visibili && visibili.length > 0 && (
					<Button variant="tertiary" onClick={ () => setSelezionati( new Set( visibili.map( ( p ) => p.id ) ) ) }>
						Seleziona tutti i visibili
					</Button>
				) }
				{ visibili && visibili.some( ( p ) => p.versioni ) && (
					<Button
						variant="tertiary"
						onClick={ () =>
							setSelezionati( new Set( visibili.filter( ( p ) => p.versioni ).map( ( p ) => p.id ) ) )
						}
					>
						Seleziona tutti con versioni salvate
					</Button>
				) }
				<SelectControl label="Mostra" value={ filtro } options={ OPZIONI_FILTRO } onChange={ setFiltro } />
			</div>

			{ errore && (
				<Notice status="error" isDismissible={ false }>
					{ errore }
				</Notice>
			) }

			<BarraAzioniVersioni
				selezionati={ selezionati.size }
				conVersioni={ sceltiConVersioni.length }
				inCorso={ inCorso }
				onAzione={ ( azione ) => esegui( azione, sceltiConVersioni.map( ( p ) => p.id ) ) }
				onDeseleziona={ () => setSelezionati( new Set() ) }
			/>

			{ esito && (
				<Notice status={ esito.status } onRemove={ rimuoviEsito }>
					{ esito.testo }
				</Notice>
			) }

			{ caricamento && <Spinner /> }

			{ ! caricamento && visibili && visibili.length > 0 && (
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style={ { width: '34px' } }>
								<span className="screen-reader-text">Seleziona</span>
							</th>
							<th style={ { width: '26%' } }>Prodotto</th>
							<th style={ { width: '14%' } }>Tipo e prezzo</th>
							<th>Stato</th>
							<th>Descrizione</th>
							<th>Descrizione breve</th>
							<th>Scrivi con AI</th>
							<th>Versioni</th>
						</tr>
					</thead>
					<tbody>
						{ visibili.map( ( prodotto ) => (
							<Fragment key={ prodotto.id }>
								<tr>
									<td>
										<input
											type="checkbox"
											aria-label={ `Seleziona ${ prodotto.titolo }` }
											checked={ selezionati.has( prodotto.id ) }
											onChange={ ( evento ) => attivaSelezione( prodotto.id, evento.target.checked ) }
										/>
									</td>
									<td>
										<LinkProdotto id={ prodotto.id }>{ prodotto.titolo }</LinkProdotto>
									</td>
									<td>
										<CellaTipoPrezzo prodotto={ prodotto } />
									</td>
									<td>{ 'draft' === prodotto.stato ? 'Bozza' : 'Pubblicato' }</td>
									<td>
										{ prodotto.lunghezza_descrizione > 0 ? (
											`${ prodotto.lunghezza_descrizione } caratteri`
										) : (
											<span style={ { color: '#b32d2e' } }>Mancante</span>
										) }
									</td>
									<td>
										{ prodotto.lunghezza_descrizione_breve > 0
											? `${ prodotto.lunghezza_descrizione_breve } caratteri`
											: '—' }
									</td>
									<td>
										<Button variant="link" onClick={ () => setProdottoMessaggio( prodotto ) }>
											Prepara messaggio
										</Button>
									</td>
									<td>
										{ prodotto.versioni ? (
											<Button
												variant="link"
												onClick={ () => setAperto( aperto === prodotto.id ? null : prodotto.id ) }
											>
												{ aperto === prodotto.id ? 'Nascondi versioni' : 'Mostra versioni' }
											</Button>
										) : (
											'—'
										) }
									</td>
								</tr>
								{ prodotto.versioni && aperto === prodotto.id && (
									<tr>
										<td colSpan={ 8 }>
											<VersioniTesti
												versioni={ prodotto.versioni }
												occupato={ inCorso }
												campi={ CAMPI_DESCRIZIONE }
												fonte="WooCommerce"
												formatta={ anteprimaHtml }
												onAzione={ ( azione ) => esegui( azione, [ prodotto.id ] ) }
											/>
										</td>
									</tr>
								) }
							</Fragment>
						) ) }
					</tbody>
				</table>
			) }

			{ ! caricamento && visibili && 0 === visibili.length && (
				<p style={ { color: '#50575e' } }>Nessun prodotto in questa categoria.</p>
			) }

			{ prodottoMessaggio && (
				<div style={ { marginTop: '16px' } }>
					<MessaggioProntoAI
						testo={ messaggioDescrizione( prodottoMessaggio ) }
						etichetta={ `Messaggio pronto per "${ prodottoMessaggio.titolo }":` }
					/>
				</div>
			) }
		</div>
	);
}
