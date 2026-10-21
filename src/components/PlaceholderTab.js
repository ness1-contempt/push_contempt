import { Notice } from '@wordpress/components';

/**
 * Segnaposto generico per una tab senza contenuto registrato in
 * App.js (CONTENUTI) - nessuna delle 8 tab attuali lo usa, resta come
 * fallback di sicurezza per una futura tab aggiunta prima di avere
 * contenuto reale pronto.
 *
 * Usa il componente Notice di @wordpress/components, non un <div
 * className="notice">: wp-admin/js/common.js sposta fisicamente nel DOM
 * qualunque elemento con classe "notice" (pensato per gli avvisi
 * server-side) subito dopo l'H1 della pagina - lo strapperebbe via dalla
 * sua posizione dentro la tab.
 */
export default function PlaceholderTab( { messaggio } ) {
	return (
		<div style={ { marginTop: '16px', maxWidth: '480px' } }>
			<Notice status="info" isDismissible={ false }>
				{ messaggio }
			</Notice>
		</div>
	);
}
