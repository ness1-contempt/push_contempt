<?php
/**
 * Endpoint REST per la SEO delle varianti di un prodotto variabile.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use PushContempt\AnalisiVarianti;
use PushContempt\PrezzoProdotto;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET legge attributi, varianti (prezzo, SKU, immagine, descrizione) e il
 * giudizio di `AnalisiVarianti`; POST riscrive descrizione e SKU delle
 * varianti indicate. Una variante non ha un URL suo: i testi servono alla
 * pagina del padre e ai dati strutturati (vedi `DatiStrutturatiVarianti`).
 *
 * Si toccano solo varianti del prodotto indicato: se una non lo e', o se uno
 * SKU e' gia' di un altro prodotto, la richiesta e' rifiutata e non si
 * scrive niente. L'ALT delle immagini delle varianti si cambia con
 * `/alt/prodotto`, che le elenca con ruolo "variante".
 */
class SeoVariantiController {

	/**
	 * Registra le rotte REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/seo/varianti',
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
						'id'             => array(
							'type'     => 'integer',
							'required' => true,
						),
						'varianti'       => array(
							'type'     => 'array',
							'required' => true,
							'items'    => array(
								'type'       => 'object',
								'properties' => array(
									'id'          => array( 'type' => 'integer' ),
									'descrizione' => array( 'type' => 'string' ),
									'sku'         => array( 'type' => 'string' ),
								),
								'required'   => array( 'id' ),
							),
						),
						'solo_anteprima' => array(
							'type'    => 'boolean',
							'default' => false,
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
	 * Il prodotto variabile con quell'id, o un errore.
	 *
	 * @param int $prodotto_id Id del prodotto.
	 *
	 * @return \WC_Product_Variable|WP_Error
	 */
	private static function trova_variabile( int $prodotto_id ) {
		$prodotto = wc_get_product( $prodotto_id );
		if ( ! $prodotto || $prodotto->is_type( 'variation' ) ) {
			return new WP_Error(
				'push_contempt_prodotto_non_trovato',
				"Nessun prodotto con id {$prodotto_id}",
				array( 'status' => 404 )
			);
		}
		if ( ! $prodotto instanceof \WC_Product_Variable ) {
			return new WP_Error(
				'push_contempt_prodotto_non_variabile',
				"Il prodotto {$prodotto_id} e' di tipo {$prodotto->get_type()}, non variabile: non ha varianti.",
				array( 'status' => 400 )
			);
		}
		return $prodotto;
	}

	/**
	 * Valori di un attributo del padre (nomi dei termini se e' globale).
	 *
	 * @param \WC_Product_Attribute $attributo Attributo.
	 *
	 * @return string[]
	 */
	private static function valori_attributo( \WC_Product_Attribute $attributo ): array {
		if ( $attributo->is_taxonomy() ) {
			return array_map(
				static fn( $termine ): string => (string) $termine->name,
				(array) $attributo->get_terms()
			);
		}
		return array_map( 'strval', $attributo->get_options() );
	}

	/**
	 * Gli attributi usati per le varianti: etichetta, valori, se si vedono nella scheda.
	 *
	 * @param \WC_Product_Variable $prodotto Il padre.
	 *
	 * @return array<string, array{valori: string[], visibile: bool}>
	 */
	private static function attributi( \WC_Product_Variable $prodotto ): array {
		$attributi = array();
		foreach ( $prodotto->get_attributes() as $attributo ) {
			if ( ! $attributo->get_variation() ) {
				continue;
			}
			$attributi[ wc_attribute_label( $attributo->get_name(), $prodotto ) ] = array(
				'valori'   => self::valori_attributo( $attributo ),
				'visibile' => (bool) $attributo->get_visible(),
			);
		}
		return $attributi;
	}

