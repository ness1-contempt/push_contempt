import { Button } from '@wordpress/components';

const ETICHETTE = {
	base: 'Torna alla base',
	precedente: 'Torna al precedente',
	nuova_base: "Usa l'attuale come base",
};

/**
 * Barra delle azioni sui prodotti selezionati nelle tabelle con versioni (SEO,
 * descrizioni): quanti sono scelti, quanti hanno versioni salvate (le azioni
 * riguardano solo quelli) e i tre pulsanti.
 *
 * @param {Object}   props
 * @param {number}   props.selezionati Prodotti selezionati.
 * @param {number}   props.conVersioni Fra questi, quelli con versioni salvate.
 * @param {boolean}  props.inCorso     Un'azione e' in corso.
 * @param {Function} props.onAzione    Chiamata con 'base', 'precedente' o 'nuova_base'.
 * @param {Function} props.onDeseleziona Svuota la selezione.
 */
export default function BarraAzioniVersioni( { selezionati, conVersioni, inCorso, onAzione, onDeseleziona } ) {
	if ( 0 === selezionati ) {
		return null;
	}
	return (
		<div style={ { margin: '0 0 12px', display: 'flex', gap: '8px', alignItems: 'center', flexWrap: 'wrap' } }>
			<strong>{ selezionati } selezionati</strong>
			<span style={ { color: '#50575e' } }>
				({ conVersioni } con versioni salvate: le azioni sulle versioni riguardano solo quelli)
			</span>
			{ Object.keys( ETICHETTE ).map( ( azione ) => (
				<Button
					key={ azione }
					variant="secondary"
					isBusy={ inCorso }
					disabled={ inCorso || 0 === conVersioni }
					onClick={ () => onAzione( azione ) }
				>
					{ ETICHETTE[ azione ] }
				</Button>
			) ) }
			<Button variant="link" onClick={ onDeseleziona }>
				Deseleziona
			</Button>
		</div>
	);
}
