<?php
/**
 * Endpoint REST del blog: brief, contesto del negozio e articoli in bozza.
 *
 * @package PushContempt
 */

namespace PushContempt\Rest;

use PushContempt\AnalisiLink;
use PushContempt\BriefBlog;
use PushContempt\PrezzoProdotto;
use PushContempt\TestoBlog;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Il plugin non scrive niente con l'AI: offre all'assistente del cliente il materiale
 * (brief e contesto del negozio) e un posto dove lasciare gli articoli, sempre in BOZZA.
 * Pubblicare resta un gesto umano da wp-admin.
 *
 * Rotte:
 * - GET/POST /blog/brief: le indicazioni del negozio (argomento, pubblico, tono, ...);
 * - GET /blog/contesto: negozio, brief, prodotti piu' venduti e articoli gia' scritti
 *   (per linkare prodotti veri e non ripetere argomenti);
 * - GET/POST /blog/articoli: elenco e creazione di una bozza;
 * - GET/POST /blog/articoli/<id>: lettura e modifica di una bozza (un articolo gia'
 *   pubblicato non si tocca da qui).
 *
 * Niente SQL diretto: solo API native di WordPress e WooCommerce.
 */
class BlogController {

	const META_PRODOTTI = '_pc_blog_prodotti';

	const MAX_PRODOTTI_CONTESTO = 40;
	const MAX_ARTICOLI_CONTESTO = 200;

