import { Button } from '@wordpress/components';

/**
 * Riquadro della Panoramica: numero grande, breve spiegazione, grafico o
 * elenco passato come children, link alla tab di dettaglio corrispondente.
 */
export default function StatCard( { titolo, valoreGrande, descrizione, azioneLabel, onAzione, children } ) {
	return (
		<div
			style={ {
				background: '#fff',
				border: '1px solid #c3c4c7',
				borderRadius: '4px',
				padding: '16px',
				flex: '1 1 320px',
				minWidth: '300px',
			} }
		>
			<h3 style={ { marginTop: 0, marginBottom: '4px' } }>{ titolo }</h3>
			<div style={ { fontSize: '28px', fontWeight: 600, color: '#1e1e1e', marginBottom: '4px' } }>
				{ valoreGrande }
			</div>
			<p style={ { margin: '0 0 12px', fontSize: '13px', color: '#50575e' } }>{ descrizione }</p>
			{ children }
			<div style={ { marginTop: '12px' } }>
				<Button variant="link" onClick={ onAzione }>
					{ azioneLabel }
				</Button>
			</div>
		</div>
	);
}
