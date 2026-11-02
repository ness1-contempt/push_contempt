<?php
/**
 * Regole per spostare una categoria sotto un'altra (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Lavora sull'elenco `{ id, nome, madre }` delle categorie. Rifiuta cio' che
 * romperebbe l'albero: spostare una categoria dentro se stessa o in una sua
 * figlia (un giro chiuso), superare i livelli massimi (come per le cartelle),
 * muovere la categoria predefinita o "spostare" dove gia' sta.
 */
class SpostamentoCategoria {

	/**
	 * Il piano di uno spostamento, o l'errore.
	 *
	 * @param array[] $categorie    Tutte le categorie `{ id, nome, madre }`.
	 * @param int     $id           Categoria da spostare.
	 * @param int     $nuova_madre  Nuova madre (0 = radice).
	 * @param int     $predefinita  Id della categoria predefinita (non si sposta).
	 *
	 * @return array{errore?: array{codice: string, messaggio: string, stato: int}, piano?: array}
	 */
	public static function pianifica( array $categorie, int $id, int $nuova_madre, int $predefinita ): array {
		$per_id = array_column( $categorie, null, 'id' );

		if ( ! isset( $per_id[ $id ] ) ) {
			return self::errore( 'categoria_non_trovata', "Nessuna categoria con id {$id}.", 404 );
		}
		if ( $nuova_madre > 0 && ! isset( $per_id[ $nuova_madre ] ) ) {
			return self::errore( 'nuova_madre_non_trovata', "Nessuna categoria con id {$nuova_madre} dove spostarla.", 404 );
		}
		$categoria = $per_id[ $id ];
		if ( $id === $predefinita ) {
			return self::errore( 'categoria_predefinita', "«{$categoria['nome']}» e' la categoria predefinita di WooCommerce: non si sposta.", 400 );
		}
		if ( (int) $categoria['madre'] === $nuova_madre ) {
			return self::errore( 'nessun_cambiamento', "«{$categoria['nome']}» e' gia' li': non c'e' niente da spostare.", 400 );
		}

		$discendenti = self::discendenti( $categorie, $id );
		if ( $nuova_madre === $id || in_array( $nuova_madre, $discendenti, true ) ) {
			return self::errore( 'spostamento_in_se_stessa', "Non si puo' spostare «{$categoria['nome']}» dentro se stessa o dentro una sua sottocategoria.", 400 );
		}

		$livelli_madre = self::catena( $per_id, $nuova_madre );
		$altezza       = self::altezza( $categorie, $id );
		$livelli_dopo  = count( $livelli_madre ) + $altezza;
		if ( $livelli_dopo > PercorsoCategorie::MAX_LIVELLI ) {
			return self::errore( 'troppi_livelli', 'Dopo lo spostamento l\'albero avrebbe ' . $livelli_dopo . ' livelli: al massimo ' . PercorsoCategorie::MAX_LIVELLI . '.', 400 );
		}

		$nomi_prima = self::nomi( $per_id, self::catena( $per_id, $id ) );
		$nomi_dopo  = array_merge( self::nomi( $per_id, $livelli_madre ), array( $categoria['nome'] ) );

		return array(
			'piano' => array(
				'id'             => $id,
				'nome'           => $categoria['nome'],
				'madre_prima'    => (int) $categoria['madre'],
				'madre_dopo'     => $nuova_madre,
				'percorso_prima' => implode( ' > ', $nomi_prima ),
				'percorso_dopo'  => implode( ' > ', $nomi_dopo ),
				'sottocategorie' => $discendenti,
				'livelli_dopo'   => $livelli_dopo,
			),
		);
	}

	/**
	 * Id di tutte le sottocategorie (figlie, nipoti...) di una categoria.
	 *
	 * @param array[] $categorie Tutte le categorie.
	 * @param int     $id        Categoria di partenza.
	 *
	 * @return int[]
	 */
	public static function discendenti( array $categorie, int $id ): array {
		$trovati = array();
		$da_fare = array( $id );
		$giri    = 0;
		while ( $da_fare && $giri < 1000 ) {
			$corrente = array_shift( $da_fare );
			foreach ( $categorie as $categoria ) {
				if ( (int) $categoria['madre'] === $corrente && ! in_array( (int) $categoria['id'], $trovati, true ) ) {
					$trovati[] = (int) $categoria['id'];
					$da_fare[] = (int) $categoria['id'];
				}
			}
			++$giri;
		}
		return $trovati;
	}

	/**
	 * Quanti livelli ha il ramo che parte da una categoria (lei compresa).
	 *
	 * @param array[] $categorie Tutte le categorie.
	 * @param int     $id        Categoria di partenza.
	 */
	private static function altezza( array $categorie, int $id ): int {
		$massimo = 0;
		foreach ( $categorie as $categoria ) {
			if ( (int) $categoria['madre'] === $id ) {
				$massimo = max( $massimo, self::altezza( $categorie, (int) $categoria['id'] ) );
			}
		}
		return $massimo + 1;
	}

	/**
	 * Il percorso di una categoria come testo ("Indumenti > Magliette").
	 *
	 * @param array[] $categorie Tutte le categorie `{ id, nome, madre }`.
	 * @param int     $id        Categoria.
	 */
	public static function percorso( array $categorie, int $id ): string {
		$per_id = array_column( $categorie, null, 'id' );
		return implode( ' > ', self::nomi( $per_id, self::catena( $per_id, $id ) ) );
	}

	/**
	 * Gli id dalla radice alla categoria (lei compresa); vuoto per la radice (id 0).
	 *
	 * @param array $per_id Categorie per id.
	 * @param int   $id     Categoria.
	 *
	 * @return int[]
	 */
	public static function catena( array $per_id, int $id ): array {
		$ids  = array();
		$giri = 0;
		while ( $id > 0 && isset( $per_id[ $id ] ) && $giri < 20 ) {
			array_unshift( $ids, $id );
			$id = (int) $per_id[ $id ]['madre'];
			++$giri;
		}
		return $ids;
	}

	/**
	 * I nomi di un elenco di id.
	 *
	 * @param array $per_id Categorie per id.
	 * @param int[] $ids    Id.
	 *
	 * @return string[]
	 */
	private static function nomi( array $per_id, array $ids ): array {
		return array_map( static fn( int $id ): string => $per_id[ $id ]['nome'], $ids );
	}

	/**
	 * Un errore nel formato di uscita.
	 *
	 * @param string $codice    Identificativo stabile.
	 * @param string $messaggio Spiegazione.
	 * @param int    $stato     Codice HTTP.
	 */
	private static function errore( string $codice, string $messaggio, int $stato ): array {
		return array(
			'errore' => array(
				'codice'    => $codice,
				'messaggio' => $messaggio,
				'stato'     => $stato,
			),
		);
	}
}