	/**
	 * Attributi di una variante: etichetta => valore ("" se "qualsiasi").
	 *
	 * @param \WC_Product_Variation $variante Variante.
	 * @param \WC_Product_Variable  $padre    Il padre.
	 *
	 * @return array<string, string>
	 */
	private static function attributi_variante( \WC_Product_Variation $variante, \WC_Product_Variable $padre ): array {
		$risultato = array();
		foreach ( $variante->get_attributes() as $chiave => $valore ) {
			$valore = (string) $valore;
			if ( '' !== $valore && taxonomy_exists( $chiave ) ) {
				$termine = get_term_by( 'slug', $valore, $chiave );
				$valore  = $termine ? (string) $termine->name : $valore;
			}
			$risultato[ wc_attribute_label( $chiave, $padre ) ] = $valore;
		}
		return $risultato;
	}

	/**
	 * Le varianti del padre, ciascuna con i campi che servono alla SEO.
	 *
	 * @param \WC_Product_Variable $prodotto Il padre.
	 */
	private static function varianti( \WC_Product_Variable $prodotto ): array {
		$righe = array();
		foreach ( $prodotto->get_children() as $variante_id ) {
			$variante = wc_get_product( $variante_id );
			if ( ! $variante instanceof \WC_Product_Variation ) {
				continue;
			}
			$immagine_id = (int) $variante->get_image_id( 'edit' );
			$righe[]     = array(
				'id'               => (int) $variante_id,
				'stato'            => $variante->get_status(),
				'etichetta'        => wc_get_formatted_variation( $variante, true ),
				'attributi'        => self::attributi_variante( $variante, $prodotto ),
				'prezzo'           => (string) $variante->get_price(),
				'in_magazzino'     => $variante->is_in_stock(),
				'sku'              => (string) $variante->get_sku( 'edit' ),
				'descrizione'      => (string) $variante->get_description( 'edit' ),
				'immagine_id'      => $immagine_id ? $immagine_id : null,
				'immagine_propria' => (bool) $immagine_id && $immagine_id !== (int) $prodotto->get_image_id(),
				'alt_immagine'     => $immagine_id ? (string) get_post_meta( $immagine_id, AltImmaginiController::META_ALT, true ) : null,
			);
		}
		return $righe;
	}

	/**
	 * Letture SEO del variabile: dati, varianti e problemi trovati.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_leggi( WP_REST_Request $request ) {
		$prodotto_id = (int) $request->get_param( 'id' );
		$prodotto    = self::trova_variabile( $prodotto_id );
		if ( is_wp_error( $prodotto ) ) {
			return $prodotto;
		}
		return new WP_REST_Response( self::lettura( $prodotto ), 200 );
	}

	/**
	 * Lettura completa del variabile (condivisa con la vista d'insieme).
	 *
	 * @param \WC_Product_Variable $prodotto Il padre.
	 */
	public static function lettura( \WC_Product_Variable $prodotto ): array {
		$id            = $prodotto->get_id();
		$titolo_seo    = (string) get_post_meta( $id, '_yoast_wpseo_title', true );
		$meta          = (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true );
		$parola_chiave = (string) get_post_meta( $id, '_yoast_wpseo_focuskw', true );
		$attributi     = self::attributi( $prodotto );
		$varianti      = self::varianti( $prodotto );

		$analisi = AnalisiVarianti::analizza(
			array(
				'attributi'     => $attributi,
				'testo'         => trim( wp_strip_all_tags( $prodotto->get_description() . ' ' . $prodotto->get_short_description() ) ),
				'titolo_seo'    => $titolo_seo,
				'parola_chiave' => $parola_chiave,
			),
			$varianti
		);

		$elenco_attributi = array();
		foreach ( $attributi as $etichetta => $attributo ) {
			$elenco_attributi[] = array_merge( array( 'nome' => $etichetta ), $attributo );
		}

		return array_merge(
			array(
				'id'                  => $id,
				'titolo'              => $prodotto->get_name(),
				'stato'               => $prodotto->get_status(),
				'titolo_seo'          => '' !== $titolo_seo ? $titolo_seo : null,
				'meta_description'    => '' !== $meta ? $meta : null,
				'parola_chiave_focus' => '' !== $parola_chiave ? $parola_chiave : null,
				'attributi'           => $elenco_attributi,
				'varianti'            => $varianti,
			),
			PrezzoProdotto::di( $prodotto ),
			$analisi
		);
	}

