import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { Fragment, useState } from '@wordpress/element';
import useRestFetch from '../hooks/useRestFetch';

const AREE = [
	{ chiave: 'titoli', etichetta: 'Titoli' },
	{ chiave: 'meta_description', etichetta: 'Meta description' },
	{ chiave: 'intestazioni', etichetta: 'Intestazioni' },
	{ chiave: 'dati_strutturati', etichetta: 'Dati strutturati' },
	{ chiave: 'contenuto', etichetta: 'Contenuto' },
	{ chiave: 'tecnico', etichetta: 'Aspetti tecnici' },
];

/**
 * Colore di sfondo di un punteggio 0-9 (il numero resta sempre scritto, il
 * colore e' solo un aiuto): da 7 in su buono, da 4 a 6 migliorabile, sotto 4 debole.
 *
 * @param {number|null} punteggio Punteggio dell'area o complessivo.
 */
function coloreDi( punteggio ) {
	if ( null === punteggio || undefined === punteggio ) {
		return 'transparent';
	}
	if ( punteggio >= 7 ) {
		return '#d7f0dd';
	}
	return punteggio >= 4 ? '#fcf0c3' : '#f7d4d6';
}

/**
 * Differenza fra un punteggio e quello del sito dell'utente, es. "+2" o "−1".
 *
 * @param {number|undefined} punteggio Punteggio del concorrente.
 * @param {number|undefined} proprio   Punteggio del sito dell'utente nella stessa area.
 */
function differenza( punteggio, proprio ) {
	if ( undefined === punteggio || undefined === proprio || punteggio === proprio ) {
		return null;
	}
	const delta = punteggio - proprio;
	return delta > 0 ? `+${ delta }` : `−${ Math.abs( delta ) }`;
}

function Cella( { punteggio, proprio } ) {
	const delta = differenza( punteggio, proprio );
	return (
		<td style={ { background: coloreDi( punteggio ), textAlign: 'center' } }>
			{ undefined === punteggio ? '—' : punteggio }
			{ delta && <small style={ { color: '#50575e', marginLeft: '4px' } }>({ delta })</small> }
		</td>
	);
}

function Dettaglio( { analisi, storico } ) {
	return (
		<div style={ { padding: '8px 4px 12px' } }>
			<p style={ { color: '#50575e', margin: '0 0 8px' } }>
				{ analisi.pagine } pagine valutate
				{ analisi.perimetro ? ` · ${ analisi.perimetro }` : '' }
				{ analisi.metodo ? ` · metodo: ${ analisi.metodo }` : '' }
				{ storico.length > 0 && (
					<>
						{ ' · ' }
						Storico del totale: { [ ...storico.map( ( v ) => v.punteggio ?? '—' ), analisi.punteggio ?? '—' ].join( ' → ' ) }
					</>
				) }
			</p>
			{ AREE.filter( ( area ) => analisi.aree[ area.chiave ] ).map( ( area ) => {
				const dati = analisi.aree[ area.chiave ];
				return (
					<div key={ area.chiave } style={ { marginBottom: '8px' } }>
						<strong>
							{ area.etichetta }: { dati.punteggio }
						</strong>
						<ul style={ { margin: '2px 0 0 18px', listStyle: 'disc' } }>
							{ dati.criteri.map( ( criterio ) => (
								<li key={ criterio.criterio }>
									{ criterio.criterio } — { criterio.evidenza }
								</li>
							) ) }
							{ dati.tetti.map( ( tetto ) => (
								<li key={ tetto.regola } style={ { color: '#b32d2e' } }>
									Limite: { tetto.regola } ({ tetto.misura }) — { tetto.effetto }
								</li>
							) ) }
						</ul>
					</div>
				);
			} ) }
		</div>
	);
}

/**
 * Punteggi SEO dei siti analizzati (concorrenti e, se analizzato, il sito
 * dell'utente) per area, da 0 a 9. L'analisi la fa il server MCP sul PC
 * dell'utente, su richiesta al suo assistente AI, e ne manda qui il
 * riepilogo: questa sezione lo mostra soltanto e lascia toglierlo. Il
 * punteggio e' un riferimento, non un obbligo: decide l'utente se e per
 * quali aree usare un sito come ispirazione.
 */
