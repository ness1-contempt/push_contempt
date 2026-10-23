import { CheckboxControl, SelectControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { AREE_ISPIRAZIONE, LIVELLI_SOMIGLIANZA, messaggioIspirazione } from '../lib/messaggioIspirazione';
import MessaggioProntoAI from './MessaggioProntoAI';

/**
 * Prepara il messaggio per l'assistente AI che riscrive titolo SEO e meta
 * description dei prodotti scelti nella tabella prendendo ispirazione da un
 * sito analizzato. Qui l'utente decide tre cose, prima che l'AI scriva
 * qualunque cosa: da quale concorrente, per quali aree, e (se vuole) fino a
 * che livello di somiglianza accetta. Il plugin non scrive niente: produce solo
 * il messaggio, e l'AI lavora con i suoi strumenti.
 *
 * @param {Object}   props
 * @param {Object[]} props.prodotti Prodotti selezionati nella tabella.
 * @param {Object[]} props.analisi  Elenco di /concorrenti (con `ultima` e `storico`).
 */
export default function IspirazioneConcorrente( { prodotti, analisi } ) {
	const concorrenti = ( analisi || [] ).filter( ( riga ) => ! riga.ultima.proprio );
	const [ sito, setSito ] = useState( '' );
	const [ aree, setAree ] = useState( [] );
	const [ livello, setLivello ] = useState( '' );

	const scelto = concorrenti.find( ( riga ) => riga.ultima.sito === sito )?.ultima;
	const pronto = scelto && aree.length > 0 && prodotti.length > 0;

	const attivaArea = ( chiave, attiva ) =>
		setAree( ( precedenti ) => ( attiva ? [ ...precedenti, chiave ] : precedenti.filter( ( a ) => a !== chiave ) ) );

	return (
		<div style={ { marginTop: '32px', maxWidth: '780px' } }>
			<h3>Riscrivi con ispirazione da un concorrente</h3>
			<p style={ { color: '#50575e' } }>
				Scegli i prodotti nella tabella, un sito già analizzato e le aree da cui prendere ispirazione: ti preparo il
				messaggio da incollare al tuo assistente AI. Non scrive niente da solo: i testi li scrive l'assistente,
				originali, e tu decidi il livello di somiglianza che accetti prima che venga applicato qualcosa.
			</p>

			{ 0 === concorrenti.length ? (
				<p>
					<strong>Nessun concorrente analizzato per ora.</strong> Chiedi al tuo assistente di analizzare un sito:
					comparirà nella sezione «Concorrenti e confronto» e potrai sceglierlo qui.
				</p>
			) : (
				<>
					<p>
						<strong>1. Prodotti:</strong>{ ' ' }
						{ 0 === prodotti.length
							? 'spunta almeno un prodotto nella prima colonna della tabella.'
							: `${ prodotti.length } selezionati nella tabella.` }
					</p>

					<SelectControl
						label="2. Concorrente"
						value={ sito }
						options={ [
							{ label: 'Scegli un sito analizzato…', value: '' },
							...concorrenti.map( ( { ultima } ) => ( {
								label: `${ ultima.sito } (punteggio ${ ultima.punteggio ?? '—' }/9, ${ new Date( ultima.ricevuta_il ).toLocaleDateString( 'it-IT' ) })`,
								value: ultima.sito,
							} ) ),
						] }
						onChange={ setSito }
					/>

					<fieldset style={ { border: 0, padding: 0, margin: '0 0 16px' } }>
						<legend style={ { fontWeight: 600, marginBottom: '4px' } }>3. Aree da cui prendere ispirazione</legend>
						{ AREE_ISPIRAZIONE.map( ( area ) => (
							<CheckboxControl
								key={ area.chiave }
								label={
									scelto?.aree[ area.chiave ]
										? `${ area.etichetta } (il concorrente ha ${ scelto.aree[ area.chiave ].punteggio }/9)`
										: area.etichetta
								}
								checked={ aree.includes( area.chiave ) }
								onChange={ ( attiva ) => attivaArea( area.chiave, attiva ) }
							/>
						) ) }
						<p style={ { color: '#50575e', fontSize: '12px', margin: '4px 0 0' } }>
							Sono le due aree che corrispondono ai campi che l'assistente scrive. Le altre (intestazioni, dati
							strutturati, contenuto, aspetti tecnici) riguardano la struttura del sito o la descrizione. Il
							punteggio del concorrente è solo un riferimento: un voto basso non esclude un buon modello.
						</p>
					</fieldset>

					<SelectControl
						label="4. Somiglianza accettata (0 = nessuna, 9 = copia)"
						value={ livello }
						options={ LIVELLI_SOMIGLIANZA.map( ( l ) => ( { label: l.etichetta, value: l.valore } ) ) }
						onChange={ setLivello }
					/>

					{ pronto ? (
						<MessaggioProntoAI
							testo={ messaggioIspirazione( { prodotti, concorrente: scelto, aree, livello } ) }
							etichetta="Messaggio pronto per il tuo assistente AI:"
						/>
					) : (
						<p style={ { color: '#50575e' } }>
							Il messaggio compare qui quando hai scelto almeno un prodotto, un concorrente e un'area.
						</p>
					) }
				</>
			) }
		</div>
	);
}
