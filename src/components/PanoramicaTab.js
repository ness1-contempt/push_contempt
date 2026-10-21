import apiFetch from '@wordpress/api-fetch';
import { Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import BarChart from './BarChart';
import StatCard from './StatCard';

// Stesse soglie di default usate nelle tab Scorte e Salute catalogo: la
// Panoramica mostra una prima lettura, non sostituisce i filtri di dettaglio.
const SOGLIA_SCORTE_DEFAULT = 5;
const SOGLIA_DESCRIZIONE_DEFAULT = 50;

const COLORE_ESAURITO = '#d63638';
const COLORE_NORMALE = '#2271b1';

/**
 * Riepilogo generale: numeri chiave e un grafico per le tab "solo script"
 * che beneficiano di una vista d'insieme (Scorte, Vendite, Salute catalogo).
 * SEO e Carica prodotti non compaiono qui per ora.
 */
export default function PanoramicaTab( { onNavigate } ) {
	const [ dati, setDati ] = useState( null );
	const [ errore, setErrore ] = useState( null );

	useEffect( () => {
		Promise.all( [
			apiFetch( { path: `/push-contempt/v1/scorte?soglia=${ SOGLIA_SCORTE_DEFAULT }` } ),
			apiFetch( { path: '/push-contempt/v1/vendite?periodo=1_mese' } ),
			apiFetch( {
				path: `/push-contempt/v1/salute-catalogo?soglia_descrizione=${ SOGLIA_DESCRIZIONE_DEFAULT }`,
			} ),
		] )
			.then( ( [ scorte, vendite, salute ] ) => setDati( { scorte, vendite, salute } ) )
			.catch( ( err ) => setErrore( err.message || 'Errore nel caricamento.' ) );
	}, [] );

	return (
		<div style={ { marginTop: '16px' } }>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Prima lettura del negozio: scorte critiche, vendite dell'ultimo mese e problemi nel catalogo, con le
				soglie di default delle rispettive tab. Clicca "Vai a..." su ogni riquadro per i filtri e l'elenco
				completo.
			</p>

			{ errore && (
				<Notice status="error" isDismissible={ false }>
					{ errore }
				</Notice>
			) }

			{ ! errore && ! dati && <Spinner /> }

			{ dati && <Riquadri dati={ dati } onNavigate={ onNavigate } /> }
		</div>
	);
}

function Riquadri( { dati, onNavigate } ) {
	const righeScorte = dati.scorte.slice( 0, 8 ).map( ( prodotto ) => ( {
		etichetta: prodotto.titolo,
		valore: prodotto.scorte ?? 0,
		colore: prodotto.esaurito ? COLORE_ESAURITO : COLORE_NORMALE,
		etichettaValore: prodotto.esaurito ? 'Esaurito' : String( prodotto.scorte ),
	} ) );

	const righeVendite = dati.vendite.piu_venduti.slice( 0, 5 ).map( ( riga ) => ( {
		etichetta: riga.titolo,
		valore: riga.quantita_venduta,
		colore: COLORE_NORMALE,
		etichettaValore: `${ riga.quantita_venduta } pz`,
	} ) );

	const conteggiProblemi = {};
	dati.salute.forEach( ( prodotto ) =>
		prodotto.problemi.forEach( ( problema ) => {
			// Il testo della descrizione troppo corta include la soglia scelta
			// ("... meno di 50 caratteri"): si raggruppa sulla parte fissa.
			const chiave = problema.split( ' (' )[ 0 ];
			conteggiProblemi[ chiave ] = ( conteggiProblemi[ chiave ] || 0 ) + 1;
		} )
	);
	const righeSalute = Object.entries( conteggiProblemi )
		.sort( ( a, b ) => b[ 1 ] - a[ 1 ] )
		.map( ( [ etichetta, valore ] ) => ( {
			etichetta,
			valore,
			colore: COLORE_NORMALE,
			etichettaValore: String( valore ),
		} ) );

	return (
		<div style={ { display: 'flex', gap: '16px', flexWrap: 'wrap' } }>
			<StatCard
				titolo="Scorte"
				valoreGrande={ dati.scorte.length }
				descrizione={ `Prodotti esauriti o con scorte pari o sotto ${ SOGLIA_SCORTE_DEFAULT } unita'.` }
				azioneLabel="Vai a Scorte →"
				onAzione={ () => onNavigate( 'scorte' ) }
			>
				{ 0 === righeScorte.length && <p>Nessun prodotto sotto scorta.</p> }
				{ righeScorte.length > 0 && (
					<>
						<BarChart righe={ righeScorte } />
						<div style={ { display: 'flex', gap: '16px', marginTop: '8px', fontSize: '12px', color: '#50575e' } }>
							<Legenda colore={ COLORE_ESAURITO } etichetta="Esaurito" />
							<Legenda colore={ COLORE_NORMALE } etichetta="Sotto soglia" />
						</div>
					</>
				) }
			</StatCard>

			<StatCard
				titolo="Vendite"
				valoreGrande={ `€ ${ dati.vendite.ricavo_totale_periodo }` }
				descrizione={ `Ricavo netto nell'ultimo mese (dal ${ dati.vendite.dal }). Prodotti più venduti per quantita'.` }
				azioneLabel="Vai a Vendite →"
				onAzione={ () => onNavigate( 'vendite' ) }
			>
				{ 0 === righeVendite.length && <p>Nessuna vendita nel periodo.</p> }
				{ righeVendite.length > 0 && <BarChart righe={ righeVendite } /> }
			</StatCard>

			<StatCard
				titolo="Salute catalogo"
				valoreGrande={ dati.salute.length }
				descrizione="Prodotti pubblicati con almeno un problema, per tipo di problema piu' frequente."
				azioneLabel="Vai a Salute catalogo →"
				onAzione={ () => onNavigate( 'salute-catalogo' ) }
			>
				{ 0 === righeSalute.length && <p>Nessun problema trovato nel catalogo.</p> }
				{ righeSalute.length > 0 && <BarChart righe={ righeSalute } /> }
			</StatCard>
		</div>
	);
}

function Legenda( { colore, etichetta } ) {
	return (
		<span style={ { display: 'inline-flex', alignItems: 'center', gap: '4px' } }>
			<span style={ { width: '10px', height: '10px', background: colore, borderRadius: '2px' } } />
			{ etichetta }
		</span>
	);
}
