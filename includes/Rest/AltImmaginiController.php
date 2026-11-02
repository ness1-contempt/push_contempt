<?php
/**
 * Endpoint REST per il testo ALT delle immagini di un prodotto.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET elenca le immagini del prodotto (in evidenza + galleria) con il loro
 * ALT, POST riscrive l'ALT di quelle indicate. Si toccano solo immagini che
 * appartengono al prodotto: un id di allegato qualsiasi viene rifiutato.
 * L'ALT e' il meta nativo `_wp_attachment_image_alt` dell'allegato.
 */
class AltImmaginiController {

	const META_ALT = '_wp_attachment_image_alt';

	/**
	 * Registra la rotta REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/alt/prodotto',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'handle_leggi' ),
					'permission_callback' => array( self::class, 'permission' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'handle_scrivi' ),
					'permission_callback' => array( self::class, 'permission' ),
					'args'                => array(
						'id'       => array(
							'type'     => 'integer',
							'required' => true,
						),
						'immagini' => array(
							'type'     => 'array',
							'required' => true,
							'items'    => array(
								'type'       => 'object',
								'properties' => array(
									'allegato_id' => array( 'type' => 'integer' ),
									'alt'         => array( 'type' => 'string' ),
								),
								'required'   => array( 'allegato_id', 'alt' ),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Solo chi gestisce il negozio puo' leggere/scrivere questo endpoint.
	 */
	public static function permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Prodotto dato il suo id, o un errore 404 se non esiste.
	 *
	 * @param int $prodotto_id Id del prodotto da cercare.
	 *
	 * @return \WC_Product|WP_Error
	 */
	private static function trova_prodotto( int $prodotto_id ) {
		$prodotto = wc_get_product( $prodotto_id );
		if ( ! $prodotto || $prodotto->is_type( 'variation' ) ) {
			return new WP_Error(
				'push_contempt_prodotto_non_trovato',
				"Nessun prodotto con id {$prodotto_id}",
				array( 'status' => 404 )
			);
		}
		return $prodotto;
	}

	/**
	 * Le immagini del prodotto: id allegato => ruolo ("principale" o "galleria"),
	 * nell'ordine in cui compaiono nella scheda, senza doppioni.
	 *
	 * @param \WC_Product $prodotto Il prodotto.
	 */
	private static function ruoli_immagini( \WC_Product $prodotto ): array {
		$ruoli = array();
		if ( $prodotto->get_image_id() ) {
			$ruoli[ (int) $prodotto->get_image_id() ] = 'principale';
		}
		foreach ( $prodotto->get_gallery_image_ids() as $allegato_id ) {
			if ( ! isset( $ruoli[ (int) $allegato_id ] ) ) {
				$ruoli[ (int) $allegato_id ] = 'galleria';
			}
		}
		return $ruoli;
	}

	/**
	 * ALT di un allegato (stringa vuota se manca).
	 *
	 * @param int $allegato_id Id dell'allegato.
	 */
	private static function alt_di( int $allegato_id ): string {
		return (string) get_post_meta( $allegato_id, self::META_ALT, true );
	}

	/**
	 * Immagine in evidenza e galleria del prodotto, ciascuna con ALT e file.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_leggi( WP_REST_Request $request ) {
		$prodotto_id = (int) $request->get_param( 'id' );
		$prodotto    = self::trova_prodotto( $prodotto_id );
		if ( is_wp_error( $prodotto ) ) {
			return $prodotto;
		}

		$immagini = array();
		foreach ( self::ruoli_immagini( $prodotto ) as $allegato_id => $ruolo ) {
			$immagini[] = array(
				'allegato_id' => $allegato_id,
				'ruolo'       => $ruolo,
				'alt'         => self::alt_di( $allegato_id ),
				'file'        => basename( (string) get_attached_file( $allegato_id ) ),
				'url'         => (string) wp_get_attachment_url( $allegato_id ),
			);
		}

		$parola_chiave = (string) get_post_meta( $prodotto_id, '_yoast_wpseo_focuskw', true );
		return new WP_REST_Response(
			array(
				'id'                  => $prodotto_id,
				'titolo'              => $prodotto->get_name(),
				'parola_chiave_focus' => '' !== $parola_chiave ? $parola_chiave : null,
				'immagini'            => $immagini,
			),
			200
		);
	}

	/**
	 * Riscrive l'ALT delle immagini indicate. Se anche una sola non appartiene
	 * al prodotto la richiesta e' rifiutata (400) e non si scrive niente.
	 * Risposta: per ogni immagine ALT prima e dopo.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_scrivi( WP_REST_Request $request ) {
		$prodotto_id = (int) $request->get_param( 'id' );
		$prodotto    = self::trova_prodotto( $prodotto_id );
		if ( is_wp_error( $prodotto ) ) {
			return $prodotto;
		}

		$ruoli    = self::ruoli_immagini( $prodotto );
		$nuovi    = array();
		$estranee = array();
		foreach ( (array) $request->get_param( 'immagini' ) as $immagine ) {
			$allegato_id = (int) $immagine['allegato_id'];
			if ( ! isset( $ruoli[ $allegato_id ] ) ) {
				$estranee[] = $allegato_id;
				continue;
			}
			$nuovi[ $allegato_id ] = sanitize_text_field( (string) $immagine['alt'] );
		}
		if ( $estranee ) {
			return new WP_Error(
				'push_contempt_immagine_non_del_prodotto',
				'Le immagini ' . implode( ', ', $estranee ) . " non appartengono al prodotto {$prodotto_id}: non e' stato scritto niente.",
				array( 'status' => 400 )
			);
		}

		$esiti = array();
		foreach ( $nuovi as $allegato_id => $alt ) {
			$prima = self::alt_di( $allegato_id );
			if ( '' === $alt ) {
				delete_post_meta( $allegato_id, self::META_ALT );
			} else {
				update_post_meta( $allegato_id, self::META_ALT, $alt );
			}
			$esiti[] = array(
				'allegato_id' => $allegato_id,
				'ruolo'       => $ruoli[ $allegato_id ],
				'prima'       => $prima,
				'dopo'        => self::alt_di( $allegato_id ),
			);
		}

		return new WP_REST_Response(
			array(
				'id'       => $prodotto_id,
				'immagini' => $esiti,
			),
			200
		);
	}
}