export default function ConcorrentiSezione() {
	const { dati: righe, caricamento, errore, carica } = useRestFetch( () => '/push-contempt/v1/concorrenti' );
	const [ aperto, setAperto ] = useState( null );
	const [ erroreElimina, setErroreElimina ] = useState( null );

	const proprio = righe ? righe.find( ( riga ) => riga.ultima.proprio ) : null;

	const elimina = ( sito ) => {
		if ( ! window.confirm( `Togliere l'analisi di ${ sito } da questa lista?` ) ) {
			return;
		}
		setErroreElimina( null );
		apiFetch( { path: `/push-contempt/v1/concorrenti?sito=${ encodeURIComponent( sito ) }`, method: 'DELETE' } )
			.then( () => {
				setAperto( null );
				carica();
			} )
			.catch( ( err ) => setErroreElimina( err.message || 'Errore nell\'eliminazione.' ) );
	};

	return (
		<div style={ { marginTop: '32px' } }>
			<h3>Concorrenti e confronto</h3>
			<p style={ { maxWidth: '780px', color: '#50575e' } }>
				Punteggio da 0 a 9 della struttura SEO dei siti che hai fatto analizzare al tuo assistente AI (es.
				«analizza il sito di un concorrente»), area per area. È solo un riferimento: scegli tu se e per quali
				aree prendere un sito come ispirazione. Se fai analizzare anche il tuo sito, compare per primo e gli
				altri mostrano la differenza rispetto a te.
			</p>
			<Button variant="secondary" onClick={ carica } isBusy={ caricamento }>
				Aggiorna
			</Button>

			{ ( errore || erroreElimina ) && (
				<Notice status="error" isDismissible={ false }>
					{ errore || erroreElimina }
				</Notice>
			) }
			{ caricamento && <Spinner /> }

			{ ! caricamento && righe && 0 === righe.length && (
				<p style={ { color: '#50575e' } }>
					Nessun sito analizzato per ora. Chiedi al tuo assistente AI di analizzare un sito: il risultato
					comparirà qui.
				</p>
			) }

			{ ! caricamento && righe && righe.length > 0 && (
				<table className="wp-list-table widefat fixed striped" style={ { marginTop: '12px' } }>
					<thead>
						<tr>
							<th>Sito</th>
							<th>Analizzato</th>
							<th style={ { textAlign: 'center' } }>Totale</th>
							{ AREE.map( ( area ) => (
								<th key={ area.chiave } style={ { textAlign: 'center' } }>
									{ area.etichetta }
								</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ righe.map( ( { ultima, storico } ) => (
							<Fragment key={ ultima.sito }>
								<tr>
									<td>
										<Button
											variant="link"
											onClick={ () => setAperto( aperto === ultima.sito ? null : ultima.sito ) }
										>
											{ ultima.sito }
										</Button>
										{ ultima.proprio && (
											<strong style={ { marginLeft: '6px', color: '#2271b1' } }>Il tuo sito</strong>
										) }
									</td>
									<td>{ new Date( ultima.ricevuta_il ).toLocaleDateString( 'it-IT' ) }</td>
									<td style={ { background: coloreDi( ultima.punteggio ), textAlign: 'center' } }>
										<strong>{ null === ultima.punteggio ? '—' : ultima.punteggio }</strong>
									</td>
									{ AREE.map( ( area ) => (
										<Cella
											key={ area.chiave }
											punteggio={ ultima.aree[ area.chiave ]?.punteggio }
											proprio={
												proprio && ! ultima.proprio
													? proprio.ultima.aree[ area.chiave ]?.punteggio
													: undefined
											}
										/>
									) ) }
								</tr>
								{ aperto === ultima.sito && (
									<tr>
										<td colSpan={ 3 + AREE.length }>
											<Dettaglio analisi={ ultima } storico={ storico } />
											<Button variant="link" isDestructive onClick={ () => elimina( ultima.sito ) }>
												Togli dalla lista
											</Button>
										</td>
									</tr>
								) }
							</Fragment>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
