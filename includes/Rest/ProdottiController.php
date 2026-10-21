<?php
/**
 * Endpoint REST per la creazione di nuovi prodotti WooCommerce.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use WC_Product_Simple;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Due rotte: POST /prodotti crea un prodotto semplice in BOZZA (mai
 * pubblicato direttamente: resta da rivedere e pubblicare da wp-admin), GET
 * /prodotti/bozze elenca quelli in attesa di revisione - lo stato "draft" di
 * WooCommerce e' gia' di per se' la lista persistente, non serve
 * tracciamento a parte. Niente SQL diretto: solo API native di WooCommerce
 * e WordPress, cosi' funziona anche dove il DB non e' raggiungibile
 * dall'esterno (hosting condiviso di un cliente).
 */
class ProdottiController {

	/**
	 * Registra entrambe le rotte REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/prodotti',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle_crea' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'titolo'               => array(
						'type'     => 'string',
						'required' => true,
					),
					'descrizione'          => array(
						'type'     => 'string',
						'required' => true,
					),
					'prezzo_regolare'      => array(
						'type'     => 'string',
						'required' => true,
					),
					'categoria'            => array(
						'type'     => 'string',
						'required' => true,
					),
					'immagine_evidenza_id' => array(
						'type' => 'integer',
					),
					'galleria_ids'         => array(
						'type' => 'array',
					),
					'alt_immagini'         => array(
						'type' => 'object',
					),
					'titolo_seo'           => array(
						'type' => 'string',
					),
					'meta_description'     => array(
						'type' => 'string',
					),
					'parola_chiave_focus'  => array(
						'type' => 'string',
					),
				),
			)
		);

		register_rest_route(
			'push-contempt/v1',
			'/prodotti/bozze',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'handle_bozze' ),
				'permission_callback' => array( self::class, 'permission' ),
			)
		);
	}

	/**
	 * Solo chi gestisce il negozio puo' creare prodotti o leggere le bozze.
	 */
	public static function permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Trova la categoria prodotto per nome o la crea se non esiste.
	 *
	 * @param string $nome Nome della categoria.
	 *
	 * @return int|WP_Error
	 */
	private static function trova_o_crea_categoria( string $nome ) {
		$termine = get_term_by( 'name', $nome, 'product_cat' );
		if ( $termine ) {
			return $termine->term_id;
		}

		$risultato = wp_insert_term( $nome, 'product_cat' );
		if ( is_wp_error( $risultato ) ) {
			return $risultato;
		}

		return $risultato['term_id'];
	}

	/**
	 * Crea un prodotto semplice in bozza, con immagini/SEO/ALT gia'
	 * impostati: resta solo da rivedere e pubblicare da wp-admin.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_crea( WP_REST_Request $request ) {
		$categoria_id = self::trova_o_crea_categoria( $request->get_param( 'categoria' ) );
		if ( is_wp_error( $categoria_id ) ) {
			return $categoria_id;
		}

		$prodotto = new WC_Product_Simple();
		$prodotto->set_name( $request->get_param( 'titolo' ) );
		$prodotto->set_description( $request->get_param( 'descrizione' ) );
		$prodotto->set_regular_price( $request->get_param( 'prezzo_regolare' ) );
		$prodotto->set_category_ids( array( $categoria_id ) );
		$prodotto->set_status( 'draft' );

		$immagine_evidenza_id = $request->get_param( 'immagine_evidenza_id' );
		if ( $immagine_evidenza_id ) {
			$prodotto->set_image_id( (int) $immagine_evidenza_id );
		}

		$galleria_ids = $request->get_param( 'galleria_ids' );
		if ( ! empty( $galleria_ids ) ) {
			$prodotto->set_gallery_image_ids( array_map( 'intval', $galleria_ids ) );
		}

		$prodotto->save();
		$prodotto_id = $prodotto->get_id();

		foreach ( (array) $request->get_param( 'alt_immagini' ) as $attachment_id => $alt ) {
			update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}

		$campi_seo = array(
			'titolo_seo'          => '_yoast_wpseo_title',
			'meta_description'    => '_yoast_wpseo_metadesc',
			'parola_chiave_focus' => '_yoast_wpseo_focuskw',
		);
		foreach ( $campi_seo as $parametro => $meta_key ) {
			$valore = $request->get_param( $parametro );
			if ( $valore ) {
				update_post_meta( $prodotto_id, $meta_key, $valore );
			}
		}

		return new WP_REST_Response(
			array(
				'stato'        => 'creato_in_bozza',
				'prodotto_id'  => $prodotto_id,
				'url_modifica' => admin_url( "post.php?post={$prodotto_id}&action=edit" ),
			),
			201
		);
	}

	/**
	 * Prodotti in bozza, piu' recenti prima: promemoria persistente di cosa
	 * resta da rivedere/pubblicare (lo stato "draft" stesso e' la lista,
	 * nessun tracciamento a parte).
	 */
	public static function handle_bozze(): WP_REST_Response {
		$prodotti = wc_get_products(
			array(
				'status'  => 'draft',
				'limit'   => -1,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		$elenco = array();
		foreach ( $prodotti as $prodotto ) {
			$creato   = $prodotto->get_date_created();
			$elenco[] = array(
				'id'     => $prodotto->get_id(),
				'titolo' => $prodotto->get_name(),
				'creato' => $creato ? $creato->date( 'c' ) : null,
			);
		}

		return new WP_REST_Response( $elenco, 200 );
	}
}
