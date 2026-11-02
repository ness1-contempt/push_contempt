<?php
/**
 * Endpoint REST per la creazione di nuovi prodotti WooCommerce.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use PushContempt\CatalogHealth;
use PushContempt\PercorsoCategorie;
use PushContempt\PrezzoProdotto;
use PushContempt\VariantiProdotto;
use WC_Product_Attribute;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Tre rotte: GET /prodotti elenca il catalogo (titolo, sku, tipo, prezzo o intervallo di prezzo, scorte -
 * usato anche per il confronto nomi di scansiona_cartella_prodotti, cosi'
 * non serve piu' un accesso diretto al DB da push_contempt_mcp), POST
 * /prodotti crea un prodotto semplice o variabile in BOZZA (mai pubblicato
 * direttamente: resta da rivedere e pubblicare da wp-admin), GET /prodotti/bozze elenca
 * quelli in attesa di revisione - lo stato "draft" di WooCommerce e' gia' di
 * per se' la lista persistente, non serve tracciamento a parte. Niente SQL
 * diretto: solo API native di WooCommerce e WordPress, cosi' funziona anche
 * dove il DB non e' raggiungibile dall'esterno (hosting condiviso di un
 * cliente).
 */
class ProdottiController {

	/**
	 * Registra tutte le rotte REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/prodotti',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'handle_elenco' ),
					'permission_callback' => array( self::class, 'permission' ),
					'args'                => array(
						'ricerca' => array(
							'type'    => 'string',
							'default' => '',
						),
						'limite'  => array(
							'type'              => 'integer',
							'default'           => 20,
							'sanitize_callback' => static function ( $valore ) {
								return (int) $valore;
							},
						),
					),
				),
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
							'type' => 'string',
						),
						'percorso_categoria'   => array(
							'type' => 'array',
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
						'tipo'                 => array(
							'type'    => 'string',
							'enum'    => array( 'simple', 'variable' ),
							'default' => 'simple',
						),
						'attributi'            => array(
							'type' => 'array',
						),
						'solo_anteprima'       => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			'push-contempt/v1',
			'/categorie/verifica',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle_verifica_categorie' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'percorsi' => array(
						'type'     => 'array',
						'required' => true,
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
	 * Catalogo filtrato per titolo (sottostringa, case-insensitive) o per
	 * intero con `limite` negativo - usato anche per il confronto nomi di
	 * scansiona_cartella_prodotti lato push_contempt_mcp.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	public static function handle_elenco( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$ricerca = (string) $request->get_param( 'ricerca' );
		$limite  = (int) $request->get_param( 'limite' );
		if ( $limite < 0 ) {
			$limite = PHP_INT_MAX; // "Nessun limite" (usato per il confronto nomi di scansiona_cartella_prodotti).
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ricerca per sottostringa sul titolo, non esprimibile con wc_get_products(); risultato non cacheable (serve sempre fresco).
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"
				SELECT p.ID
				FROM {$wpdb->posts} p
				WHERE p.post_type = 'product' AND p.post_title LIKE %s
				ORDER BY p.post_title
				LIMIT %d
				",
				'%' . $wpdb->esc_like( $ricerca ) . '%',
				$limite
			)
		);

		// Tipo, prezzi e scorte si leggono dall'oggetto prodotto di WooCommerce, non dai meta
		// grezzi: un variabile ha piu' righe `_price` (minimo e massimo), vedi PrezzoProdotto.
		update_meta_cache( 'post', $ids );
		$righe = array();
		foreach ( $ids as $id ) {
			$prodotto = wc_get_product( (int) $id );
			if ( ! $prodotto ) {
				continue;
			}
			$righe[] = array_merge(
				array(
					'id'     => $prodotto->get_id(),
					'titolo' => $prodotto->get_name(),
					'stato'  => $prodotto->get_status(),
					'sku'    => '' !== $prodotto->get_sku() ? $prodotto->get_sku() : null,
				),
				PrezzoProdotto::di( $prodotto ),
				array(
					'scorte'       => $prodotto->get_manage_stock() ? $prodotto->get_stock_quantity() : null,
					'stato_scorte' => $prodotto->get_stock_status(),
				)
			);
		}

		return new WP_REST_Response( $righe, 200 );
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
	 * Crea un prodotto in bozza (semplice o variabile), con immagini/SEO/ALT
	 * gia' impostati: resta solo da rivedere e pubblicare da wp-admin.
	 *
	 * Un variabile ha `attributi` (elenco di `{ nome, valori[] }`) e nasce con una
	 * variante per ogni combinazione dei valori, tutte allo stesso `prezzo_regolare`
	 * e senza gestione scorte. Con `solo_anteprima` non si scrive niente (nemmeno la
	 * categoria): si restituisce cosa verrebbe creato. Tutto viene validato prima
	 * di salvare; se qualcosa fallisce a meta', quello che era stato creato si toglie.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_crea( WP_REST_Request $request ) {
		$variabile = 'variable' === $request->get_param( 'tipo' );
		$prezzo    = (string) $request->get_param( 'prezzo_regolare' );

		$varianti = null;
		if ( $variabile ) {
			$varianti = VariantiProdotto::analizza( $request->get_param( 'attributi' ) );
			$errori   = $varianti['errori'];
			if ( ! CatalogHealth::prezzo_valido( $prezzo ) ) {
				$errori[] = 'Il prezzo unico delle varianti deve essere un numero maggiore di zero, con il punto come decimale.';
			}
			$errori = array_merge( $errori, self::nomi_attributi_in_conflitto( $varianti['attributi'] ) );
			if ( array() !== $errori ) {
				return new WP_Error(
					'push_contempt_varianti_non_valide',
					implode( ' ', $errori ),
					array(
						'status' => 400,
						'errori' => $errori,
					)
				);
			}
		}

		$piano  = null;
		$albero = is_array( $request->get_param( 'percorso_categoria' ) ) && array() !== $request->get_param( 'percorso_categoria' );
		if ( $albero ) {
			$piano = self::piano_categorie( $request->get_param( 'percorso_categoria' ) );
			if ( is_wp_error( $piano ) ) {
				return $piano;
			}
		} elseif ( '' === trim( (string) $request->get_param( 'categoria' ) ) ) {
			return new WP_Error( 'push_contempt_categoria_mancante', 'Serve la categoria (o il percorso delle categorie).', array( 'status' => 400 ) );
		}

		if ( $request->get_param( 'solo_anteprima' ) ) {
			return new WP_REST_Response( self::anteprima( $request, $varianti, $piano ), 200 );
		}

		$categorie_create = array();
		if ( null !== $piano ) {
			$categoria_id = self::crea_categorie_mancanti( $piano['livelli'], $categorie_create );
		} else {
			$categoria_id = self::trova_o_crea_categoria( $request->get_param( 'categoria' ) );
		}
		if ( is_wp_error( $categoria_id ) ) {
			self::togli_categorie( $categorie_create );
			return $categoria_id;
		}

		$prodotto = $variabile ? new WC_Product_Variable() : new WC_Product_Simple();
		$prodotto->set_name( $request->get_param( 'titolo' ) );
		$prodotto->set_description( $request->get_param( 'descrizione' ) );
		$prodotto->set_category_ids( array( $categoria_id ) );
		$prodotto->set_status( 'draft' );
		if ( $variabile ) {
			$prodotto->set_attributes( self::attributi_woocommerce( $varianti['attributi'] ) );
		} else {
			$prodotto->set_regular_price( $prezzo );
		}

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

		if ( $variabile ) {
			try {
				self::crea_varianti( $prodotto_id, $varianti['combinazioni'], $prezzo );
			} catch ( \Throwable $errore ) {
				$prodotto->delete( true ); // Toglie anche le varianti gia' create.
				self::togli_categorie( $categorie_create );
				return new WP_Error(
					'push_contempt_creazione_varianti_fallita',
					'Creazione delle varianti non riuscita, non e\' stato creato niente: ' . $errore->getMessage(),
					array( 'status' => 500 )
				);
			}
		}

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
			array_merge(
				array(
					'stato'            => 'creato_in_bozza',
					'prodotto_id'      => $prodotto_id,
					'url_modifica'     => admin_url( "post.php?post={$prodotto_id}&action=edit" ),
					'categorie_create' => array_column(
						array_filter(
							null !== $piano ? $piano['livelli'] : array(),
							static fn( $livello ) => 'da_creare' === $livello['stato']
						),
						'percorso'
					),
				),
				PrezzoProdotto::di( wc_get_product( $prodotto_id ) )
			),
			201
		);
	}

	/**
	 * Le categorie prodotto esistenti come `{ id, nome, madre }`.
	 *
	 * @return array[]
	 */
	private static function categorie_esistenti(): array {
		$termini = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 0,
			)
		);
		$elenco  = array();
		if ( is_wp_error( $termini ) ) {
			return $elenco;
		}
		foreach ( $termini as $termine ) {
			$elenco[] = array(
				'id'    => (int) $termine->term_id,
				'nome'  => wp_specialchars_decode( $termine->name, ENT_QUOTES ),
				'madre' => (int) $termine->parent,
			);
		}
		return $elenco;
	}

	/**
	 * Il confronto fra un percorso di categorie e l'albero del sito, o un errore se la forma
	 * non va (400) o se c'e' un conflitto di nomi (409).
	 *
	 * @param mixed $percorso Elenco di nomi, dalla radice alla categoria del prodotto.
	 *
	 * @return array|WP_Error `{ livelli, errori }` senza errori, altrimenti l'errore.
	 */
	private static function piano_categorie( $percorso ) {
		$pulito = PercorsoCategorie::pulisci( $percorso );
		if ( array() !== $pulito['errori'] ) {
			return new WP_Error(
				'push_contempt_percorso_categoria_non_valido',
				implode( ' ', $pulito['errori'] ),
				array(
					'status' => 400,
					'errori' => $pulito['errori'],
				)
			);
		}
		$piano = PercorsoCategorie::risolvi( $pulito['percorso'], self::categorie_esistenti() );
		if ( array() !== $piano['errori'] ) {
			return new WP_Error(
				'push_contempt_categoria_in_conflitto',
				implode( ' ', $piano['errori'] ),
				array(
					'status'  => 409,
					'errori'  => $piano['errori'],
					'livelli' => $piano['livelli'],
				)
			);
		}
		return $piano;
	}

	/**
	 * Crea le categorie del percorso che mancano, dall'alto verso il basso, ciascuna sotto la sua madre.
	 *
	 * @param array $livelli  Livelli del piano (esiste / da_creare).
	 * @param array $create   Id delle categorie create, aggiornato per riferimento (serve ad annullarle).
	 *
	 * @return int|WP_Error Id della categoria piu' in basso, o l'errore.
	 */
	private static function crea_categorie_mancanti( array $livelli, array &$create ) {
		$madre = 0;
		foreach ( $livelli as $livello ) {
			if ( 'esiste' === $livello['stato'] ) {
				$madre = (int) $livello['id'];
				continue;
			}
			$risultato = wp_insert_term( $livello['nome'], 'product_cat', array( 'parent' => $madre ) );
			if ( is_wp_error( $risultato ) ) {
				return $risultato;
			}
			$madre    = (int) $risultato['term_id'];
			$create[] = $madre;
		}
		return $madre;
	}

	/**
	 * Toglie le categorie create in questa richiesta (dalla piu' in basso), se poi la creazione fallisce.
	 *
	 * @param int[] $create Id delle categorie create.
	 */
	private static function togli_categorie( array $create ): void {
		foreach ( array_reverse( $create ) as $id ) {
			wp_delete_term( (int) $id, 'product_cat' );
		}
	}

	/**
	 * Per ogni percorso di categorie cosa si farebbe, piu' i nomi che compaiono in
	 * punti diversi delle cartelle. Sola lettura: non crea niente.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	public static function handle_verifica_categorie( WP_REST_Request $request ): WP_REST_Response {
		$percorsi  = (array) $request->get_param( 'percorsi' );
		$esistenti = self::categorie_esistenti();
		$risultati = array();
		$validi    = array();
		foreach ( $percorsi as $percorso ) {
			$pulito = PercorsoCategorie::pulisci( $percorso );
			if ( array() !== $pulito['errori'] ) {
				$risultati[] = array(
					'percorso' => (array) $percorso,
					'livelli'  => array(),
					'errori'   => $pulito['errori'],
				);
				continue;
			}
			$validi[]    = $pulito['percorso'];
			$piano       = PercorsoCategorie::risolvi( $pulito['percorso'], $esistenti );
			$risultati[] = array(
				'percorso' => $pulito['percorso'],
				'livelli'  => $piano['livelli'],
				'errori'   => $piano['errori'],
			);
		}

		return new WP_REST_Response(
			array(
				'percorsi'               => $risultati,
				'conflitti_tra_percorsi' => PercorsoCategorie::conflitti_tra_percorsi( $validi ),
				'conflitti'              => PercorsoCategorie::conflitti_dettaglio( $validi ),
			),
			200
		);
	}

	/**
	 * Cosa verrebbe creato, senza scrivere niente.
	 *
	 * @param WP_REST_Request $request  Richiesta REST in arrivo.
	 * @param array|null      $varianti Esito di VariantiProdotto::analizza (null per un semplice).
	 * @param array|null      $piano    Confronto del percorso di categorie con l'albero (null se si usa la categoria singola).
	 */
	private static function anteprima( WP_REST_Request $request, ?array $varianti, ?array $piano ): array {
		$prezzo    = (string) $request->get_param( 'prezzo_regolare' );
		$categoria = (string) $request->get_param( 'categoria' );
		$esito     = array(
			'stato'           => 'anteprima',
			'tipo'            => (string) $request->get_param( 'tipo' ),
			'titolo'          => (string) $request->get_param( 'titolo' ),
			'categoria'       => $categoria,
			'categoria_nuova' => null !== $piano
				? in_array( 'da_creare', array_column( $piano['livelli'], 'stato' ), true )
				: ! get_term_by( 'name', $categoria, 'product_cat' ),
			'prezzo_regolare' => $prezzo,
			'in_bozza'        => true,
		);
		if ( null !== $piano ) {
			$esito['categorie'] = $piano['livelli'];
		}
		if ( null === $varianti ) {
			return $esito;
		}

		$esito['attributi']       = $varianti['attributi'];
		$esito['numero_varianti'] = count( $varianti['combinazioni'] );
		$esito['scorte']          = 'non gestite';
		$esito['varianti']        = array_map(
			static function ( array $valori ) use ( $prezzo ): array {
				return array(
					'valori' => $valori,
					'prezzo' => $prezzo,
				);
			},
			$varianti['combinazioni']
		);
		return $esito;
	}

	/**
	 * Errori se due attributi, una volta resi "slug" da WordPress, coincidono
	 * (per esempio "Memoria (GB)" e "Memoria GB"): le varianti non li distinguerebbero.
	 *
	 * @param array $attributi Attributi validati.
	 *
	 * @return string[]
	 */
	private static function nomi_attributi_in_conflitto( array $attributi ): array {
		$errori = array();
		$visti  = array();
		foreach ( $attributi as $attributo ) {
			$slug = sanitize_title( $attributo['nome'] );
			if ( '' === $slug ) {
				$errori[] = "Il nome dell'attributo «{$attributo['nome']}» non contiene lettere o numeri.";
			} elseif ( isset( $visti[ $slug ] ) ) {
				$errori[] = "Gli attributi «{$visti[ $slug ]}» e «{$attributo['nome']}» hanno lo stesso identificativo ({$slug}).";
			}
			$visti[ $slug ] = $attributo['nome'];
		}
		return $errori;
	}

	/**
	 * Gli attributi nel formato di WooCommerce: propri del prodotto, usati per le varianti.
	 *
	 * @param array $attributi Attributi validati: elenco di `{ nome, valori[] }`.
	 *
	 * @return WC_Product_Attribute[]
	 */
	private static function attributi_woocommerce( array $attributi ): array {
		$risultato = array();
		foreach ( array_values( $attributi ) as $posizione => $attributo ) {
			$oggetto = new WC_Product_Attribute();
			$oggetto->set_id( 0 ); // 0 = attributo del prodotto, non globale.
			$oggetto->set_name( $attributo['nome'] );
			$oggetto->set_options( $attributo['valori'] );
			$oggetto->set_position( $posizione );
			$oggetto->set_visible( true );
			$oggetto->set_variation( true );
			$risultato[] = $oggetto;
		}
		return $risultato;
	}

	/**
	 * Una variante per ogni combinazione, tutte allo stesso prezzo e senza gestione
	 * scorte, poi la sincronizzazione del padre (prezzo minimo/massimo, scorte).
	 *
	 * @param int    $prodotto_id  Id del prodotto padre.
	 * @param array  $combinazioni Per ogni variante: nome attributo => valore.
	 * @param string $prezzo       Prezzo regolare di tutte le varianti.
	 */
	private static function crea_varianti( int $prodotto_id, array $combinazioni, string $prezzo ): void {
		foreach ( $combinazioni as $valori ) {
			$attributi = array();
			foreach ( $valori as $nome => $valore ) {
				$attributi[ sanitize_title( $nome ) ] = $valore;
			}
			$variante = new WC_Product_Variation();
			$variante->set_parent_id( $prodotto_id );
			$variante->set_attributes( $attributi );
			$variante->set_regular_price( $prezzo );
			$variante->set_manage_stock( false );
			$variante->set_status( 'publish' );
			$variante->save();
		}
		WC_Product_Variable::sync( $prodotto_id );
		wc_delete_product_transients( $prodotto_id );
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
			$elenco[] = array_merge(
				array(
					'id'     => $prodotto->get_id(),
					'titolo' => $prodotto->get_name(),
					'creato' => $creato ? $creato->date( 'c' ) : null,
				),
				array_intersect_key( PrezzoProdotto::di( $prodotto ), array_flip( array( 'tipo', 'numero_varianti' ) ) )
			);
		}

		return new WP_REST_Response( $elenco, 200 );
	}
}
