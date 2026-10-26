#!/usr/bin/env bash
# Prepara WordPress e WooCommerce per i test di integrazione (tests/Integration):
# scarica WordPress e il plugin WooCommerce nella cartella indicata, se non ci sono gia'.
# Il database si crea a parte (in CI e' il servizio MySQL, in locale vedi README).
#
# Uso: bin/install-wp-tests.sh <cartella>
# Variabili: WP_VERSION (predefinita: quella provata in CI), WC_VERSION.
set -euo pipefail

DIR="${1:?Uso: bin/install-wp-tests.sh <cartella>}"
WP_VERSION="${WP_VERSION:-7.1.3}"
WC_VERSION="${WC_VERSION:-11.2.1}"

if [ ! -f "$DIR/wp-settings.php" ]; then
    mkdir -p "$DIR"
    curl -fsSL "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" | tar -xz --strip-components=1 -C "$DIR"
fi

if [ ! -f "$DIR/wp-content/plugins/woocommerce/woocommerce.php" ]; then
    TMP="$(mktemp -d)"
    curl -fsSL -o "$TMP/wc.zip" "https://downloads.wordpress.org/plugin/woocommerce.${WC_VERSION}.zip"
    unzip -q "$TMP/wc.zip" -d "$DIR/wp-content/plugins"
    rm -rf "$TMP"
fi

echo "WordPress ${WP_VERSION} e WooCommerce ${WC_VERSION} pronti in $DIR"
