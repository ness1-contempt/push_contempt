import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, SelectControl, Spinner, TextControl } from '@wordpress/components';
import { Fragment, useState } from '@wordpress/element';
import CellaTipoPrezzo from './CellaTipoPrezzo';
import LinkProdotto from './LinkProdotto';
import MessaggioProntoAI from './MessaggioProntoAI';
import useRestFetch from '../hooks/useRestFetch';
import { messaggioAlt } from '../lib/messaggiContenuti';

const ETICHETTE_RUOLO = {
	principale: 'immagine in evidenza',
	galleria: 'galleria',
	variante: 'immagine di una variante',
};

const OPZIONI_FILTRO = [
	{ label: 'Solo con problemi (ALT mancante o senza parola chiave)', value: 'problemi' },
	{ label: 'Tutti i prodotti con immagini', value: 'tutti' },
];

const FILTRI = {
	problemi: ( prodotto ) => prodotto.mancanti + prodotto.senza_parola_chiave > 0,
	tutti: ( prodotto ) => prodotto.numero_immagini > 0,
};

const PROBLEMI = {
	mancante: 'ALT mancante',
	senza_parola_chiave: 'Manca la parola chiave',
};

/**
 * Le immagini di un prodotto con il loro ALT, modificabile a mano: salva solo
 * quelle cambiate.
 *
 * @param {Object}   props
 * @param {Object}   props.prodotto Riga di /alt/stato.
 * @param {boolean}  props.occupato Un salvataggio e' in corso.
 * @param {Function} props.onSalva  Chiamata con l'elenco { allegato_id, alt } delle immagini cambiate.
 */
function ModificaAlt( { prodotto, occupato, onSalva } ) {
	const [ modifiche, setModifiche ] = useState( {} );
	const cambiate = prodotto.immagini.filter(
		( immagine ) =>
			undefined !== modifiche[ immagine.allegato_id ] && modifiche[ immagine.allegato_id ] !== immagine.alt
	);

	return (
		<div style={ { padding: '8px 0' } }>
			{ prodotto.parola_chiave_focus && (
				<p style={ { color: '#50575e', marginTop: 0 } }>
					Parola chiave focus: <strong>{ prodotto.parola_chiave_focus }</strong>
				</p>
			) }
			{ prodotto.immagini.map( ( immagine ) => (
				<div
					key={ immagine.allegato_id }
					style={ { display: 'flex', gap: '12px', alignItems: 'flex-start', marginBottom: '12px' } }
				>
					{ immagine.miniatura ? (
						<img
							src={ immagine.miniatura }
							alt=""
							width={ 64 }
							height={ 64 }
							style={ { objectFit: 'cover', border: '1px solid #dcdcde', flex: 'none' } }
						/>
					) : (
						<div style={ { width: 64, height: 64, background: '#f0f0f1', flex: 'none' } } />
					) }
					<div style={ { flex: '1 1 auto', maxWidth: '640px' } }>
						<TextControl
							label={ `ALT — ${ ETICHETTE_RUOLO[ immagine.ruolo ] ?? 'galleria' }` }
							help={ immagine.file }
							value={ modifiche[ immagine.allegato_id ] ?? immagine.alt }
							onChange={ ( testo ) => setModifiche( ( precedenti ) => ( { ...precedenti, [ immagine.allegato_id ]: testo } ) ) }
							__nextHasNoMarginBottom
						/>
						{ immagine.problema && undefined === modifiche[ immagine.allegato_id ] && (
							<span style={ { color: '#b32d2e', fontSize: '12px' } }>{ PROBLEMI[ immagine.problema ] }</span>
						) }
					</div>
				</div>
			) ) }
			<Button
				variant="primary"
				isBusy={ occupato }
				disabled={ occupato || 0 === cambiate.length }
				onClick={ () =>
					onSalva(
						cambiate.map( ( immagine ) => ( {
							allegato_id: immagine.allegato_id,
							alt: modifiche[ immagine.allegato_id ],
						} ) )
					)
				}
			>
				{ 0 === cambiate.length ? 'Salva ALT' : `Salva ALT (${ cambiate.length })` }
			</Button>
		</div>
	);
}

/**
 * Immagini dei prodotti e loro testo ALT: quali ne hanno uno mancante o senza
 * la parola chiave (due controlli di Yoast), e modifica a mano. Legge e scrive
 * sulla REST API del plugin, nessuna AI; scrivere gli ALT al posto tuo e' un
 * lavoro per l'assistente AI del cliente, di cui qui si prepara il messaggio.
 */
