import apiFetch from '@wordpress/api-fetch';
import { Button, CheckboxControl, Notice, SelectControl, Spinner } from '@wordpress/components';
import { Fragment, useState } from '@wordpress/element';
import ConcorrentiSezione from './ConcorrentiSezione';
import LinkProdotto from './LinkProdotto';
import MessaggioProntoAI from './MessaggioProntoAI';
import StatoCollegamentoAI from './StatoCollegamentoAI';
import VersioniSeo from './VersioniSeo';
import useRestFetch from '../hooks/useRestFetch';

const TRE_ORE_IN_MS = 3 * 60 * 60 * 1000;

/**
 * Da quanto tempo esiste il prodotto, in ms - Infinity se la data non c'e'
 * (non dovrebbe succedere, ma evita che un prodotto senza data finisca per
 * sbaglio nel filtro "appena inseriti").
 *
 * @param {Object} prodotto Riga della tabella (serve `data_creazione`).
 */
function etaInMs( prodotto ) {
	return prodotto.data_creazione
		? Date.now() - new Date( prodotto.data_creazione ).getTime()
		: Infinity;
}

/**
 * "Appena inseriti" e "Catalogo consolidato" sono mutuamente esclusivi (le
 * 3 ore decidono in quale dei due finisce un prodotto pubblicato). "In
 * bozza" invece guarda solo lo stato, non l'eta': una bozza appena creata
 * compare sia qui sia in "Appena inseriti" (serve in entrambe le liste di
 * lavoro), una bozza vecchia di settimane compare solo qui.
 */
const FILTRI = {
	tutti: () => true,
	appena_inseriti: ( prodotto ) => etaInMs( prodotto ) <= TRE_ORE_IN_MS,
	bozza: ( prodotto ) => 'draft' === prodotto.stato,
	consolidato: ( prodotto ) => 'publish' === prodotto.stato && etaInMs( prodotto ) > TRE_ORE_IN_MS,
	con_versioni: ( prodotto ) => !! prodotto.versioni,
};

const OPZIONI_FILTRO = [
	{ label: 'Tutti', value: 'tutti' },
	{ label: 'Appena inseriti (ultime 3 ore)', value: 'appena_inseriti' },
	{ label: 'In bozza', value: 'bozza' },
	{ label: 'Catalogo consolidato (da più di 3 ore)', value: 'consolidato' },
	{ label: 'Con versioni salvate (base e precedente)', value: 'con_versioni' },
];

const AVVISO_SCAMBIO = 'La versione attuale diventerà il «precedente»: potrai sempre tornarci.';

/**
 * Le azioni sulle versioni: rotta REST, esito che conta come riuscito, e il testo
 * della conferma (che dice sempre cosa succede a quello che si lascia).
 */
const AZIONI = {
	base: {
		path: '/seo/ripristina',
		dati: { versione: 'base' },
		esitoOk: 'ripristinato',
		verbo: 'Riportati alla base',
		conferma: ( quanti ) => `Rimettere i campi SEO ${ quanti } come nella base? ${ AVVISO_SCAMBIO }`,
	},
	precedente: {
		path: '/seo/ripristina',
		dati: { versione: 'precedente' },
		esitoOk: 'ripristinato',
		verbo: 'Riportati al precedente',
		conferma: ( quanti ) => `Rimettere i campi SEO ${ quanti } come nel precedente? ${ AVVISO_SCAMBIO }`,
	},
	nuova_base: {
		path: '/seo/base',
		dati: {},
		esitoOk: 'impostata',
		verbo: 'Nuova base impostata',
		conferma: ( quanti ) =>
			`Usare i valori attuali ${ quanti } come nuova base? La base smette di essere lo stato originale ` +
			'prima dei nostri interventi e quello stato non sarà più recuperabile.',
	},
};

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
 * plugin, nessuna AI. Il filtro (vedi FILTRI per i dettagli di sovrapposizione
 * tra i casi) e' applicato lato client sull'intero elenco gia' scaricato,
 * cosi' cambiarlo non richiede una nuova chiamata REST. La scrittura SEO
 * vera e propria serve l'assistente AI del cliente (stesso trattamento di
 * Carica prodotti: nessun bottone-azione, solo stato del collegamento e
 * messaggio pronto - qui per singolo prodotto).
 */