	/**
	 * Registra tutte le rotte REST (chiamato su rest_api_init).
	 */
	public static function register(): void {
		register_rest_route(
			'push-contempt/v1',
			'/blog/brief',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'handle_brief' ),
					'permission_callback' => array( self::class, 'permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'handle_salva_brief' ),
					'permission_callback' => array( self::class, 'permission' ),
				),
			)
		);

		register_rest_route(
			'push-contempt/v1',
			'/blog/contesto',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'handle_contesto' ),
				'permission_callback' => array( self::class, 'permission' ),
			)
		);

		register_rest_route(
			'push-contempt/v1',
			'/blog/articoli',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'handle_elenco' ),
					'permission_callback' => array( self::class, 'permission' ),
					'args'                => array(
						'stato'   => array(
							'type'    => 'string',
							'enum'    => array( 'draft', 'publish', 'any' ),
							'default' => 'any',
						),
						'ricerca' => array(
							'type'    => 'string',
							'default' => '',
						),
						'limite'  => array(
							'type'              => 'integer',
							'default'           => 50,
							'sanitize_callback' => static function ( $valore ) {
								return max( 1, min( 200, (int) $valore ) );
							},
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'handle_crea' ),
					'permission_callback' => array( self::class, 'permission' ),
					'args'                => self::argomenti_articolo( true ),
				),
			)
		);

		register_rest_route(
			'push-contempt/v1',
			'/blog/articoli/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'handle_leggi' ),
					'permission_callback' => array( self::class, 'permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'handle_aggiorna' ),
					'permission_callback' => array( self::class, 'permission' ),
					'args'                => self::argomenti_articolo( false ),
				),
			)
		);
	}

	/**
	 * Gli argomenti accettati da creazione e modifica di un articolo.
	 *
	 * @param bool $creazione Se true titolo e contenuto sono obbligatori.
	 */
	private static function argomenti_articolo( bool $creazione ): array {
		return array(
			'titolo'               => array(
				'type'     => 'string',
				'required' => $creazione,
			),
			'contenuto'            => array(
				'type'     => 'string',
				'required' => $creazione,
			),
			'estratto'             => array( 'type' => 'string' ),
			'categoria'            => array( 'type' => 'string' ),
			'tag'                  => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'immagine_evidenza_id' => array( 'type' => 'integer' ),
			'alt_immagini'         => array( 'type' => 'object' ),
			'titolo_seo'           => array( 'type' => 'string' ),
			'meta_description'     => array( 'type' => 'string' ),
			'parola_chiave_focus'  => array( 'type' => 'string' ),
			'prodotti_ids'         => array(
				'type'  => 'array',
				'items' => array( 'type' => 'integer' ),
			),
			'consenti_duplicato'   => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	/**
	 * Solo chi gestisce il negozio puo' usare il blog del plugin.
	 */
	public static function permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Il brief salvato (o quello predefinito), con l'indicazione se e' compilato.
	 */
	public static function handle_brief(): WP_REST_Response {
		return new WP_REST_Response( self::risposta_brief( self::brief() ), 200 );
	}

	/**
	 * Salva il brief: i campi mandati sostituiscono quelli esistenti, gli altri restano.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	public static function handle_salva_brief( WP_REST_Request $request ): WP_REST_Response {
		$brief = BriefBlog::pulisci( array_merge( self::brief(), (array) $request->get_json_params(), (array) $request->get_body_params() ) );
		update_option( BriefBlog::OPZIONE, $brief, false );

		return new WP_REST_Response( self::risposta_brief( $brief ), 200 );
	}

	/**
	 * Tutto quello che serve per scrivere articoli coerenti col negozio: dati del sito, brief,
	 * categorie di prodotti, i prodotti piu' venduti (con indirizzo, per linkarli) e gli articoli
	 * gia' presenti (per non ripetere gli argomenti).
	 */
	public static function handle_contesto(): WP_REST_Response {
		$brief = self::brief();

		$argomenti = array(
			'status'  => 'publish',
			'limit'   => self::MAX_PRODOTTI_CONTESTO,
			'orderby' => 'popularity',
			'order'   => 'DESC',
		);
		$slug      = array();
		foreach ( $brief['categorie_prodotti'] as $categoria_id ) {
			$termine = get_term( (int) $categoria_id, 'product_cat' );
			if ( $termine instanceof \WP_Term ) {
				$slug[] = $termine->slug;
			}
		}
		if ( array() !== $slug ) {
			$argomenti['category'] = $slug;
		}

		$prodotti = array();
		foreach ( wc_get_products( $argomenti ) as $prodotto ) {
			$immagine_id = (int) $prodotto->get_image_id();
			$prodotti[]  = array_merge(
				array(
					'id'           => $prodotto->get_id(),
					'titolo'       => $prodotto->get_name(),
					'url'          => get_permalink( $prodotto->get_id() ),
					'categorie'    => wp_get_post_terms( $prodotto->get_id(), 'product_cat', array( 'fields' => 'names' ) ),
					'immagine_id'  => $immagine_id > 0 ? $immagine_id : null,
					'immagine_url' => $immagine_id > 0 ? wp_get_attachment_url( $immagine_id ) : null,
				),
				PrezzoProdotto::di( $prodotto )
			);
		}

		return new WP_REST_Response(
			array(
				'negozio'            => array(
					'nome'        => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					'descrizione' => wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES ),
					'url'         => home_url( '/' ),
					'lingua_sito' => get_locale(),
				),
				'brief'              => self::risposta_brief( $brief ),
				'categorie_prodotti' => self::categorie_prodotti(),
				'categorie_blog'     => self::categorie_blog(),
				'prodotti'           => $prodotti,
				'prodotti_limitati'  => count( $prodotti ) >= self::MAX_PRODOTTI_CONTESTO,
				'articoli_esistenti' => self::articoli_esistenti(),
			),
			200
		);
	}

	/**
	 * Elenco degli articoli (piu' recenti prima), per stato e/o parola nel titolo o nel testo.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 */
	public static function handle_elenco( WP_REST_Request $request ): WP_REST_Response {
		$stato = (string) $request->get_param( 'stato' );
		$post  = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'any' === $stato ? array( 'publish', 'draft', 'pending', 'future', 'private' ) : $stato,
				'numberposts'      => (int) $request->get_param( 'limite' ),
				'orderby'          => 'date',
				'order'            => 'DESC',
				's'                => (string) $request->get_param( 'ricerca' ),
				'suppress_filters' => false,
			)
		);

		return new WP_REST_Response( array_map( array( self::class, 'riga' ), $post ), 200 );
	}

	/**
	 * Un articolo per intero (testo, categoria, tag, campi SEO), per rivederlo o correggerlo.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_leggi( WP_REST_Request $request ) {
		$articolo = self::trova( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $articolo ) ) {
			return $articolo;
		}

		return new WP_REST_Response( self::dettaglio( $articolo ), 200 );
	}

	/**
	 * Crea un articolo in BOZZA (mai pubblicato). Un articolo con lo stesso titolo gia' presente
	 * blocca la creazione (409), a meno di `consenti_duplicato`: cosi' rilanciare per sbaglio la
	 * stessa serie non riempie il blog di doppioni.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_crea( WP_REST_Request $request ) {
		$titolo    = sanitize_text_field( (string) $request->get_param( 'titolo' ) );
		$contenuto = wp_kses_post( (string) $request->get_param( 'contenuto' ) );
		if ( '' === $titolo ) {
			return self::errore( 'push_contempt_titolo_mancante', 'Serve il titolo dell\'articolo.', 400 );
		}
		if ( 0 === TestoBlog::conta_parole( $contenuto ) ) {
			return self::errore( 'push_contempt_contenuto_vuoto', 'Il testo dell\'articolo e\' vuoto.', 400 );
		}

		if ( ! $request->get_param( 'consenti_duplicato' ) ) {
			$doppione = self::articolo_con_titolo( $titolo );
			if ( $doppione ) {
				return self::errore(
					'push_contempt_articolo_duplicato',
					"Esiste gia' un articolo con questo titolo (id {$doppione->ID}, {$doppione->post_status}). Cambia titolo, modifica quello esistente o passa consenti_duplicato.",
					409,
					array( 'articolo_id' => $doppione->ID )
				);
			}
		}

		$verifica = self::verifica_riferimenti( $request );
		if ( is_wp_error( $verifica ) ) {
			return $verifica;
		}

		$articolo_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'post_title'   => $titolo,
				'post_content' => $contenuto,
				'post_excerpt' => sanitize_textarea_field( (string) $request->get_param( 'estratto' ) ),
				'post_author'  => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $articolo_id ) ) {
			return $articolo_id;
		}

		$categoria_creata = self::applica_campi( $articolo_id, $request );

		$articolo = get_post( $articolo_id );
		return new WP_REST_Response(
			array_merge(
				array(
					'esito'            => 'creato_in_bozza',
					'articolo_id'      => $articolo_id,
					'categoria_creata' => $categoria_creata,
				),
				self::riga( $articolo )
			),
			201
		);
	}

	/**
	 * Modifica una bozza: cambia solo i campi presenti nella richiesta. Un articolo non in bozza
	 * (pubblicato, programmato, privato) si rifiuta con 409: va modificato da wp-admin.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_aggiorna( WP_REST_Request $request ) {
		$articolo = self::trova( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $articolo ) ) {
			return $articolo;
		}
		if ( ! in_array( $articolo->post_status, array( 'draft', 'pending' ), true ) ) {
			return self::errore(
				'push_contempt_articolo_non_in_bozza',
				"L'articolo {$articolo->ID} e' {$articolo->post_status}, non in bozza: da qui si modificano solo le bozze. Per cambiarlo usa wp-admin.",
				409
			);
		}

		$modifiche = array( 'ID' => $articolo->ID );
		if ( $request->has_param( 'titolo' ) ) {
			$titolo = sanitize_text_field( (string) $request->get_param( 'titolo' ) );
			if ( '' === $titolo ) {
				return self::errore( 'push_contempt_titolo_mancante', 'Il titolo non puo\' essere vuoto.', 400 );
			}
			$modifiche['post_title'] = $titolo;
		}
		if ( $request->has_param( 'contenuto' ) ) {
			$contenuto = wp_kses_post( (string) $request->get_param( 'contenuto' ) );
			if ( 0 === TestoBlog::conta_parole( $contenuto ) ) {
				return self::errore( 'push_contempt_contenuto_vuoto', 'Il testo dell\'articolo non puo\' essere vuoto.', 400 );
			}
			$modifiche['post_content'] = $contenuto;
		}
		if ( $request->has_param( 'estratto' ) ) {
			$modifiche['post_excerpt'] = sanitize_textarea_field( (string) $request->get_param( 'estratto' ) );
		}

		$verifica = self::verifica_riferimenti( $request );
		if ( is_wp_error( $verifica ) ) {
			return $verifica;
		}

		if ( count( $modifiche ) > 1 ) {
			$esito = wp_update_post( $modifiche, true );
			if ( is_wp_error( $esito ) ) {
				return $esito;
			}
		}
		$categoria_creata = self::applica_campi( $articolo->ID, $request );

		return new WP_REST_Response(
			array_merge(
				array(
					'esito'            => 'aggiornato',
					'categoria_creata' => $categoria_creata,
				),
				self::riga( get_post( $articolo->ID ) )
			),
			200
		);
	}

	/**
	 * Il brief salvato, ripulito e completato coi valori predefiniti.
	 */
	private static function brief(): array {
		return BriefBlog::pulisci( get_option( BriefBlog::OPZIONE, array() ) );
	}

	/**
	 * Il brief piu' l'indicazione se ha l'argomento.
	 *
	 * @param array $brief Brief gia' pulito.
	 */
	private static function risposta_brief( array $brief ): array {
		return array_merge( $brief, array( 'compilato' => BriefBlog::e_compilato( $brief ) ) );
	}

	/**
	 * Le categorie di prodotti come `{ id, nome, madre, url, n_prodotti }`.
	 */
	private static function categorie_prodotti(): array {
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
				'id'         => (int) $termine->term_id,
				'nome'       => wp_specialchars_decode( $termine->name, ENT_QUOTES ),
				'madre'      => (int) $termine->parent,
				'url'        => get_term_link( $termine ),
				'n_prodotti' => (int) $termine->count,
			);
		}
		return $elenco;
	}

	/**
	 * Le categorie del blog come `{ id, nome }`.
	 */
	private static function categorie_blog(): array {
		$termini = get_terms(
			array(
				'taxonomy'   => 'category',
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
				'id'   => (int) $termine->term_id,
				'nome' => wp_specialchars_decode( $termine->name, ENT_QUOTES ),
			);
		}
		return $elenco;
	}

	/**
	 * Titolo, stato e indirizzo degli articoli presenti, per non ripeterne gli argomenti.
	 */
	private static function articoli_esistenti(): array {
		$elenco = array();
		$post   = get_posts(
			array(
				'post_type'   => 'post',
				'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'numberposts' => self::MAX_ARTICOLI_CONTESTO,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		foreach ( $post as $articolo ) {
			$elenco[] = array(
				'id'     => $articolo->ID,
				'titolo' => wp_specialchars_decode( $articolo->post_title, ENT_QUOTES ),
				'stato'  => $articolo->post_status,
				'url'    => 'publish' === $articolo->post_status ? get_permalink( $articolo ) : null,
			);
		}
		return $elenco;
	}

	/**
	 * Un articolo (solo di tipo `post`) dato il suo id, o un errore 404.
	 *
	 * @param int $id Id dell'articolo.
	 *
	 * @return WP_Post|WP_Error
	 */
	private static function trova( int $id ) {
		$articolo = get_post( $id );
		if ( ! $articolo instanceof WP_Post || 'post' !== $articolo->post_type || in_array( $articolo->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return self::errore( 'push_contempt_articolo_non_trovato', "Nessun articolo con id {$id}", 404 );
		}
		return $articolo;
	}

	/**
	 * Un articolo non cestinato che ha esattamente questo titolo, se c'e'.
	 *
	 * @param string $titolo Titolo da cercare.
	 */
	private static function articolo_con_titolo( string $titolo ): ?WP_Post {
		$trovati = get_posts(
			array(
				'post_type'   => 'post',
				'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'title'       => $titolo,
				'numberposts' => 1,
			)
		);
		return $trovati[0] ?? null;
	}

	/**
	 * Controlla che immagine in evidenza e prodotti collegati esistano davvero.
	 *
	 * @param WP_REST_Request $request Richiesta REST in arrivo.
	 *
	 * @return true|WP_Error
	 */
	private static function verifica_riferimenti( WP_REST_Request $request ) {
		$immagine = $request->get_param( 'immagine_evidenza_id' );
		if ( $immagine && 'attachment' !== get_post_type( (int) $immagine ) ) {
			return self::errore( 'push_contempt_immagine_non_trovata', "Nessuna immagine con id {$immagine} nella libreria media.", 400 );
		}
		foreach ( (array) $request->get_param( 'prodotti_ids' ) as $prodotto_id ) {
			if ( ! wc_get_product( (int) $prodotto_id ) ) {
				return self::errore( 'push_contempt_prodotto_non_trovato', "Nessun prodotto con id {$prodotto_id}.", 400 );
			}
		}
		return true;
	}

	/**
	 * Scrive i campi facoltativi presenti nella richiesta (categoria, tag, immagine, ALT,
	 * SEO Yoast, prodotti collegati).
	 *
	 * @param int             $articolo_id Id dell'articolo.
	 * @param WP_REST_Request $request     Richiesta REST in arrivo.
	 *
	 * @return bool True se la categoria e' stata creata ora.
	 */
	private static function applica_campi( int $articolo_id, WP_REST_Request $request ): bool {
		$categoria_creata = false;

		$categoria = sanitize_text_field( (string) $request->get_param( 'categoria' ) );
		if ( '' !== $categoria ) {
			$termine = get_term_by( 'name', $categoria, 'category' );
			if ( ! $termine ) {
				$nuovo = wp_insert_term( $categoria, 'category' );
				if ( ! is_wp_error( $nuovo ) ) {
					$categoria_creata = true;
					wp_set_post_categories( $articolo_id, array( (int) $nuovo['term_id'] ) );
				}
			} else {
				wp_set_post_categories( $articolo_id, array( (int) $termine->term_id ) );
			}
		}

		if ( $request->has_param( 'tag' ) ) {
			wp_set_post_tags( $articolo_id, array_map( 'sanitize_text_field', array_map( 'strval', (array) $request->get_param( 'tag' ) ) ) );
		}

		$immagine = $request->get_param( 'immagine_evidenza_id' );
		if ( $immagine ) {
			set_post_thumbnail( $articolo_id, (int) $immagine );
		}
		foreach ( (array) $request->get_param( 'alt_immagini' ) as $attachment_id => $alt ) {
			if ( 'attachment' === get_post_type( (int) $attachment_id ) ) {
				update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $alt ) );
			}
		}

		foreach ( SeoProdottoController::CAMPI_YOAST as $parametro => $meta_key ) {
			if ( $request->has_param( $parametro ) ) {
				update_post_meta( $articolo_id, $meta_key, sanitize_text_field( (string) $request->get_param( $parametro ) ) );
			}
		}

		if ( $request->has_param( 'prodotti_ids' ) ) {
			update_post_meta( $articolo_id, self::META_PRODOTTI, array_values( array_unique( array_map( 'intval', (array) $request->get_param( 'prodotti_ids' ) ) ) ) );
		}

		return $categoria_creata;
	}

	/**
	 * Le informazioni di riepilogo di un articolo (per elenchi e risposte di scrittura).
	 *
	 * @param WP_Post $articolo L'articolo.
	 */
	private static function riga( WP_Post $articolo ): array {
		$link = class_exists( 'DOMDocument' )
			? AnalisiLink::analizza( $articolo->post_content, (string) wp_parse_url( home_url(), PHP_URL_HOST ) )
			: null;

		return array(
			'id'                 => $articolo->ID,
			'titolo'             => wp_specialchars_decode( $articolo->post_title, ENT_QUOTES ),
			'stato'              => $articolo->post_status,
			'creato'             => get_post_datetime( $articolo )->format( 'c' ),
			'n_parole'           => TestoBlog::conta_parole( $articolo->post_content ),
			'n_link_interni'     => null === $link ? null : count( $link['interni'] ),
			'n_link_esterni'     => null === $link ? null : count( $link['esterni'] ),
			'prodotti_collegati' => self::prodotti_collegati( $articolo->ID ),
			'url_modifica'       => admin_url( "post.php?post={$articolo->ID}&action=edit" ),
			'url'                => 'publish' === $articolo->post_status ? get_permalink( $articolo ) : null,
		);
	}

	/**
	 * L'articolo per intero: il riepilogo piu' testo, categorie, tag e campi SEO.
	 *
	 * @param WP_Post $articolo L'articolo.
	 */
	private static function dettaglio( WP_Post $articolo ): array {
		$campi = array(
			'contenuto'            => $articolo->post_content,
			'estratto'             => $articolo->post_excerpt,
			'categorie'            => wp_get_post_categories( $articolo->ID, array( 'fields' => 'names' ) ),
			'tag'                  => wp_get_post_tags( $articolo->ID, array( 'fields' => 'names' ) ),
			'immagine_evidenza_id' => get_post_thumbnail_id( $articolo ) ? (int) get_post_thumbnail_id( $articolo ) : null,
		);
		foreach ( SeoProdottoController::CAMPI_YOAST as $campo => $meta_key ) {
			$valore          = get_post_meta( $articolo->ID, $meta_key, true );
			$campi[ $campo ] = '' !== $valore ? $valore : null;
		}

		return array_merge( self::riga( $articolo ), $campi );
	}

	/**
	 * Gli id dei prodotti che l'articolo promuove.
	 *
	 * @param int $articolo_id Id dell'articolo.
	 */
	private static function prodotti_collegati( int $articolo_id ): array {
		$ids = get_post_meta( $articolo_id, self::META_PRODOTTI, true );
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * Un errore REST con codice HTTP.
	 *
	 * @param string $codice  Codice dell'errore.
	 * @param string $testo   Messaggio leggibile.
	 * @param int    $stato   Codice HTTP.
	 * @param array  $dettagli Altri dati da restituire.
	 */
	private static function errore( string $codice, string $testo, int $stato, array $dettagli = array() ): WP_Error {
		return new WP_Error( $codice, $testo, array_merge( array( 'status' => $stato ), $dettagli ) );
	}
}