export default function AltSezione() {
	const { dati: prodotti, caricamento, errore, carica } = useRestFetch( () => '/push-contempt/v1/alt/stato' );
	const [ filtro, setFiltro ] = useState( 'problemi' );
	const [ aperto, setAperto ] = useState( null );
	const [ prodottoMessaggio, setProdottoMessaggio ] = useState( null );
	const [ inCorso, setInCorso ] = useState( false );
	const [ esito, setEsito ] = useState( null );

	const visibili = prodotti ? prodotti.filter( FILTRI[ filtro ] ) : null;

	const salva = ( prodotto, immagini ) => {
		setInCorso( true );
		setEsito( null );
		apiFetch( {
			path: '/push-contempt/v1/alt/prodotto',
			method: 'POST',
			data: { id: prodotto.id, immagini },
		} )
			.then( ( risposta ) => {
				setEsito( {
					status: 'success',
					testo: `ALT salvati per «${ prodotto.titolo }»: ${ risposta.immagini.length }.`,
				} );
				carica();
			} )
			.catch( ( err ) => setEsito( { status: 'error', testo: err.message || 'Salvataggio non riuscito.' } ) )
			.finally( () => setInCorso( false ) );
	};

	return (
		<div style={ { marginTop: '16px' } }>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Per ogni prodotto conta le immagini (in evidenza, galleria e, nei prodotti variabili, delle varianti) con il testo ALT mancante o che non contiene
				la parola chiave focus: sono due controlli di Yoast. "Modifica ALT" mostra le immagini e ti lascia
				correggere il testo a mano; "Prepara messaggio" ti dà il testo da dare al tuo assistente AI per scriverli
				al posto tuo.
			</p>

			<div style={ { margin: '16px 0', display: 'flex', gap: '16px', alignItems: 'flex-end', flexWrap: 'wrap' } }>
				<Button variant="primary" onClick={ carica } isBusy={ caricamento }>
					Aggiorna
				</Button>
				<SelectControl label="Mostra" value={ filtro } options={ OPZIONI_FILTRO } onChange={ setFiltro } />
			</div>

			{ errore && (
				<Notice status="error" isDismissible={ false }>
					{ errore }
				</Notice>
			) }

			{ esito && (
				<Notice status={ esito.status } onRemove={ () => setEsito( null ) }>
					{ esito.testo }
				</Notice>
			) }

			{ caricamento && <Spinner /> }

			{ ! caricamento && visibili && visibili.length > 0 && (
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style={ { width: '22%' } }>Prodotto</th>
							<th style={ { width: '15%' } }>Tipo e prezzo</th>
							<th style={ { width: '14%' } }>Parola chiave focus</th>
							<th>Immagini</th>
							<th>ALT mancanti</th>
							<th>Senza parola chiave</th>
							<th style={ { width: '250px' } }>Azioni</th>
						</tr>
					</thead>
					<tbody>
						{ visibili.map( ( prodotto ) => (
							<Fragment key={ prodotto.id }>
								<tr>
									<td>
										<LinkProdotto id={ prodotto.id }>{ prodotto.titolo }</LinkProdotto>
										{ 'publish' !== prodotto.stato && ' (bozza)' }
									</td>
									<td>
										<CellaTipoPrezzo prodotto={ prodotto } />
									</td>
									<td>{ prodotto.parola_chiave_focus || '—' }</td>
									<td>{ prodotto.numero_immagini }</td>
									<td>{ prodotto.mancanti }</td>
									<td>{ prodotto.parola_chiave_focus ? prodotto.senza_parola_chiave : '—' }</td>
									<td style={ { whiteSpace: 'nowrap' } }>
										{ prodotto.numero_immagini > 0 && (
											<>
												<Button
													variant="link"
													onClick={ () => setAperto( aperto === prodotto.id ? null : prodotto.id ) }
												>
													{ aperto === prodotto.id ? 'Chiudi' : 'Modifica ALT' }
												</Button>
												{ ' · ' }
											</>
										) }
										<Button variant="link" onClick={ () => setProdottoMessaggio( prodotto ) }>
											Prepara messaggio
										</Button>
									</td>
								</tr>
								{ aperto === prodotto.id && (
									<tr>
										<td colSpan={ 7 }>
											<ModificaAlt
												prodotto={ prodotto }
												occupato={ inCorso }
												onSalva={ ( immagini ) => salva( prodotto, immagini ) }
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
				<p style={ { color: '#50575e' } }>
					{ 'problemi' === filtro ? 'Nessun prodotto con ALT da sistemare.' : 'Nessun prodotto con immagini.' }
				</p>
			) }

			{ prodottoMessaggio && (
				<div style={ { marginTop: '16px' } }>
					<MessaggioProntoAI
						testo={ messaggioAlt( prodottoMessaggio ) }
						etichetta={ `Messaggio pronto per "${ prodottoMessaggio.titolo }":` }
					/>
				</div>
			) }
		</div>
	);
}
