import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import LinkProdotto from './LinkProdotto';
import MessaggioProntoAI from './MessaggioProntoAI';
import StatoCollegamentoAI from './StatoCollegamentoAI';
import useRestFetch from '../hooks/useRestFetch';
import { messaggioBlog } from '../lib/messaggioBlog';

const LINGUE = [
	{ label: 'Italiano', value: 'it' },
	{ label: 'English', value: 'en' },
	{ label: 'Français', value: 'fr' },
	{ label: 'Deutsch', value: 'de' },
	{ label: 'Español', value: 'es' },
];

const LUNGHEZZE = [ 600, 900, 1200, 1500, 2000 ].map( ( parole ) => ( {
	label: `circa ${ parole } parole`,
	value: String( parole ),
} ) );

/**
 * Le indicazioni del negozio per il blog (argomento, pubblico, tono, ...), salvate nel plugin:
 * le legge l'assistente AI con leggi_brief_blog, cosi' il messaggio da incollare resta corto.
 *
 * @param {Object}   props
 * @param {Function} props.onSalvato Chiamata con il brief salvato.
 */
function BriefBlog( { onSalvato } ) {
	const [ brief, setBrief ] = useState( null );
	const [ categorie, setCategorie ] = useState( [] );
	const [ errore, setErrore ] = useState( null );
	const [ salvato, setSalvato ] = useState( false );
	const [ inCorso, setInCorso ] = useState( false );

	useEffect( () => {
		apiFetch( { path: '/push-contempt/v1/blog/brief' } )
			.then( ( dati ) => {
				setBrief( dati );
				onSalvato( dati );
			} )
			.catch( ( err ) => setErrore( err.message || 'Errore nel caricamento del brief.' ) );
		apiFetch( { path: '/wp/v2/product_cat?per_page=100&_fields=id,name' } )
			.then( setCategorie )
			.catch( () => setCategorie( [] ) );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	if ( errore ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ errore }
			</Notice>
		);
	}
	if ( ! brief ) {
		return <Spinner />;
	}

	const cambia = ( campo ) => ( valore ) => {
		setSalvato( false );
		setBrief( { ...brief, [ campo ]: valore } );
	};
	const cambiaCategoria = ( id, scelta ) => {
		const altre = brief.categorie_prodotti.filter( ( x ) => x !== id );
		cambia( 'categorie_prodotti' )( scelta ? [ ...altre, id ] : altre );
	};

	const salva = () => {
		setInCorso( true );
		setErrore( null );
		apiFetch( { path: '/push-contempt/v1/blog/brief', method: 'POST', data: brief } )
			.then( ( dati ) => {
				setBrief( dati );
				onSalvato( dati );
				setSalvato( true );
			} )
			.catch( ( err ) => setErrore( err.message || 'Salvataggio non riuscito.' ) )
			.finally( () => setInCorso( false ) );
	};

	return (
		<div style={ { maxWidth: '640px', marginBottom: '24px' } }>
			<h3 style={ { marginTop: 0 } }>Brief del blog</h3>
			<TextControl
				label="Argomento"
				help="Di cosa deve parlare il blog (es. zaini e attrezzatura per il trekking)."
				value={ brief.argomento }
				onChange={ cambia( 'argomento' ) }
				__next40pxDefaultSize
			/>
			<TextControl
				label="Pubblico"
				help="A chi si rivolge (es. escursionisti alle prime armi)."
				value={ brief.pubblico }
				onChange={ cambia( 'pubblico' ) }
				__next40pxDefaultSize
			/>
			<TextControl
				label="Tono"
				help="Come scrivere (es. amichevole e pratico, senza tecnicismi)."
				value={ brief.tono }
				onChange={ cambia( 'tono' ) }
				__next40pxDefaultSize
			/>
			<div style={ { display: 'flex', gap: '16px', flexWrap: 'wrap' } }>
				<SelectControl
					label="Lingua"
					value={ brief.lingua }
					options={ LINGUE }
					onChange={ cambia( 'lingua' ) }
					__next40pxDefaultSize
				/>
				<SelectControl
					label="Lunghezza"
					value={ String( brief.lunghezza_parole ) }
					options={ LUNGHEZZE }
					onChange={ ( valore ) => cambia( 'lunghezza_parole' )( Number( valore ) ) }
					__next40pxDefaultSize
				/>
				<TextControl
					label="Articoli per volta"
					type="number"
					min={ 1 }
					max={ 10 }
					value={ brief.n_articoli }
					onChange={ ( valore ) => cambia( 'n_articoli' )( Number( valore ) ) }
					__next40pxDefaultSize
				/>
			</div>

			{ categorie.length > 0 && (
				<fieldset style={ { margin: '8px 0 16px', border: 0, padding: 0 } }>
					<legend style={ { fontWeight: 600, marginBottom: '8px' } }>
						Prodotti da far comparire (nessuna scelta = tutto il catalogo)
					</legend>
					{ categorie.map( ( categoria ) => (
						<CheckboxControl
							key={ categoria.id }
							label={ categoria.name }
							checked={ brief.categorie_prodotti.includes( categoria.id ) }
							onChange={ ( scelta ) => cambiaCategoria( categoria.id, scelta ) }
							__nextHasNoMarginBottom
						/>
					) ) }
				</fieldset>
			) }

			<TextareaControl
				label="Note per l'assistente"
				help="Regole in più: cosa evitare, come citare i prodotti, indicazioni AEO/GEO..."
				value={ brief.note }
				onChange={ cambia( 'note' ) }
				rows={ 4 }
			/>

			<Button variant="primary" onClick={ salva } isBusy={ inCorso } disabled={ inCorso }>
				Salva brief
			</Button>
			{ salvato && <span style={ { marginLeft: '12px' } }>Salvato ✓</span> }
		</div>
	);
}

