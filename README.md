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
| Test PHP | `bin/dev.sh phpunit` |

`bin/dev.sh` esegue i comandi PHP dentro un container Docker che ha PHP
installato (pensato per l'ambiente di sviluppo `ols-docker-env`): se il tuo
host ha PHP nativamente, usa `composer`/`vendor/bin/phpcs`/`vendor/bin/phpunit`
direttamente invece di passare dallo script.

## Struttura

- `push-contempt.php` — bootstrap: header plugin, registrazione delle rotte REST, registrazione della pagina admin
- `includes/Rest/` — un controller per endpoint REST (vedi sotto), sempre con `permission_callback` su `manage_woocommerce` e API native di WooCommerce/WordPress, mai SQL diretto salvo dove esplicitamente giustificato
- `includes/CatalogHealth.php` / `includes/PricingCalculator.php` — logica di calcolo pura, senza dipendenze da WordPress, testata con PHPUnit
- `includes/Admin/MenuPage.php` — registrazione menu e caricamento della app React
- `src/components/` — app React (`@wordpress/scripts`), una tab per ciascuna area
- `tests/` — PHPUnit sulle classi di logica pura

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
