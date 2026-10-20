#!/usr/bin/env bash
# Esegue comandi PHP (composer/phpcs/phpunit) dentro il container litespeed,
# dove PHP e' installato - l'host non ha PHP. Uso: bin/dev.sh phpcs | phpcbf | phpunit | php <...>
set -euo pipefail

CONTAINER=litespeed
PLUGIN_DIR=/var/www/vhosts/localhost/html/wp-content/plugins/push_contempt

case "${1:-}" in
    phpcs|phpcbf|phpunit)
        exec docker exec -w "$PLUGIN_DIR" "$CONTAINER" php "vendor/bin/$1" "${@:2}"
        ;;
    php)
        exec docker exec -w "$PLUGIN_DIR" "$CONTAINER" php "${@:2}"
        ;;
    *)
        echo "Uso: bin/dev.sh {phpcs|phpcbf|phpunit|php} [argomenti]" >&2
        exit 1
        ;;
esac
