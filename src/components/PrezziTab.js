import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	Flex,
	FlexItem,
	FormTokenField,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import LinkProdotto from './LinkProdotto';

/**
 * La Store API restituisce i nomi prodotto con entity HTML (es. "&#8211;"
 * per i trattini lunghi) non decodificate.
 *
 * @param {string} testo Testo con eventuali entity HTML.
 */
function decodificaEntityHtml( testo ) {
	const area = document.createElement( 'textarea' );
	area.innerHTML = testo;
	return area.value;
}

/**
 * Etichetta univoca per i token del menu prodotti: il nome da solo
 * potrebbe ripetersi tra prodotti diversi, l'id in coda lo esclude.
 *
 * @param {Object} prodotto Prodotto dalla Store API ({ id, name }).
 */
function etichettaProdotto( prodotto ) {
	return `${ decodificaEntityHtml( prodotto.name ) } (#${ prodotto.id })`;
}

/**
 * Inverso di etichettaProdotto(): estrae l'id numerico da un token
 * "Nome (#123)" selezionato nel FormTokenField.
 *
 * @param {string} etichetta Token come mostrato/selezionato nel campo.
 */
function idDaEtichetta( etichetta ) {
	const corrispondenza = /\(#(\d+)\)\s*$/.exec( etichetta );
	return corrispondenza ? parseInt( corrispondenza[ 1 ], 10 ) : null;
}

/**
 * Prezzi in blocco per categoria: anteprima via REST (nessuna scrittura),
 * poi scelta di quali prodotti scontare e applicazione solo su conferma
 * esplicita dell'utente - stesso pattern prepara/conferma già usato altrove
 * nel plugin.
 */
export default function PrezziTab() {
	const [ categorie, setCategorie ] = useState( null );
	const [ categoria, setCategoria ] = useState( '' );
	const [ prodottiCategoria, setProdottiCategoria ] = useState( [] );
	const [ caricamentoProdotti, setCaricamentoProdotti ] = useState( false );
	const [ prodottiScelti, setProdottiScelti ] = useState( [] );
	const [ percentuale, setPercentuale ] = useState( '' );
	const [ delta, setDelta ] = useState( '' );
	const [ anteprima, setAnteprima ] = useState( null );
	const [ selezionati, setSelezionati ] = useState( new Set() );
	const [ caricamento, setCaricamento ] = useState( false );
	const [ applicazione, setApplicazione ] = useState( false );
	const [ errore, setErrore ] = useState( null );
	const [ esito, setEsito ] = useState( null );

	// Categorie prodotto per il menu a tendina: endpoint nativo di WordPress,
	// WooCommerce registra "product_cat" come tassonomia REST-abilitata.
	useEffect( () => {
		apiFetch( { path: '/wp/v2/product_cat?per_page=100&orderby=name&order=asc' } )
			.then( ( risposta ) => setCategorie( risposta.filter( ( cat ) => cat.count > 0 ) ) )
			.catch( () => setCategorie( [] ) );
	}, [] );

	// Prodotti della categoria scelta, per poterne richiamare e selezionare
	// solo alcuni: Store API di WooCommerce, pubblica e già filtrata sul
	// catalogo visibile, nessun nuovo endpoint necessario.
	useEffect( () => {
		setProdottiScelti( [] );
		if ( '' === categoria ) {
			setProdottiCategoria( [] );
			return;
		}

		setCaricamentoProdotti( true );
		apiFetch( { path: `/wc/store/v1/products?category=${ encodeURIComponent( categoria ) }&per_page=100` } )
			.then( setProdottiCategoria )
			.catch( () => setProdottiCategoria( [] ) )
			.finally( () => setCaricamentoProdotti( false ) );
	}, [ categoria ] );

	const calcola = () => {
		if ( '' === categoria || ( '' === percentuale ) === ( '' === delta ) ) {
			setErrore( 'Specificare una categoria ed esattamente uno tra percentuale e delta.' );
			return;
		}

		setCaricamento( true );
		setErrore( null );
		setEsito( null );

		const parametri = new URLSearchParams( { categoria } );
		if ( '' !== percentuale ) {
			parametri.set( 'percentuale', percentuale );
		}
		if ( '' !== delta ) {
			parametri.set( 'delta', delta );
		}

		apiFetch( { path: `/push-contempt/v1/prezzi/calcola?${ parametri.toString() }` } )
			.then( ( risposta ) => {
				// Se l'utente ha scelto solo alcuni prodotti dal menu, l'anteprima
				// si restringe a quelli (nessuna modifica lato REST: filtro lato client).
				if ( prodottiScelti.length > 0 ) {
					const idScelti = new Set( prodottiScelti.map( ( token ) => idDaEtichetta( token ) ) );
					risposta = {
						...risposta,
						aggiornamenti: risposta.aggiornamenti.filter( ( riga ) => idScelti.has( riga.id ) ),
						prodotti_variabili_esclusi: risposta.prodotti_variabili_esclusi.filter( ( riga ) =>
							idScelti.has( riga.id )
						),
					};
				}
				setAnteprima( risposta );
				// Di default tutti i prodotti proposti sono selezionati per lo sconto.
				setSelezionati( new Set( risposta.aggiornamenti.map( ( riga ) => riga.id ) ) );
			} )
			.catch( ( err ) => setErrore( err.message || 'Errore nel calcolo.' ) )
			.finally( () => setCaricamento( false ) );
	};

	const alternaSelezione = ( id, selezionato ) => {
		setSelezionati( ( precedenti ) => {
			const nuovi = new Set( precedenti );
			if ( selezionato ) {
				nuovi.add( id );
			} else {
				nuovi.delete( id );
			}
			return nuovi;
		} );
	};

	const applica = () => {
		const daApplicare = anteprima.aggiornamenti.filter( ( riga ) => selezionati.has( riga.id ) );
		if ( 0 === daApplicare.length ) {
			setErrore( 'Seleziona almeno un prodotto da scontare.' );
			return;
		}

		setApplicazione( true );
		setErrore( null );

		apiFetch( {
			path: '/push-contempt/v1/prezzi/applica',
			method: 'POST',
			data: { aggiornamenti: daApplicare },
		} )
			.then( ( risposta ) => {
				setEsito( risposta );
				setAnteprima( null );
			} )
			.catch( ( err ) => setErrore( err.message || "Errore nell'applicazione." ) )
			.finally( () => setApplicazione( false ) );
	};

	return (
		<div style={ { marginTop: '16px' } }>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Scegli una categoria, poi eventualmente uno o più prodotti specifici se non vuoi scontarli tutti, e indica
				di quanto cambiare il prezzo (percentuale, es. "10" per +10%, oppure un importo fisso in euro, es. "-5").
				"Calcola anteprima" mostra solo i nuovi prezzi senza scrivere nulla: puoi togliere la spunta ai prodotti
				che non vuoi toccare prima di premere "Applica".
			</p>
			<Flex justify="flex-start" align="flex-end" wrap gap={ 3 } style={ { marginBottom: '16px' } }>
				<FlexItem style={ { minWidth: '200px' } }>
					<SelectControl
						label="Categoria"
						value={ categoria }
						options={ [
							{ label: null === categorie ? 'Caricamento…' : 'Seleziona una categoria', value: '' },
							...( categorie || [] ).map( ( cat ) => ( {
								label: `${ cat.name } (${ cat.count })`,
								value: cat.slug,
							} ) ),
						] }
						onChange={ setCategoria }
						__next40pxDefaultSize
					/>
				</FlexItem>
				<FlexItem style={ { minWidth: '140px' } }>
					<TextControl
						label="Percentuale (es. 10 o -10)"
						type="number"
						value={ percentuale }
						onChange={ ( valore ) => {
							setPercentuale( valore );
							setDelta( '' );
						} }
						__next40pxDefaultSize
					/>
				</FlexItem>
				<FlexItem style={ { minWidth: '140px' } }>
					<TextControl
						label="Delta fisso (es. 5 o -5)"
						type="number"
						value={ delta }
						onChange={ ( valore ) => {
							setDelta( valore );
							setPercentuale( '' );
						} }
						__next40pxDefaultSize
					/>
				</FlexItem>
				<FlexItem>
					<Button variant="primary" onClick={ calcola } isBusy={ caricamento }>
						Calcola anteprima
					</Button>
				</FlexItem>
			</Flex>

			{ /* Riga propria, non allineata con gli altri controlli: con più
			prodotti selezionati il campo va a capo e cresce in altezza - in
			una riga condivisa con align="flex-end" questo spingerebbe giù
			tutto il resto del form, qui invece cresce solo verso il basso. */ }
			<div style={ { maxWidth: '640px', marginBottom: '16px' } }>
				<FormTokenField
					label="Prodotti"
					value={ prodottiScelti }
					suggestions={ prodottiCategoria.map( etichettaProdotto ) }
					onChange={ ( token ) => {
						// Solo prodotti scelti dai suggerimenti: niente token liberi
						// che non corrisponderebbero a nessun id.
						const valide = prodottiCategoria.map( etichettaProdotto );
						setProdottiScelti( token.filter( ( t ) => valide.includes( t ) ) );
					} }
					disabled={ '' === categoria }
					placeholder={
						'' === categoria
							? 'Scegli prima una categoria'
							: caricamentoProdotti
							? 'Caricamento prodotti…'
							: 'Vuoto = tutta la categoria'
					}
					__experimentalExpandOnFocus
					__experimentalShowHowTo={ false }
					__next40pxDefaultSize
				/>
			</div>

			{ errore && (
				<Notice status="error" isDismissible={ false }>
					{ errore }
				</Notice>
			) }

			{ esito && (
				<Notice status="success" isDismissible={ false }>
					Prezzi applicati a { esito.aggiornati.length } prodotti.
				</Notice>
			) }

			{ caricamento && <Spinner /> }

			{ ! caricamento && anteprima && (
				<>
					{ anteprima.prodotti_variabili_esclusi.length > 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ anteprima.prodotti_variabili_esclusi.length } prodotti variabili esclusi (prezzo gestito dalle varianti):{ ' ' }
							{ anteprima.prodotti_variabili_esclusi.map( ( p ) => p.titolo ).join( ', ' ) }
						</Notice>
					) }

					{ 0 === anteprima.aggiornamenti.length && <p>Nessun prodotto semplice da aggiornare in questa categoria.</p> }

					{ anteprima.aggiornamenti.length > 0 && (
						<>
							<div style={ { margin: '16px 0', display: 'flex', gap: '12px' } }>
								<Button
									variant="link"
									onClick={ () => setSelezionati( new Set( anteprima.aggiornamenti.map( ( r ) => r.id ) ) ) }
								>
									Seleziona tutti
								</Button>
								<Button variant="link" onClick={ () => setSelezionati( new Set() ) }>
									Deseleziona tutti
								</Button>
							</div>

							<table className="wp-list-table widefat fixed striped" style={ { marginBottom: '16px' } }>
								<thead>
									<tr>
										<th style={ { width: '40px' } }></th>
										<th>Prodotto</th>
										<th>Prezzo attuale</th>
										<th>Prezzo nuovo</th>
									</tr>
								</thead>
								<tbody>
									{ anteprima.aggiornamenti.map( ( riga ) => (
										<tr key={ riga.id }>
											<td>
												<CheckboxControl
													__nextHasNoMarginBottom
													checked={ selezionati.has( riga.id ) }
													onChange={ ( selezionato ) => alternaSelezione( riga.id, selezionato ) }
												/>
											</td>
											<td>
												<LinkProdotto id={ riga.id }>{ riga.titolo }</LinkProdotto>
											</td>
											<td>{ riga.prezzo_regolare_attuale }</td>
											<td>{ riga.prezzo_regolare_nuovo }</td>
										</tr>
									) ) }
								</tbody>
							</table>

							<Button variant="primary" onClick={ applica } isBusy={ applicazione }>
								Applica a { selezionati.size } prodotti selezionati
							</Button>
						</>
					) }
				</>
			) }
		</div>
	);
}
