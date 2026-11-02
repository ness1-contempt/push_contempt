import LinkDescrizioniSezione from './LinkDescrizioniSezione';
import StatoCollegamentoAI from './StatoCollegamentoAI';

/**
 * Strumenti sui testi dei prodotti (descrizioni e, in seguito, immagini): quello
 * che si legge e si controlla direttamente da qui, e il messaggio da dare
 * all'assistente AI per quello che richiede di scrivere.
 *
 * @param {Object}   props
 * @param {Function} props.onNavigate Cambia tab (serve al link verso Impostazioni).
 */
export default function ContenutiTab( { onNavigate } ) {
	return (
		<div style={ { marginTop: '16px' } }>
			<StatoCollegamentoAI onNavigate={ onNavigate } />
			<LinkDescrizioniSezione />
		</div>
	);
}
