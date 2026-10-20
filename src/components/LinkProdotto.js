/**
 * Link alla scheda di modifica nativa del prodotto (la stessa pagina che si
 * apre dalla lista prodotti di WooCommerce), aperto in una nuova scheda per
 * non perdere lo stato della tab corrente. Percorso relativo a wp-admin/,
 * dove vive anche la pagina del plugin - funziona in qualunque ambiente
 * senza bisogno di conoscere l'URL del sito.
 */
export default function LinkProdotto( { id, children } ) {
	return (
		<a href={ `post.php?post=${ id }&action=edit` } target="_blank" rel="noopener noreferrer">
			{ children }
		</a>
	);
}
