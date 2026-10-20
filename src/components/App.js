import { TabPanel } from '@wordpress/components';
import PlaceholderTab from './PlaceholderTab';
import PrezziTab from './PrezziTab';
import SaluteCatalogoTab from './SaluteCatalogoTab';
import ScorteTab from './ScorteTab';
import SeoStatoTab from './SeoStatoTab';
import VenditeTab from './VenditeTab';

const TABS = [
	{ name: 'panoramica', title: 'Panoramica' },
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
	seo: SeoStatoTab,
	prezzi: PrezziTab,
	scorte: ScorteTab,
	vendite: VenditeTab,
	'salute-catalogo': SaluteCatalogoTab,
};

// Tab che richiedono una sessione AI collegata (il "ponte", non ancora deciso).
const RICHIEDE_AGENTE = [ 'carica-prodotti' ];

export default function App() {
	return (
		<div className="push-contempt-app">
			<TabPanel tabs={ TABS }>{ ( tab ) => <Contenuto nomeTab={ tab.name } /> }</TabPanel>
		</div>
	);
}

function Contenuto( { nomeTab } ) {
	const Tab = CONTENUTI[ nomeTab ];
	if ( Tab ) {
		return <Tab />;
	}
	if ( RICHIEDE_AGENTE.includes( nomeTab ) ) {
		return <PlaceholderTab messaggio="Richiede una sessione AI collegata - in arrivo." />;
	}
	return <PlaceholderTab messaggio="In arrivo." />;
}
