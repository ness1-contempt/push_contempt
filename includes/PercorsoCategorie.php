<?php
/**
 * Percorso di categorie di un prodotto (da una cartella) confrontato con l'albero di WooCommerce
 * (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Le cartelle dei prodotti rispecchiano l'albero delle categorie: `Indumenti/Magliette/…`
 * vuol dire la categoria Magliette dentro Indumenti. Qui si decide, livello per livello,
 * cosa fare con ogni categoria del percorso:
 *
 * - `esiste`: c'e' gia' ed e' nel posto giusto (sotto la madre attesa);
 * - `da_creare`: non esiste, si crea sotto la madre attesa;
 * - `conflitto`: il nome esiste gia' ma in un altro punto dell'albero, oppure esiste piu'
 *   di una categoria con quel nome.
 *
 * Regola: un nome di categoria esiste UNA volta sola in tutto l'albero. Per questo non si
 * riusa mai una categoria con lo stesso nome ma in un altro ramo, e non se ne crea una
 * seconda: si segnala il problema e non si crea niente.
 */
class PercorsoCategorie {

	/**
	 * Quanti livelli al massimo.
	 */
	const MAX_LIVELLI = 5;

	/**
	 * Lunghezza massima del nome di una categoria.
	 */
	const MAX_LUNGHEZZA = 100;

