<?php
/**
 * Endpoint REST per la vista d'insieme SEO dei prodotti.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use PushContempt\SeoSnapshot;
use WP_REST_Response;

/**
 * Per ogni prodotto pubblicato o in bozza: titolo SEO e meta description
 * impostati o mancanti, con le rispettive lunghezze, piu' stato e data di
 * creazione - utile per capire da dove iniziare un'ottimizzazione SEO del
 * catalogo, e per distinguere in UI i prodotti appena inseriti da quelli
 * del catalogo consolidato (vedi SeoStatoTab.js). `versioni` sono
 * base, precedente e attuale dei campi SEO (null se il prodotto non e' mai
 * stato modificato dal plugin): serve al ripristino.
 */
class SeoStatoController {

	/**
	 * Registra la rotta REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/seo/stato',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => array( self::class, 'permission' ),
			)
		);
	}

	/**
	 * Solo chi gestisce il negozio puo' leggere questo endpoint.
	 */
	public static function permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Stato SEO di tutti i prodotti pubblicati o in bozza.
	 */
	public static function handle(): WP_REST_Response {
		$prodotti = wc_get_products(
			array(
				'status'  => array( 'publish', 'draft' ),
				'limit'   => -1,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		$risultato = array();
		foreach ( $prodotti as $prodotto ) {
			$titolo_seo          = get_post_meta( $prodotto->get_id(), '_yoast_wpseo_title', true );
			$meta_description    = get_post_meta( $prodotto->get_id(), '_yoast_wpseo_metadesc', true );
			$parola_chiave_focus = get_post_meta( $prodotto->get_id(), '_yoast_wpseo_focuskw', true );
			$creato              = $prodotto->get_date_created();

			$risultato[] = array(
				'id'                         => $prodotto->get_id(),
				'titolo'                     => $prodotto->get_name(),
				'stato'                      => $prodotto->get_status(),
				'data_creazione'             => $creato ? $creato->date( 'c' ) : null,
				'titolo_seo'                 => '' !== $titolo_seo ? $titolo_seo : null,
				'meta_description'           => '' !== $meta_description ? $meta_description : null,
				'parola_chiave_focus'        => '' !== $parola_chiave_focus ? $parola_chiave_focus : null,
				'lunghezza_titolo_seo'       => strlen( (string) $titolo_seo ),
				'lunghezza_meta_description' => strlen( (string) $meta_description ),
				'versioni'                   => SeoSnapshot::per_risposta(
					get_post_meta( $prodotto->get_id(), SeoSnapshot::META_KEY, true ),
					array(
						'titolo_seo'          => (string) $titolo_seo,
						'meta_description'    => (string) $meta_description,
						'parola_chiave_focus' => (string) $parola_chiave_focus,
					)
				),
			);
		}

		return new WP_REST_Response( $risultato, 200 );
	}
}
