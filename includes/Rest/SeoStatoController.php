<?php
/**
 * Endpoint REST per la vista d'insieme SEO dei prodotti.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use WP_REST_Response;

/**
 * Per ogni prodotto pubblicato: titolo SEO e meta description impostati o
 * mancanti, con le rispettive lunghezze - utile per capire da dove
 * iniziare un'ottimizzazione SEO del catalogo.
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
	 * Stato SEO di tutti i prodotti pubblicati.
	 */
	public static function handle(): WP_REST_Response {
		$prodotti = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => -1,
				'orderby' => 'title',
				'order'   => 'ASC',
			)
		);

		$risultato = array();
		foreach ( $prodotti as $prodotto ) {
			$titolo_seo          = get_post_meta( $prodotto->get_id(), '_yoast_wpseo_title', true );
			$meta_description    = get_post_meta( $prodotto->get_id(), '_yoast_wpseo_metadesc', true );
			$parola_chiave_focus = get_post_meta( $prodotto->get_id(), '_yoast_wpseo_focuskw', true );

			$risultato[] = array(
				'id'                         => $prodotto->get_id(),
				'titolo'                     => $prodotto->get_name(),
				'titolo_seo'                 => '' !== $titolo_seo ? $titolo_seo : null,
				'meta_description'           => '' !== $meta_description ? $meta_description : null,
				'parola_chiave_focus'        => '' !== $parola_chiave_focus ? $parola_chiave_focus : null,
				'lunghezza_titolo_seo'       => strlen( (string) $titolo_seo ),
				'lunghezza_meta_description' => strlen( (string) $meta_description ),
			);
		}

		return new WP_REST_Response( $risultato, 200 );
	}
}
