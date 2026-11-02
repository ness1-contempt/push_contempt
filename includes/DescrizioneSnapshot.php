<?php
/**
 * Versioni (base, precedente) della descrizione di un prodotto.
 *
 * @package PushContempt
 */

namespace PushContempt;

/**
 * Stessa logica di SeoSnapshot (base = punto di partenza, precedente = versione
 * appena sostituita, ripristino reversibile), applicata alla descrizione
 * lunga e alla descrizione breve del prodotto invece che ai campi Yoast.
 */
class DescrizioneSnapshot extends SeoSnapshot {

	/**
	 * Chiave del meta del prodotto in cui si conservano le versioni.
	 */
	const META_KEY = '_pc_descrizione_snapshot';

	/**
	 * I campi coperti (stessi nomi usati dalla REST API del plugin).
	 */
	const CAMPI = array( 'descrizione', 'descrizione_breve' );
}
