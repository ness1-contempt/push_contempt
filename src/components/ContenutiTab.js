import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import AltSezione from './AltSezione';
import DescrizioniSezione from './DescrizioniSezione';
import LinkDescrizioniSezione from './LinkDescrizioniSezione';
import StatoCollegamentoAI from './StatoCollegamentoAI';

const SEZIONI = [
	{ nome: 'descrizioni', titolo: 'Descrizioni', Contenuto: DescrizioniSezione },
	{ nome: 'alt', titolo: 'Immagini (ALT)', Contenuto: AltSezione },
	{ nome: 'link', titolo: 'Link', Contenuto: LinkDescrizioniSezione },
];

/**
 * Strumenti sui testi dei prodotti: descrizioni (con le versioni salvate),
 * testo ALT delle immagini e link nelle descrizioni. Ogni sezione legge dalla
 * REST API del plugin; per scrivere i testi serve l'assistente AI del cliente,
 * e ogni sezione prepara il messaggio da dargli.
 *
 * @param {Object}   props
 * @param {Function} props.onNavigate Cambia tab (serve al link verso Impostazioni).
 */
export default function ContenutiTab( { onNavigate } ) {
	const [ sezione, setSezione ] = useState( 'descrizioni' );
	const { Contenuto } = SEZIONI.find( ( voce ) => voce.nome === sezione );

	return (
		<div style={ { marginTop: '16px' } }>
			<StatoCollegamentoAI onNavigate={ onNavigate } />
			<div role="group" aria-label="Sezione" style={ { display: 'flex', gap: '8px', margin: '16px 0 0' } }>
				{ SEZIONI.map( ( voce ) => (
					<Button
						key={ voce.nome }
						variant={ voce.nome === sezione ? 'primary' : 'secondary' }
						aria-pressed={ voce.nome === sezione }
						onClick={ () => setSezione( voce.nome ) }
					>
						{ voce.titolo }
					</Button>
				) ) }
			</div>
			<Contenuto />
		</div>
	);
}
