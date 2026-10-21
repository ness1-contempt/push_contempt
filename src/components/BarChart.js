/**
 * Classifica orizzontale semplice (nessuna libreria grafici): una riga per
 * voce, barra proporzionale al massimo del set, valore sempre etichettato
 * a destra. Pensato per piccoli elenchi "top N", non per serie temporali.
 */
export default function BarChart( { righe } ) {
	const massimo = Math.max( ...righe.map( ( riga ) => riga.valore ), 1 );

	return (
		<div style={ { marginTop: '8px' } }>
			{ righe.map( ( riga, indice ) => (
				<div
					key={ indice }
					style={ { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '6px' } }
				>
					<span
						title={ riga.etichetta }
						style={ {
							width: '140px',
							flexShrink: 0,
							fontSize: '12px',
							color: '#1e1e1e',
							overflow: 'hidden',
							textOverflow: 'ellipsis',
							whiteSpace: 'nowrap',
						} }
					>
						{ riga.etichetta }
					</span>
					<div style={ { flex: 1, background: '#f0f0f1', height: '14px' } }>
						<div
							style={ {
								width: `${ ( riga.valore / massimo ) * 100 }%`,
								minWidth: riga.valore > 0 ? '4px' : 0,
								height: '100%',
								background: riga.colore || '#2271b1',
								borderTopRightRadius: '4px',
								borderBottomRightRadius: '4px',
							} }
						/>
					</div>
					<span
						style={ {
							width: '70px',
							flexShrink: 0,
							fontSize: '12px',
							textAlign: 'right',
							color: '#50575e',
						} }
					>
						{ riga.etichettaValore ?? riga.valore }
					</span>
				</div>
			) ) }
		</div>
	);
}