	/**
	 * Riscrive descrizione e SKU delle varianti indicate.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_scrivi( WP_REST_Request $request ) {
		$prodotto_id = (int) $request->get_param( 'id' );
		$prodotto    = self::trova_variabile( $prodotto_id );
		if ( is_wp_error( $prodotto ) ) {
			return $prodotto;
		}

		$figli     = array_map( 'intval', $prodotto->get_children() );
		$richieste = array();
		$estranee  = array();
		foreach ( (array) $request->get_param( 'varianti' ) as $voce ) {
			$variante_id = (int) $voce['id'];
			if ( ! in_array( $variante_id, $figli, true ) ) {
				$estranee[] = $variante_id;
				continue;
			}
			$richieste[ $variante_id ] = $voce;
		}
		if ( $estranee ) {
			return new WP_Error(
				'push_contempt_variante_non_del_prodotto',
				'Le varianti ' . implode( ', ', $estranee ) . " non appartengono al prodotto {$prodotto_id}: non e' stato scritto niente.",
				array( 'status' => 400 )
			);
		}

		$conflitti = self::conflitti_sku( $richieste );
		if ( $conflitti ) {
			return new WP_Error(
				'push_contempt_sku_in_uso',
				'SKU gia\' in uso: ' . implode( '; ', $conflitti ) . ". Non e' stato scritto niente.",
				array( 'status' => 409 )
			);
		}

		$anteprima = (bool) $request->get_param( 'solo_anteprima' );
		$esiti     = array();
		foreach ( $richieste as $variante_id => $voce ) {
			$variante = wc_get_product( $variante_id );
			$esito    = array(
				'id'        => $variante_id,
				'etichetta' => wc_get_formatted_variation( $variante, true ),
			);
			if ( isset( $voce['descrizione'] ) ) {
				$nuova                      = wp_kses_post( (string) $voce['descrizione'] );
				$esito['descrizione_prima'] = (string) $variante->get_description( 'edit' );
				$esito['descrizione_dopo']  = $nuova;
				$variante->set_description( $nuova );
			}
			if ( isset( $voce['sku'] ) ) {
				$nuovo              = trim( (string) $voce['sku'] );
				$esito['sku_prima'] = (string) $variante->get_sku( 'edit' );
				$esito['sku_dopo']  = $nuovo;
				$variante->set_sku( $nuovo );
			}
			if ( ! $anteprima ) {
				$variante->save();
			}
			$esiti[] = $esito;
		}

		if ( ! $anteprima ) {
			// La scheda in cache mostra ancora i vecchi testi delle varianti.
			do_action( 'litespeed_purge_post', $prodotto_id );
		}

		return new WP_REST_Response(
			array(
				'id'             => $prodotto_id,
				'solo_anteprima' => $anteprima,
				'varianti'       => $esiti,
			),
			200
		);
	}

	/**
	 * SKU richiesti che sono gia' di un altro prodotto o ripetuti nella richiesta.
	 *
	 * @param array $richieste Variante id => voce della richiesta.
	 *
	 * @return string[] Messaggi, uno per conflitto.
	 */
	private static function conflitti_sku( array $richieste ): array {
		$conflitti = array();
		$visti     = array();
		foreach ( $richieste as $variante_id => $voce ) {
			if ( ! isset( $voce['sku'] ) || '' === trim( (string) $voce['sku'] ) ) {
				continue;
			}
			$sku = trim( (string) $voce['sku'] );
			if ( isset( $visti[ strtolower( $sku ) ] ) ) {
				$conflitti[] = "\"{$sku}\" e' dato sia alla variante {$visti[ strtolower( $sku ) ]} sia alla {$variante_id}";
				continue;
			}
			$visti[ strtolower( $sku ) ] = $variante_id;

			$altro = (int) wc_get_product_id_by_sku( $sku );
			if ( $altro && $altro !== (int) $variante_id ) {
				$conflitti[] = "\"{$sku}\" e' gia' del prodotto {$altro}";
			}
		}
		return $conflitti;
	}
}
