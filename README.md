# push_contempt

Plugin WordPress/WooCommerce con una pagina di amministrazione a tab per
gestire scorte, prezzi in blocco, report vendite, salute del catalogo, SEO
e caricamento prodotti. Le operazioni "meccaniche" (tutto tranne
l'estrazione/scrittura di testi con l'AI) funzionano da sole, chiamando la
REST API nativa del plugin — niente server esterno, niente costo AI
lato negozio. Le due operazioni che richiedono davvero un assistente AI
(lettura di cartelle prodotto locali, scrittura testi SEO) passano dal
server MCP companion `push_contempt_mcp` (repo separato): l'utente collega
il proprio assistente AI (Claude Code, Cursor,
Windsurf o altro client MCP) con la propria sottoscrizione, il plugin non
paga né gestisce nessuna AI lato server.

## Requisiti

- WordPress 6.4+, PHP 8.1+
- WooCommerce attivo
- [Yoast SEO](https://wordpress.org/plugins/wordpress-seo/) attivo, se si
  vuole che i campi SEO letti/scritti dal tab "SEO" (titolo SEO, meta
  description, parola chiave focus) abbiano effetto reale in SERP — senza
  Yoast il plugin funziona comunque, solo quei campi restano inutilizzati

## Installazione

### Opzione A — da wp-admin, senza terminale (consigliata per un sito già in produzione)

Dalla pagina **Releases** di questo repo scarica lo zip dell'ultima
versione (`push_contempt.zip`, allegato automaticamente a ogni release —
già compilato, pronto all'uso). Poi wp-admin → Plugin → Aggiungi nuovo →
Carica plugin → seleziona lo zip → Installa → Attiva.

**Non funziona con il bottone "Code → Download ZIP"** della pagina
principale del repo: quello scarica il codice sorgente, che non contiene
`build/` (il JS compilato, ignorato da git) — il plugin si attiverebbe ma
la sua pagina di amministrazione resterebbe vuota. Va preso lo zip dalla
pagina Releases.

### Opzione B — da sorgente (per sviluppo, o se non ci sono ancora release)

```sh
cd wp-content/plugins
git clone https://github.com/ness1-contempt/push_contempt.git
cd push_contempt
npm install
npm run build   # genera build/, richiesto: senza non c'è UI admin
```

Poi attiva "Push Contempt" da wp-admin → Plugin. Compare una nuova voce di
menu "Push Contempt" (richiede la capability `manage_woocommerce`, quindi
visibile ad Admin e Store Manager).

`composer install` **non serve** per usare il plugin: le dipendenze in
`composer.json` sono tutte di sviluppo (phpcs, phpunit, Brain Monkey),
servono solo per lavorare sul codice (vedi Comandi).

### Aggiornamenti

Il plugin non si aggiorna da solo: per passare a una versione nuova si scarica
lo zip dalla pagina Releases e lo si carica da wp-admin come per
l'installazione (Plugin → Aggiungi nuovo → Carica plugin; WordPress propone di
sostituire la versione installata). Un controllo automatico delle release
GitHub è stato provato e tolto: su alcuni hosting (per esempio Altervista) il
server può contattare solo una lista di siti consentiti, e GitHub non c'è.

## Primo collegamento con l'assistente AI

Dal tab **Impostazioni** si genera con un click una Application Password
nativa di WordPress (non scade da sola). Copia i tre valori mostrati
(`WP_SITE_URL`/`WP_APP_USER`/`WP_APP_PASSWORD`) nel `.env` di
`push_contempt_mcp` sul computer dove gira il proprio assistente AI — da
lì in poi i tab "Carica prodotti" e "SEO" mostrano se il collegamento è
attivo e preparano il messaggio pronto da incollare in chat.

## Il tab SEO: versioni e confronto con i concorrenti

Il tab ha due parti, entrambe con una guida a scomparsa nella pagina stessa.

### Le tre versioni dei campi SEO

Quando l'assistente AI scrive titolo SEO, meta description e parola chiave
focus di un prodotto, il plugin tiene tre versioni, così non si perde niente:

- **Base**: il punto di partenza, cioè com'era il prodotto prima del primo
  intervento (di norma quello che c'era in Yoast). Non cambia mentre si lavora.
  Solo l'utente può ridefinirla, con «Usa l'attuale come nuova base»: da quel
  momento la base non è più l'originale di Yoast e quello stato non si
  recupera più (la conferma lo dice, e il badge della base passa da blu
  «Originale» ad arancione «Impostata da te»).
- **Precedente**: la versione da cui si viene, cioè quella che c'era prima
  dell'ultima scrittura o dell'ultimo ripristino. Si prende dai valori vivi di
  Yoast, quindi comprende anche le correzioni fatte a mano.
- **Attuale**: quella in uso adesso in Yoast. Non è copiata: si legge dal vivo.
  L'origine mostrata è chi ha scritto per ultimo (es. «concorrente x.it»),
  oppure «modifica manuale» se qualcuno l'ha cambiata a mano da allora.

Dalla colonna «Versioni» → «Mostra versioni» si vedono le tre versioni
affiancate. «Torna alla base» e «Torna al precedente» rimettono quei testi in
Yoast; la versione lasciata diventa il «precedente», quindi si può sempre
tornare indietro. Per più prodotti si spunta «Seleziona» e si usa la barra
sopra la tabella; ogni azione chiede conferma. Le versioni riguardano solo i
tre campi SEO (non la descrizione) e un prodotto le ha solo dopo che il plugin
ha scritto i suoi campi SEO almeno una volta.

### Concorrenti e confronto

La sezione «Concorrenti e confronto» mostra i siti che l'utente ha fatto
analizzare al proprio assistente AI, con un punteggio da 0 a 9 per area
(titoli, meta description, intestazioni, dati strutturati, contenuto, aspetti
tecnici).

1. **Si chiede l'analisi** all'assistente, ad es. «Analizza il sito
   https://… e dimmi com'è messo con la SEO». Gira sul computer dell'utente
   (non sul sito WordPress) e richiede da qualche decina di secondi a un paio
   di minuti.
2. **Il risultato compare nella tabella.** Cliccando un sito si vedono le prove
   dietro ogni voto e i limiti che hanno abbassato un'area.
3. **Si fa analizzare anche il proprio sito**: compare per primo come «Il tuo
   sito» e gli altri mostrano la differenza area per area, (+2) = due punti
   meglio di te, (−1) = uno peggio.
4. **L'utente decide** se e per quali aree usare un concorrente come
   ispirazione, dal pannello «Riscrivi con ispirazione da un concorrente»: si
   spuntano i prodotti nella prima colonna della tabella, si sceglie il sito
   analizzato, le aree (titoli SEO e/o meta description: le due che
   corrispondono ai campi scritti dall'assistente) e, se si vuole, il livello
   massimo di somiglianza accettato (da 0, nessuna, a 9, copia). Il pannello
   non scrive niente: prepara un messaggio da incollare all'assistente, che
   scrive testi originali, li controlla con `controlla_somiglianza` prima di
   applicarli (se il livello non è stato fissato lo sceglie l'utente dopo aver
   visto i risultati) e li applica indicando il concorrente come origine. Le
   modifiche si annullano dalle versioni.

Il punteggio misura solo la struttura (non posizionamento, autorevolezza,
velocità né qualità dei testi) ed è solo un riferimento: un sito mediocre può
essere un buon modello per una sola area. Al plugin arrivano solo i punteggi e
le prove, mai i testi delle pagine analizzate.

## Comandi

| Cosa | Comando |
|---|---|
| Installare le dipendenze JS | `npm install` |
| Compilare l'admin React | `npm run build` |
| Admin React in watch mode | `npm run start` |
| Lint JS | `npm run lint:js` |
| Installare le dipendenze PHP di sviluppo | `docker exec -w <percorso-plugin-nel-container> <container> composer install` |
| Lint PHP (WPCS) | `bin/dev.sh phpcs` |
| Fix automatico lint PHP | `bin/dev.sh phpcbf` |
| Test PHP (logica pura) | `bin/dev.sh phpunit` |
| Test PHP di integrazione (controller REST) | vedi sotto |
| Test JS | `npm run test:unit` |

`bin/dev.sh` esegue i comandi PHP dentro un container Docker che ha PHP
installato (pensato per l'ambiente di sviluppo `ols-docker-env`): se il tuo
host ha PHP nativamente, usa `composer`/`vendor/bin/phpcs`/`vendor/bin/phpunit`
direttamente invece di passare dallo script.

### Test di integrazione

I controller REST si provano su WordPress e WooCommerce veri (`tests/Integration/`,
`phpunit-integration.xml.dist`): richieste interne con `rest_do_request()`, permessi,
validazione, meta dei prodotti e opzioni, su un database di prova. Servono WordPress e
WooCommerce scaricati in una cartella a parte e un database vuoto **il cui nome contiene
"test"** (la configurazione rifiuta gli altri, perché a ogni esecuzione viene svuotato):

```bash
bin/install-wp-tests.sh /tmp/wp-core          # WordPress e WooCommerce alle versioni provate in CI
export WP_TESTS_WP_DIR=/tmp/wp-core WP_TESTS_DB_HOST=127.0.0.1 \
       WP_TESTS_DB_NAME=wordpress_test WP_TESTS_DB_USER=<utente> WP_TESTS_DB_PASSWORD=<password>
vendor/bin/phpunit -c phpunit-integration.xml.dist
```

Nell'ambiente `ols-docker-env` si esegue nel container `litespeed` (`bin/dev.sh php vendor/bin/phpunit -c
phpunit-integration.xml.dist` con le variabili passate da `docker exec -e`), con `WP_TESTS_DB_HOST=mysql`. Non usare
mai la cartella di WordPress del sito di sviluppo come `WP_TESTS_WP_DIR`: il suo `object-cache.php` punta alla
Redis vera. In CI è il job `integration`; le versioni di WordPress e WooCommerce sono fissate nel workflow.

## Struttura

- `push-contempt.php` — bootstrap: header plugin, registrazione delle rotte REST, registrazione della pagina admin
- `includes/Rest/` — un controller per endpoint REST (vedi sotto), sempre con `permission_callback` su `manage_woocommerce` e API native di WooCommerce/WordPress, mai SQL diretto salvo dove esplicitamente giustificato
- `includes/CatalogHealth.php` / `includes/PricingCalculator.php` — logica di calcolo pura, senza dipendenze da WordPress, testata con PHPUnit
- `includes/Admin/MenuPage.php` — registrazione menu e caricamento della app React
- `src/components/` — app React (`@wordpress/scripts`), una tab per ciascuna area
- `tests/` — PHPUnit sulle classi di logica pura; `tests/Integration/` — i controller REST su WordPress e WooCommerce veri

## Endpoint REST (namespace `push-contempt/v1`)

| Rotta | Metodo | Cosa fa |
|---|---|---|
| `/scorte` | GET | prodotti esauriti o sotto soglia scorte |
| `/vendite` | GET | report vendite per prodotto in un periodo |
| `/ordini` | GET | elenco ordini per stato (nativi WooCommerce) e finestra temporale, senza dati personali |
| `/ordini/<id>` | GET | dettaglio di un ordine, con dati del cliente |
| `/salute-catalogo` | GET | prodotti pubblicati ma incompleti |
| `/prezzi/calcola` | GET | anteprima aggiornamento prezzi per categoria |
| `/prezzi/applica` | POST | applica l'aggiornamento prezzi approvato |
| `/seo/stato` | GET | vista d'insieme SEO su tutto il catalogo |
| `/seo/prodotto` | GET/POST | lettura/scrittura campi Yoast di un prodotto (tiene base e precedente) |
| `/concorrenti` | GET/POST/DELETE | analisi SEO dei siti concorrenti inviate dal server MCP (punteggi per area), mostrate nella tab SEO |
| `/seo/ripristina` | POST | `ids` + `versione` (`base` o `precedente`): rimette quella versione dei campi Yoast; la versione lasciata diventa il precedente |
| `/seo/base` | POST | `ids`: usa i valori attuali come nuova base (non è più l'originale di Yoast) |
| `/prodotti` | POST | crea un prodotto in bozza (titolo, descrizione, prezzo, categoria, immagini, SEO) |
| `/prodotti/bozze` | GET | prodotti creati e non ancora pubblicati |
| `/credenziali` | GET/POST | stato/generazione della Application Password per `push_contempt_mcp` |

Tutte richiedono autenticazione (sessione wp-admin per le chiamate del
browser, Application Password per le chiamate di `push_contempt_mcp`) e la
capability `manage_woocommerce`.

## Note

- Il React admin usa `wp-scripts` quindi niente build step custom (webpack
  è configurato da `@wordpress/scripts`).
- I tab "Carica prodotti" e "SEO" (parte di scrittura AI) non eseguono mai
  nulla da soli: preparano un messaggio pronto da incollare nel proprio
  assistente AI, che poi chiama `push_contempt_mcp`. Il browser non può
  leggere file dal filesystem del cliente né eseguire un modello
  linguistico, quindi questo passaggio non è automatizzabile da qui.
- I prodotti creati via `/prodotti` nascono sempre in stato bozza, mai
  pubblicati automaticamente: restano nell'elenco "Prodotti in bozza" del
  tab "Carica prodotti" finché non vengono rivisti e pubblicati a mano da
  wp-admin.