export default function SeoStatoTab( { onNavigate } ) {
	const { dati: prodotti, caricamento, errore, carica } = useRestFetch(
		() => '/push-contempt/v1/seo/stato'
	);
	const [ prodottoSelezionato, setProdottoSelezionato ] = useState( null );
	const [ filtro, setFiltro ] = useState( 'tutti' );
	const [ selezionati, setSelezionati ] = useState( new Set() );
	const [ inCorso, setInCorso ] = useState( false );
	const [ esito, setEsito ] = useState( null );
	const [ aperto, setAperto ] = useState( null );

	const prodottiFiltrati = prodotti ? prodotti.filter( FILTRI[ filtro ] ) : null;
	const conVersioniVisibili = prodottiFiltrati ? prodottiFiltrati.filter( ( p ) => p.versioni ) : [];

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

	const esegui = ( azione, ids ) => {
		const { path, dati, esitoOk, conferma, verbo } = AZIONI[ azione ];
		const quanti = 1 === ids.length ? 'di questo prodotto' : `di ${ ids.length } prodotti`;
		if ( ! window.confirm( conferma( quanti ) ) ) {
			return;
		}
		setInCorso( true );
		setEsito( null );
		apiFetch( { path: `/push-contempt/v1${ path }`, method: 'POST', data: { ids, ...dati } } )
			.then( ( esiti ) => {
				const valori = Object.values( esiti );
				const fatti = valori.filter( ( e ) => esitoOk === e ).length;
				const gia = valori.filter( ( e ) => 'gia_in_uso' === e ).length;
				setEsito( {
					status: fatti + gia === ids.length ? 'success' : 'warning',
					testo:
						`${ verbo }: ${ fatti } su ${ ids.length }.` +
						( gia ? ` ${ gia } già uguali, lasciati come sono.` : '' ),
				} );
				setSelezionati( new Set() );
				carica();
			} )
			.catch( ( err ) => setEsito( { status: 'error', testo: err.message || 'Operazione non riuscita.' } ) )
			.finally( () => setInCorso( false ) );
	};

	return (
		<div style={ { marginTop: '16px' } }>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Per ogni prodotto pubblicato o in bozza mostra se titolo SEO, meta description e parola chiave focus
				(impostati da Yoast) sono presenti e quanto sono lunghi - utile per capire da dove iniziare
				un'ottimizzazione. Usa il filtro per concentrarti sui prodotti appena caricati, su quelli ancora in bozza
				o sul catalogo consolidato. Clicca su un prodotto per aprirne la scheda di modifica e sistemare i campi
				SEO.
			</p>
			<StatoCollegamentoAI onNavigate={ onNavigate } />

			<div style={ { margin: '16px 0', display: 'flex', gap: '16px', alignItems: 'flex-end' } }>
				<Button variant="primary" onClick={ carica } isBusy={ caricamento }>
					Aggiorna
				</Button>
				{ conVersioniVisibili.length > 0 && (
					<Button
						variant="tertiary"
						onClick={ () => setSelezionati( new Set( conVersioniVisibili.map( ( p ) => p.id ) ) ) }
					>
						Seleziona tutti con versioni salvate
					</Button>
				) }
				<SelectControl
					label="Mostra"
					value={ filtro }
					options={ OPZIONI_FILTRO }
					onChange={ setFiltro }
					style={ { minWidth: '280px' } }
				/>
			</div>

			{ errore && (
				<Notice status="error" isDismissible={ false }>
					{ errore }
				</Notice>
			) }

			{ selezionati.size > 0 && (
				<div style={ { margin: '0 0 12px', display: 'flex', gap: '8px', alignItems: 'center', flexWrap: 'wrap' } }>
					<strong>{ selezionati.size } selezionati:</strong>
					{ [ 'base', 'precedente', 'nuova_base' ].map( ( azione ) => (
						<Button
							key={ azione }
							variant="secondary"
							isBusy={ inCorso }
							disabled={ inCorso }
							onClick={ () => esegui( azione, Array.from( selezionati ) ) }
						>
							{ { base: 'Torna alla base', precedente: 'Torna al precedente', nuova_base: 'Usa l\'attuale come base' }[ azione ] }
						</Button>
					) ) }
					<Button variant="link" onClick={ () => setSelezionati( new Set() ) }>
						Deseleziona
					</Button>
				</div>
			) }

			{ esito && (
				<Notice status={ esito.status } onRemove={ () => setEsito( null ) }>
					{ esito.testo }
				</Notice>
			) }

			{ caricamento && <Spinner /> }

			{ ! caricamento && prodottiFiltrati && prodottiFiltrati.length > 0 && (
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Prodotto</th>
							<th>Stato</th>
							<th>Inserito</th>
							<th>Titolo SEO</th>
							<th>Meta description</th>
							<th>Parola chiave focus</th>
							<th>Scrivi con AI</th>
							<th>Versioni</th>
						</tr>
					</thead>
					<tbody>
						{ prodottiFiltrati.map( ( prodotto ) => (
							<Fragment key={ prodotto.id }>
							<tr>
								<td>
									<LinkProdotto id={ prodotto.id }>{ prodotto.titolo }</LinkProdotto>
								</td>
								<td>{ 'draft' === prodotto.stato ? 'Bozza' : 'Pubblicato' }</td>
								<td>
									{ prodotto.data_creazione
										? new Date( prodotto.data_creazione ).toLocaleString( 'it-IT' )
										: '—' }
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
								<td>
									{ prodotto.versioni ? (
										<div>
											<CheckboxControl
												label="Seleziona"
												checked={ selezionati.has( prodotto.id ) }
												onChange={ ( attiva ) => attivaSelezione( prodotto.id, attiva ) }
											/>
											<Button
												variant="link"
												onClick={ () => setAperto( aperto === prodotto.id ? null : prodotto.id ) }
											>
												{ aperto === prodotto.id ? 'Nascondi versioni' : 'Mostra versioni' }
											</Button>
										</div>
									) : (
										'—'
									) }
								</td>
							</tr>
							{ prodotto.versioni && aperto === prodotto.id && (
								<tr>
									<td colSpan={ 8 }>
										<VersioniSeo
											versioni={ prodotto.versioni }
											occupato={ inCorso }
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

			{ ! caricamento && prodottiFiltrati && 0 === prodottiFiltrati.length && (
				<p style={ { color: '#50575e' } }>Nessun prodotto in questa categoria.</p>
			) }

			{ prodottoSelezionato && (
				<div style={ { marginTop: '16px' } }>
					<MessaggioProntoAI
						testo={ messaggioPronto( prodottoSelezionato ) }
						etichetta={ `Messaggio pronto per "${ prodottoSelezionato.titolo }":` }
					/>
				</div>
			) }

			<ConcorrentiSezione />
		</div>
	);
}