	/**
	 * Un nome senza maiuscole e con gli spazi ridotti a uno, per confrontare i nomi.
	 *
	 * @param string $nome Nome di una categoria.
	 */
	public static function normalizza( string $nome ): string {
		return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $nome ) ) );
	}

	/**
	 * Il percorso ripulito (spazi) e gli errori di forma.
	 *
	 * @param mixed $percorso Elenco di nomi, dalla radice alla categoria del prodotto.
	 *
	 * @return array{percorso: string[], errori: string[]}
	 */
	public static function pulisci( $percorso ): array {
		$errori = array();
		if ( ! is_array( $percorso ) || array() === $percorso ) {
			return array(
				'percorso' => array(),
				'errori'   => array( 'Il percorso delle categorie e\' vuoto.' ),
			);
		}
		if ( count( $percorso ) > self::MAX_LIVELLI ) {
			$errori[] = 'Troppi livelli di categorie (' . count( $percorso ) . '): al massimo ' . self::MAX_LIVELLI . '.';
		}

		$puliti = array();
		$visti  = array();
		foreach ( array_values( $percorso ) as $posizione => $nome ) {
			$nome = trim( (string) preg_replace( '/\s+/u', ' ', is_scalar( $nome ) ? (string) $nome : '' ) );
			if ( '' === $nome ) {
				$errori[] = 'Il livello n. ' . ( $posizione + 1 ) . ' del percorso non ha un nome.';
				continue;
			}
			if ( mb_strlen( $nome ) > self::MAX_LUNGHEZZA ) {
				$errori[] = "Il nome della categoria «{$nome}» e' troppo lungo.";
			}
			$chiave = self::normalizza( $nome );
			if ( isset( $visti[ $chiave ] ) ) {
				$errori[] = "La categoria «{$nome}» compare piu' di una volta nello stesso percorso.";
			}
			$visti[ $chiave ] = true;
			$puliti[]         = $nome;
		}
		return array(
			'percorso' => $puliti,
			'errori'   => $errori,
		);
	}

	/**
	 * Confronta un percorso con le categorie esistenti.
	 *
	 * @param string[] $percorso   Nomi dalla radice alla categoria del prodotto (gia' puliti).
	 * @param array[]  $categorie  Categorie esistenti: ciascuna `{ id, nome, madre }` (madre 0 = radice).
	 *
	 * @return array{livelli: array, errori: string[]}
	 *         Ogni livello: `nome`, `stato` (esiste / da_creare / conflitto), `id` (null se
	 *         da creare), `percorso` (come si presenta nell'albero).
	 */
	public static function risolvi( array $percorso, array $categorie ): array {
		$per_nome = array();
		foreach ( $categorie as $categoria ) {
			$per_nome[ self::normalizza( (string) $categoria['nome'] ) ][] = $categoria;
		}

		$livelli = array();
		$errori  = array();
		$madre   = 0;     // Id della madre attesa; null se la madre del livello va ancora creata.
		$cammino = array();
		foreach ( $percorso as $nome ) {
			$cammino[] = $nome;
			$trovate   = $per_nome[ self::normalizza( $nome ) ] ?? array();
			$atteso    = implode( ' > ', $cammino );

			if ( count( $trovate ) > 1 ) {
				$dove      = array_map( static fn( $c ) => self::percorso_di( $c, $categorie ), $trovate );
				$errori[]  = 'Esistono ' . count( $trovate ) . " categorie chiamate «{$nome}» (" . implode( '; ', $dove ) . '): i nomi delle categorie devono essere unici, sistemale su WooCommerce.';
				$livelli[] = array(
					'nome'     => $nome,
					'stato'    => 'conflitto',
					'id'       => null,
					'percorso' => $atteso,
				);
				$madre     = null;
				continue;
			}

			if ( array() === $trovate ) {
				$livelli[] = array(
					'nome'     => $nome,
					'stato'    => 'da_creare',
					'id'       => null,
					'percorso' => $atteso,
				);
				$madre     = null;
				continue;
			}

			$esistente = $trovate[0];
			if ( null !== $madre && (int) $esistente['madre'] === $madre ) {
				$cammino[ count( $cammino ) - 1 ] = $esistente['nome']; // Il nome vero sul sito, non quello scritto nella cartella.
				$livelli[]                        = array(
					'nome'     => $esistente['nome'],
					'stato'    => 'esiste',
					'id'       => (int) $esistente['id'],
					'percorso' => implode( ' > ', $cammino ),
				);
				$madre                            = (int) $esistente['id'];
				continue;
			}

			$trovato   = self::percorso_di( $esistente, $categorie );
			$errori[]  = "La categoria «{$nome}» esiste gia' come «{$trovato}», ma la cartella la vuole in «{$atteso}»: " .
				'un nome di categoria deve esistere una volta sola. Sposta la cartella o la categoria cosi\' che coincidano.';
			$livelli[] = array(
				'nome'     => $nome,
				'stato'    => 'conflitto',
				'id'       => (int) $esistente['id'],
				'percorso' => $atteso,
			);
			$madre     = (int) $esistente['id'];
		}

		return array(
			'livelli' => $livelli,
			'errori'  => $errori,
		);
	}

	/**
	 * I nomi di categoria che compaiono in punti diversi delle cartelle, con i punti.
	 *
	 * @param array[] $percorsi Elenco di percorsi (ciascuno: elenco di nomi).
	 *
	 * @return array[] Per ogni conflitto: `nome` e `posti` (i percorsi completi in cui compare).
	 */
	public static function conflitti_dettaglio( array $percorsi ): array {
		$posti = array();
		foreach ( $percorsi as $percorso ) {
			$cammino = array();
			foreach ( array_values( (array) $percorso ) as $nome ) {
				$nome                                       = trim( (string) preg_replace( '/\s+/u', ' ', (string) $nome ) );
				$cammino[]                                  = $nome;
				$completo                                   = implode( ' > ', $cammino );
				$posti[ self::normalizza( $nome ) ]['nome'] = $nome;
				$posti[ self::normalizza( $nome ) ]['posti'][ self::normalizza( $completo ) ] = $completo;
			}
		}
		$conflitti = array();
		foreach ( $posti as $voce ) {
			if ( count( $voce['posti'] ) > 1 ) {
				$conflitti[] = array(
					'nome'  => $voce['nome'],
					'posti' => array_values( $voce['posti'] ),
				);
			}
		}
		return $conflitti;
	}

	/**
	 * Gli errori fra piu' percorsi di cartelle: lo stesso nome di categoria in due punti diversi.
	 *
	 * @param array[] $percorsi Elenco di percorsi (ciascuno: elenco di nomi).
	 *
	 * @return string[]
	 */
	public static function conflitti_tra_percorsi( array $percorsi ): array {
		$errori = array();
		foreach ( self::conflitti_dettaglio( $percorsi ) as $conflitto ) {
			$errori[] = 'Lo stesso nome di categoria compare in piu\' punti delle cartelle (' . implode( '; ', $conflitto['posti'] ) . '): i nomi delle categorie devono essere unici.';
		}
		return $errori;
	}

	/**
	 * Come si presenta una categoria nell'albero (`Madre > Figlia`).
	 *
	 * @param array   $categoria  Categoria `{ id, nome, madre }`.
	 * @param array[] $categorie  Tutte le categorie esistenti.
	 */
	private static function percorso_di( array $categoria, array $categorie ): string {
		$per_id = array_column( $categorie, null, 'id' );
		$nomi   = array( $categoria['nome'] );
		$madre  = (int) $categoria['madre'];
		$giri   = 0;
		while ( $madre > 0 && isset( $per_id[ $madre ] ) && $giri < 20 ) {
			array_unshift( $nomi, $per_id[ $madre ]['nome'] );
			$madre = (int) $per_id[ $madre ]['madre'];
			++$giri;
		}
		return implode( ' > ', $nomi );
	}
}