/**
 * Bozze del blog in attesa di revisione: lo stato "draft" di WordPress e' l'elenco (vedi
 * BlogController::handle_elenco), niente tracciamento a parte. Un refresh manuale lascia
 * visibile la lista precedente.
 */
function BozzeBlog() {
	const { dati: articoli, errore, carica } = useRestFetch(
		() => '/push-contempt/v1/blog/articoli?stato=draft'
	);

	return (
		<div style={ { marginTop: '32px', maxWidth: '780px' } }>
			<div style={ { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' } }>
				<h3 style={ { margin: 0 } }>Articoli in bozza</h3>
				<Button variant="link" onClick={ carica }>
					Aggiorna
				</Button>
			</div>
			<p style={ { marginBottom: '16px', color: '#50575e' } }>
				Articoli scritti dal tuo assistente AI e non ancora pubblicati: aprili per rivederli (testo,
				fonti, immagini, SEO) e pubblicali da wp-admin quando sono pronti. Le bozze vanno sempre lette
				da una persona prima di uscire.
			</p>

			{ errore && (
				<Notice status="error" isDismissible={ false }>
					{ errore }
				</Notice>
			) }

			{ ! articoli && ! errore && <Spinner /> }

			{ articoli && 0 === articoli.length && <p>Nessun articolo in bozza al momento.</p> }

			{ articoli && articoli.length > 0 && (
				<table className="widefat striped">
					<thead>
						<tr>
							<th>Titolo</th>
							<th>Parole</th>
							<th>Link interni</th>
							<th>Link esterni</th>
							<th>Prodotti collegati</th>
						</tr>
					</thead>
					<tbody>
						{ articoli.map( ( articolo ) => (
							<tr key={ articolo.id }>
								<td>
									<LinkProdotto id={ articolo.id }>{ articolo.titolo }</LinkProdotto>
								</td>
								<td>{ articolo.n_parole }</td>
								<td>{ articolo.n_link_interni ?? '—' }</td>
								<td>{ articolo.n_link_esterni ?? '—' }</td>
								<td>{ articolo.prodotti_collegati.length }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}

/**
 * Blog: la scrittura richiede l'AI, che sta sul computer del cliente (il plugin non ne usa).
 * Questa tab salva il brief nel plugin, prepara il messaggio da incollare nella chat
 * dell'assistente e mostra le bozze che ha lasciato.
 *
 * @param {Object}   props
 * @param {Function} props.onNavigate Cambia tab (serve al link verso Impostazioni).
 */
export default function BlogTab( { onNavigate } ) {
	const [ brief, setBrief ] = useState( null );

	return (
		<div style={ { marginTop: '16px' } }>
			<p style={ { maxWidth: '780px', marginBottom: '16px', color: '#50575e' } }>
				Gli articoli non si scrivono da qui: li scrive il tuo assistente AI (Claude Code, Cursor, Windsurf
				e simili, con push_contempt_mcp configurato) e li lascia in bozza. Qui definisci di cosa deve
				parlare il blog, copi il messaggio da incollare nella chat e rivedi le bozze.
			</p>

			<StatoCollegamentoAI onNavigate={ onNavigate } />

			<BriefBlog onSalvato={ setBrief } />

			{ brief && brief.compilato ? (
				<MessaggioProntoAI testo={ messaggioBlog( brief.n_articoli ) } />
			) : (
				brief && <p style={ { color: '#50575e' } }>Scrivi l'argomento e salva il brief per avere il messaggio pronto.</p>
			) }

			<BozzeBlog />
		</div>
	);
}
