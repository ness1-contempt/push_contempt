import { TabPanel } from '@wordpress/components';
import { useState } from '@wordpress/element';
import ImpostazioniTab from './ImpostazioniTab';
import PanoramicaTab from './PanoramicaTab';
import PlaceholderTab from './PlaceholderTab';
import PrezziTab from './PrezziTab';
import SaluteCatalogoTab from './SaluteCatalogoTab';
import ScorteTab from './ScorteTab';
import SeoStatoTab from './SeoStatoTab';
import VenditeTab from './VenditeTab';

const TABS = [
	{ name: 'panoramica', title: 'Panoramica' },
	{ name: 'impostazioni', title: 'Impostazioni' },
	{ name: 'carica-prodotti', title: 'Carica prodotti' },
	{ name: 'seo', title: 'SEO' },
	{ name: 'prezzi', title: 'Prezzi' },
	{ name: 'scorte', title: 'Scorte' },
	{ name: 'vendite', title: 'Vendite' },
	{ name: 'salute-catalogo', title: 'Salute catalogo' },
];

// Tab che chiamano direttamente la REST API del plugin, sempre disponibili
// anche senza sessione AI collegata.
const CONTENUTI = {
	panoramica: PanoramicaTab,
	impostazioni: ImpostazioniTab,
	seo: SeoStatoTab,
	prezzi: PrezziTab,
	scorte: ScorteTab,
	vendite: VenditeTab,
	'salute-catalogo': SaluteCatalogoTab,
};

// Tab che richiedono una sessione AI collegata (il "ponte", non ancora deciso).
const RICHIEDE_AGENTE = [ 'carica-prodotti' ];

export default function App() {
	// Tab controllata (non solo "initialTabName") per permettere ai riquadri
	// della Panoramica di saltare direttamente a una tab di dettaglio; il
	// cambio di "key" forza TabPanel a ripartire dalla nuova tab attiva.
	const [ tabAttiva, setTabAttiva ] = useState( 'panoramica' );

	return (
		<div className="push-contempt-app">
			<TabPanel key={ tabAttiva } tabs={ TABS } initialTabName={ tabAttiva } onSelect={ setTabAttiva }>
				{ ( tab ) => <Contenuto nomeTab={ tab.name } onNavigate={ setTabAttiva } /> }
			</TabPanel>
		</div>
	);
}

function Contenuto( { nomeTab, onNavigate } ) {
	const Tab = CONTENUTI[ nomeTab ];
	if ( Tab ) {
		return <Tab onNavigate={ onNavigate } />;
	}
	if ( RICHIEDE_AGENTE.includes( nomeTab ) ) {
		return <PlaceholderTab messaggio="Richiede una sessione AI collegata - in arrivo." />;
	}
	return <PlaceholderTab messaggio="In arrivo." />;
}
