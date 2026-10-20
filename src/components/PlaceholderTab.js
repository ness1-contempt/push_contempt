import { Notice } from '@wordpress/components';

/**
 * Segnaposto per le tab non ancora costruite (o che richiedono una sessione
 * AI collegata, decisione sul "ponte" ancora da prendere).
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
