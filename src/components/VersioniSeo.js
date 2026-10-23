import { Button } from '@wordpress/components';

const ETICHETTA_CAMPI = [
	{ chiave: 'titolo_seo', etichetta: 'Titolo SEO' },
	{ chiave: 'meta_description', etichetta: 'Meta description' },
	{ chiave: 'parola_chiave_focus', etichetta: 'Parola chiave' },
];

/**
 * Data breve di una versione (vuota se non nota).
 *
 * @param {string} iso Data in formato ISO 8601.
 */
function data( iso ) {
	return iso ? new Date( iso ).toLocaleString( 'it-IT' ) : '';
}

/**
 * Una delle tre versioni: nome, che cos'e', i tre campi, chi l'ha prodotta e
 * il pulsante dell'azione che la riguarda.
 */
function Scheda( { titolo, sottotitolo, colore, etichetta, versione, nota, azione } ) {
	return (
		<div
			style={ {
				flex: '1 1 0',
				minWidth: '240px',
				background: '#fff',
				border: '1px solid #dcdcde',
				borderTop: `4px solid ${ colore }`,
				padding: '10px 12px',
			} }
		>
			<div style={ { fontWeight: 600 } }>{ titolo }</div>
			<div style={ { color: '#50575e', fontSize: '12px', marginBottom: '6px' } }>{ sottotitolo }</div>
			<div
				style={ {
					display: 'inline-block',
					background: colore,
					color: '#fff',
					fontSize: '11px',
					padding: '1px 8px',
					borderRadius: '10px',
					marginBottom: '8px',
				} }
			>
				{ etichetta }
			</div>
			{ ETICHETTA_CAMPI.map( ( campo ) => (
				<div key={ campo.chiave } style={ { marginBottom: '4px' } }>
					<span style={ { color: '#50575e', fontSize: '12px' } }>{ campo.etichetta }: </span>
					{ versione[ campo.chiave ] || '—' }
				</div>
			) ) }
			<div style={ { color: '#50575e', fontSize: '12px', margin: '8px 0' } }>
				{ [ versione.origine, data( versione.salvato_il ) ].filter( Boolean ).join( ', ' ) }
			</div>
			{ nota && <div style={ { color: '#50575e', fontSize: '12px', marginBottom: '6px' } }>{ nota }</div> }
			{ azione }
		</div>
	);
}

/**
 * Le tre versioni dei campi SEO di un prodotto: base (punto di partenza),
 * precedente (la versione da cui si viene) e attuale (quella in uso adesso in
 * Yoast). Alla base va detto chiaramente se e' l'originale di Yoast o se
 * l'utente l'ha ridefinita.
 *
 * @param {Object}   props
 * @param {Object}   props.versioni `versioni` di una riga di /seo/stato.
 * @param {Function} props.onAzione Chiamata con 'base', 'precedente' o 'nuova_base'.
 * @param {boolean}  props.occupato Un'azione e' in corso.
 */
export default function VersioniSeo( { versioni, onAzione, occupato } ) {
	const { base, precedente, attuale } = versioni;
	const baseOriginale = 'originale' === base.tipo;

	return (
		<div style={ { display: 'flex', gap: '12px', flexWrap: 'wrap', padding: '8px 0' } }>
			<Scheda
				titolo="Base"
				sottotitolo="Il punto di partenza"
				colore={ baseOriginale ? '#2271b1' : '#996800' }
				etichetta={ baseOriginale ? 'Originale: com\'era prima dei nostri interventi' : 'Impostata da te: non è l\'originale di Yoast' }
				versione={ base }
				azione={
					<Button
						variant="secondary"
						disabled={ occupato || versioni.attuale_e_base }
						onClick={ () => onAzione( 'base' ) }
					>
						Torna alla base
					</Button>
				}
			/>
			<Scheda
				titolo="Precedente"
				sottotitolo="La versione da cui vieni"
				colore="#787c82"
				etichetta="Prima dell'ultima modifica"
				versione={ precedente }
				nota={ versioni.precedente_e_base ? 'Uguale alla base.' : null }
				azione={
					<Button
						variant="secondary"
						disabled={ occupato || versioni.attuale_e_precedente }
						onClick={ () => onAzione( 'precedente' ) }
					>
						Torna al precedente
					</Button>
				}
			/>
			<Scheda
				titolo="Attuale"
				sottotitolo="In uso adesso in Yoast"
				colore="#00a32a"
				etichetta="In uso"
				versione={ attuale }
				nota={ versioni.attuale_e_base ? 'Uguale alla base.' : null }
				azione={
					<Button
						variant="tertiary"
						disabled={ occupato || versioni.attuale_e_base }
						onClick={ () => onAzione( 'nuova_base' ) }
					>
						Usa l'attuale come nuova base
					</Button>
				}
			/>
		</div>
	);
}
