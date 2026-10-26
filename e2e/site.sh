#!/usr/bin/env bash
# Sito WordPress usa-e-getta per i test end-to-end: WordPress + WooCommerce + questo plugin,
# su un database di prova che viene svuotato a ogni "setup", servito dal server integrato di PHP.
# Non tocca mai il sito di sviluppo.
#
# Uso:  e2e/site.sh setup   prepara tutto (scarica, installa, attiva)
#       e2e/site.sh serve   avvia il server (resta in primo piano)
#
# Variabili (tra parentesi il predefinito):
#   E2E_DIR (/tmp/wp-e2e)     dove mettere WordPress
#   E2E_HOST (127.0.0.1)      indirizzo con cui il browser raggiunge il sito
#   E2E_PORT (8081)           porta del server
#   E2E_DB_NAME (wordpress_e2e), E2E_DB_USER (root), E2E_DB_PASSWORD (vuota), E2E_DB_HOST (127.0.0.1)
#   E2E_ADMIN_PASSWORD (e2e-password)   utente "admin"
#   WP_CLI (wp)               comando di WP-CLI
#   WP_VERSION, WC_VERSION    versioni (vedi bin/install-wp-tests.sh)
set -euo pipefail

RADICE="$(cd "$(dirname "$0")/.." && pwd)"
E2E_DIR="${E2E_DIR:-/tmp/wp-e2e}"
E2E_HOST="${E2E_HOST:-127.0.0.1}"
E2E_PORT="${E2E_PORT:-8081}"
E2E_DB_NAME="${E2E_DB_NAME:-wordpress_e2e}"
E2E_DB_USER="${E2E_DB_USER:-root}"
E2E_DB_PASSWORD="${E2E_DB_PASSWORD:-}"
E2E_DB_HOST="${E2E_DB_HOST:-127.0.0.1}"
E2E_ADMIN_PASSWORD="${E2E_ADMIN_PASSWORD:-e2e-password}"
WP_CLI="${WP_CLI:-wp}"

# Il database viene svuotato: mai un nome che non sia di prova.
case "$E2E_DB_NAME" in
    *e2e* | *test*) ;;
    *) echo "Rifiuto di usare il database '$E2E_DB_NAME': il nome deve contenere 'e2e' o 'test'." >&2; exit 1 ;;
esac

wpcli() { "$WP_CLI" --allow-root --path="$E2E_DIR" "$@"; }

case "${1:-}" in
    setup)
        "$RADICE/bin/install-wp-tests.sh" "$E2E_DIR"
        [ -f "$E2E_DIR/wp-config.php" ] || wpcli config create --skip-check --force \
            --dbname="$E2E_DB_NAME" --dbuser="$E2E_DB_USER" --dbpass="$E2E_DB_PASSWORD" --dbhost="$E2E_DB_HOST"
        wpcli config set WP_ENVIRONMENT_TYPE local --type=constant
        # Svuota il database (db clean carica WordPress, che qui non e' ancora installato).
        { echo 'SET FOREIGN_KEY_CHECKS=0;'
          wpcli db query "SELECT CONCAT('DROP TABLE IF EXISTS \`', table_name, '\`;') FROM information_schema.tables WHERE table_schema = DATABASE()" --skip-column-names
        } | wpcli db query
        wpcli core install --url="http://$E2E_HOST:$E2E_PORT" --title="E2E" --admin_user=admin \
            --admin_password="$E2E_ADMIN_PASSWORD" --admin_email=admin@example.org --skip-email
        ln -sfn "$RADICE" "$E2E_DIR/wp-content/plugins/push_contempt"
        wpcli plugin activate woocommerce push_contempt
        wpcli eval 'WC_Install::install();'
        wpcli transient delete _wc_activation_redirect || true
        echo "Sito pronto: http://$E2E_HOST:$E2E_PORT (utente admin)"
        ;;
    serve)
        PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}" exec php -S "0.0.0.0:$E2E_PORT" -t "$E2E_DIR"
        ;;
    *)
        echo "Uso: e2e/site.sh {setup|serve}" >&2
        exit 1
        ;;
esac
