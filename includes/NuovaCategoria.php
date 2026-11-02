<?php
/**
 * Regole per creare una categoria da sola (nessuna chiamata WordPress/DB).
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Stesse regole delle cartelle dei prodotti: un nome di categoria esiste UNA volta sola
 * in tutto l'albero (anche in un altro ramo), al massimo `PercorsoCategorie::MAX_LIVELLI`
 * livelli, nome non vuoto e non troppo lungo. Lavora sull'elenco `{ id, nome, madre }`.
 */
class NuovaCategoria {

	/**
	 * Il piano per creare la categoria, o l'errore.
	 *
	 * @param array[] $categorie Tutte le categorie `{ id, nome, madre }`.
	 * @param mixed   $nome      Nome richiesto.
	 * @param int     $madre     Categoria madre (0 = radice).
	 *
	 * @return array{errore?: array{codice: string, messaggio: string, stato: int}, piano?: array}
	 */
	public static function pianifica( array $categorie, $nome, int $madre ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- logica pura, senza WordPress (wp_strip_all_tags non c'e').
		$nome = trim( (string) preg_replace( '/\s+/u', ' ', strip_tags( is_scalar( $nome ) ? (string) $nome : '' ) ) );
		if ( '' === $nome ) {
			return self::errore( 'nome_vuoto', 'Il nome della categoria e\' vuoto.', 400 );
		}
		if ( mb_strlen( $nome ) > PercorsoCategorie::MAX_LUNGHEZZA ) {
			return self::errore( 'nome_troppo_lungo', "Il nome della categoria e' troppo lungo (al massimo " . PercorsoCategorie::MAX_LUNGHEZZA . ' caratteri).', 400 );
		}

		$per_id = array_column( $categorie, null, 'id' );
		if ( $madre > 0 && ! isset( $per_id[ $madre ] ) ) {
			return self::errore( 'madre_non_trovata', "Nessuna categoria con id {$madre} dove metterla.", 404 );
		}

		$chiave = PercorsoCategorie::normalizza( $nome );
		foreach ( $categorie as $categoria ) {
			if ( PercorsoCategorie::normalizza( (string) $categoria['nome'] ) === $chiave ) {
				return self::errore(
					'nome_gia_esistente',
					"La categoria «{$nome}» esiste gia': " . SpostamentoCategoria::percorso( $categorie, (int) $categoria['id'] ) . '. Un nome di categoria esiste una volta sola nell\'albero: non se ne crea una seconda (per metterla sotto un\'altra, spostala).',
					409
				);
			}
		}

		$antenati = SpostamentoCategoria::catena( $per_id, $madre );
		$livello  = count( $antenati ) + 1;
		if ( $livello > PercorsoCategorie::MAX_LIVELLI ) {
			return self::errore( 'troppi_livelli', 'La categoria sarebbe al livello ' . $livello . ': al massimo ' . PercorsoCategorie::MAX_LIVELLI . '.', 400 );
		}

		$nomi_madre = array_map( static fn( int $id ): string => $per_id[ $id ]['nome'], $antenati );
		return array(
			'piano' => array(
				'nome'          => $nome,
				'madre'         => $madre,
				'livello'       => $livello,
				'percorso_dopo' => implode( ' > ', array_merge( $nomi_madre, array( $nome ) ) ),
			),
		);
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
