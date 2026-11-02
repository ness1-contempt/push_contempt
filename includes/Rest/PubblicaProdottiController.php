<?php
/**
 * Endpoint REST per pubblicare bozze di prodotti, con anteprima e conferma.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use PushContempt\ConfermaAzione;
use PushContempt\PrezzoProdotto;
use PushContempt\ValutaBozza;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Pubblicare rende il prodotto visibile a tutti, quindi si fa in due tempi:
 * con `solo_anteprima` (il predefinito) non cambia niente, elenca cosa verrebbe
 * pubblicato e cosa no (con i motivi) e restituisce un `codice_conferma`; la
 * pubblicazione vera richiede quel codice (vedi ConfermaAzione) ed e' rifiutata
 * se anche solo uno dei prodotti, o i suoi campi SEO, sono cambiati dopo
 * l'anteprima. Si pubblicano solo bozze (ValutaBozza): niente prezzo o varianti
 * sbagliate le bloccano, e restano da sistemare in wp-admin.
 */
class PubblicaProdottiController {

	const AZIONE = 'pubblica';

	/**
	 * Quanti prodotti al massimo per richiesta.
	 */
	const MAX_PRODOTTI = 50;

	/**
	 * Registra la rotta REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/prodotti/pubblica',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'ids'             => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'integer' ),
					),
					'solo_anteprima'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'codice_conferma' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Solo chi gestisce il negozio puo' pubblicare.
	 */
	public static function permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Anteprima o pubblicazione.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle( WP_REST_Request $request ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $request->get_param( 'ids' ) ) ) ) );
		if ( ! $ids ) {
			return new WP_Error( 'push_contempt_nessun_prodotto', 'Indica almeno un prodotto.', array( 'status' => 400 ) );
		}
		if ( count( $ids ) > self::MAX_PRODOTTI ) {
			return new WP_Error( 'push_contempt_troppi_prodotti', 'Al massimo ' . self::MAX_PRODOTTI . ' prodotti per volta.', array( 'status' => 400 ) );
		}

		$da_pubblicare    = array();
		$non_pubblicabili = array();
		$non_trovati      = array();
		$stato            = array();
		foreach ( $ids as $id ) {
			$prodotto = wc_get_product( $id );
			if ( ! $prodotto || $prodotto->is_type( 'variation' ) ) {
				$non_trovati[] = $id;
				continue;
			}
			$giudizio = ValutaBozza::valuta( self::dati( $prodotto ) );
			if ( $giudizio['bloccanti'] ) {
				$non_pubblicabili[] = array(
					'id'     => $id,
					'titolo' => $prodotto->get_name(),
					'stato'  => $prodotto->get_status(),
					'motivi' => $giudizio['bloccanti'],
				);
				continue;
			}
			$da_pubblicare[ $id ] = array_merge(
				array(
					'id'        => $id,
					'titolo'    => $prodotto->get_name(),
					'categorie' => wp_list_pluck( (array) wp_get_post_terms( $id, 'product_cat' ), 'name' ),
					'avvisi'    => $giudizio['avvisi'],
				),
				PrezzoProdotto::di( $prodotto )
			);
			$stato[]              = $id . '@' . self::impronta( $prodotto, $giudizio['avvisi'] );
		}
		$segreto = wp_salt( 'auth' );

		if ( $request->get_param( 'solo_anteprima' ) ) {
			return new WP_REST_Response(
				array(
					'solo_anteprima'   => true,
					'da_pubblicare'    => array_values( $da_pubblicare ),
					'non_pubblicabili' => $non_pubblicabili,
					'non_trovati'      => $non_trovati,
					'codice_conferma'  => $da_pubblicare ? ConfermaAzione::codice( self::AZIONE, $stato, $segreto ) : null,
				),
				200
			);
		}

		if ( ! $da_pubblicare ) {
			return new WP_Error( 'push_contempt_niente_da_pubblicare', 'Nessuno dei prodotti indicati e\' pubblicabile.', array( 'status' => 400 ) );
		}
		if ( ! ConfermaAzione::valido( (string) $request->get_param( 'codice_conferma' ), self::AZIONE, $stato, $segreto ) ) {
			return new WP_Error(
				'push_contempt_conferma_non_valida',
				"Manca il codice di conferma dell'anteprima, o almeno un prodotto e' cambiato dopo: rifai l'anteprima, falla vedere all'utente e usa il nuovo codice. Non e' stato pubblicato niente.",
				array( 'status' => 409 )
			);
		}

		$pubblicati = array();
		$falliti    = array();
		foreach ( array_keys( $da_pubblicare ) as $id ) {
			$prodotto = wc_get_product( $id );
			try {
				$prodotto->set_status( 'publish' );
				$prodotto->save();
			} catch ( \Exception $errore ) {
				$falliti[] = array(
					'id'     => $id,
					'titolo' => $da_pubblicare[ $id ]['titolo'],
					'motivo' => $errore->getMessage(),
				);
				continue;
			}
			if ( 'publish' !== get_post_status( $id ) ) {
				// Per esempio una data nel futuro: WordPress lo mette in programmazione, non lo pubblica.
				$falliti[] = array(
					'id'     => $id,
					'titolo' => $da_pubblicare[ $id ]['titolo'],
					'motivo' => 'Dopo il salvataggio lo stato e\' "' . get_post_status( $id ) . '", non "publish".',
				);
				continue;
			}
			do_action( 'litespeed_purge_post', $id );
			$pubblicati[] = array(
				'id'     => $id,
				'titolo' => $da_pubblicare[ $id ]['titolo'],
				'url'    => get_permalink( $id ),
			);
		}

		return new WP_REST_Response(
			array(
				'solo_anteprima'   => false,
				'pubblicati'       => $pubblicati,
				'falliti'          => $falliti,
				'non_pubblicabili' => $non_pubblicabili,
				'non_trovati'      => $non_trovati,
			),
			200
		);
	}

	/**
	 * Un'impronta di cio' che l'utente vede nell'anteprima: se uno di questi campi cambia, il codice non vale piu'.
	 * Non basta la data di modifica (ha la precisione di un secondo, e i campi SEO non la toccano).
	 *
	 * @param \WC_Product $prodotto Il prodotto.
	 * @param string[]    $avvisi   Avvisi mostrati.
	 */
	private static function impronta( \WC_Product $prodotto, array $avvisi ): string {
		return md5(
			(string) wp_json_encode(
				array(
					$prodotto->get_name(),
					$prodotto->get_status(),
					$prodotto->get_description( 'edit' ),
					$prodotto->get_short_description( 'edit' ),
					$prodotto->get_regular_price( 'edit' ),
					$prodotto->get_sale_price( 'edit' ),
					$prodotto->get_image_id( 'edit' ),
					$prodotto->get_category_ids( 'edit' ),
					$avvisi,
				)
			)
		);
	}

	/**
	 * I dati di un prodotto nel formato di ValutaBozza.
	 *
	 * @param \WC_Product $prodotto Il prodotto.
	 */
	private static function dati( \WC_Product $prodotto ): array {
		$id          = $prodotto->get_id();
		$prezzo      = PrezzoProdotto::di( $prodotto );
		$categorie   = array_map( 'intval', (array) wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'ids' ) ) );
		$predefinita = (int) get_option( 'default_product_cat' );

		$errori_varianti = array();
		if ( $prodotto instanceof \WC_Product_Variable ) {
			foreach ( SeoVariantiController::lettura( $prodotto )['problemi'] as $problema ) {
				if ( 'errore' === $problema['gravita'] ) {
					$errori_varianti[] = $problema['messaggio'];
				}
			}
		}

		return array(
			'stato'                      => $prodotto->get_status(),
			'titolo'                     => $prodotto->get_name(),
			'prezzo_min'                 => $prezzo['prezzo_min'],
			'immagine_evidenza'          => (bool) $prodotto->get_image_id(),
			'titolo_seo'                 => (string) get_post_meta( $id, '_yoast_wpseo_title', true ),
			'meta_description'           => (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true ),
			'parola_chiave'              => (string) get_post_meta( $id, '_yoast_wpseo_focuskw', true ),
			'solo_categoria_predefinita' => ! $categorie || array( $predefinita ) === $categorie,
			'errori_varianti'            => $errori_varianti,
		);
	}
}
