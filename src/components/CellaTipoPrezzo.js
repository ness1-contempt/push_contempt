import { etichettaPrezzo, etichettaTipo, etichettaVarianti } from '../lib/tipoPrezzo';

/**
 * Contenuto della colonna "Tipo e prezzo": tipo (e numero di varianti) sopra,
 * prezzo o intervallo sotto. Per i prodotti variabili e raggruppati il prezzo
 * e' sempre un intervallo, mai un numero solo (vedi PrezzoProdotto nel plugin).
 *
 * @param {Object} props
 * @param {Object} props.prodotto Riga con tipo, prezzo_min, prezzo_max, numero_varianti.
 */
export default function CellaTipoPrezzo( { prodotto } ) {
	const varianti = etichettaVarianti( prodotto );
	return (
		<>
			<div>{ [ etichettaTipo( prodotto.tipo ), varianti ].filter( Boolean ).join( ' · ' ) }</div>
			<div style={ { color: '#50575e' } }>{ etichettaPrezzo( prodotto ) }</div>
		</>
	);
}
