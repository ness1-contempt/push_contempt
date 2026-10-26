# Test end-to-end della tab SEO

Playwright sulla tab SEO di wp-admin, su un **sito WordPress usa-e-getta** (WordPress + WooCommerce +
questo plugin compilato) con un database di prova che viene svuotato a ogni `setup`. Non toccano mai il
sito di sviluppo né quello di produzione. I dati (prodotti, analisi dei concorrenti) si preparano con la
REST API del plugin; l'interfaccia si usa come la userebbe una persona; l'esito si controlla anche sul
sito (valori SEO realmente scritti).

Scenari: elenco e filtri, le tre versioni (torna alla base, al precedente, nuova base, azioni in blocco,
conferme annullate), tabella dei concorrenti (il tuo sito, differenze, dettaglio, eliminazione, aggiorna),
pannello «Riscrivi con ispirazione» (scelte dell'utente, messaggio generato, copia), guide, permessi.

## Prerequisiti

- il plugin compilato: `npm ci && npm run build` nella cartella del plugin;
- PHP con `mysqli`, WP-CLI e un database vuoto il cui nome contiene `e2e` o `test`;
- `cd e2e && npm ci && npx playwright install chromium`.

## Esecuzione

```bash
# 1. il sito di prova (la variabile E2E_* sono descritte in site.sh)
E2E_DB_USER=<utente> E2E_DB_PASSWORD=<password> e2e/site.sh setup
e2e/site.sh serve &                      # http://127.0.0.1:8081

# 2. i test
cd e2e && npx playwright test            # E2E_BASE_URL per un altro indirizzo
npx playwright show-report               # dopo un errore: screenshot e trace
```

Nell'ambiente `ols-docker-env` PHP e WP-CLI stanno nel container `litespeed`: `site.sh` si esegue lì
(`docker exec -e E2E_HOST=<ip del container> -e E2E_DB_HOST=mysql ... litespeed bash e2e/site.sh setup`;
`docker exec -d ... serve` per il server) e i test, sull'host, usano `E2E_BASE_URL=http://<ip del container>:8081`.
Su un indirizzo che non è `localhost` il browser non offre gli appunti: il test «Copia messaggio» li simula
(in CI, su 127.0.0.1, sono quelli veri).

Utente di prova: `admin` / `e2e-password` (`E2E_ADMIN_PASSWORD` per cambiarla).

## Come sono fatti

- `site.sh` prepara e serve il sito (versioni di WordPress e WooCommerce come in `bin/install-wp-tests.sh`).
- `tests/helpers.js`: client REST con la sessione dell'amministratore, nomi unici per i dati di prova,
  selettori comuni (riga di un prodotto, scheda di una versione, avviso).
- Un solo lavoratore: i test condividono il sito. Ogni test crea prodotti con nomi unici e le analisi dei
  concorrenti vengono azzerate prima e dopo gli scenari che le contano.
- Le richieste verso altri siti (gravatar, font, telemetria) vengono bloccate: non servono e rallentano.
