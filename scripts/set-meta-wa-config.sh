#!/bin/sh

set -eu

CFG_ID="${1:-}"

if [ "$CFG_ID" = "" ]; then
  echo "usage: sh set-meta-wa-config.sh <META_WA_CONFIG_ID>" >&2
  exit 2
fi

APP_PATH="/home/dh_j2957h/apps/woopack"
ENV_FILE="$APP_PATH/.env"
PHP_BIN="/usr/local/bin/php-8.4"

if [ ! -f "$ENV_FILE" ]; then
  echo "env_missing" >&2
  exit 1
fi

tmp="$ENV_FILE.tmp.$$"

# Rewrite to ensure we end with a single META_WA_CONFIG_ID entry.
grep -v '^META_WA_CONFIG_ID=' "$ENV_FILE" > "$tmp"
printf "META_WA_CONFIG_ID=%s\n" "$CFG_ID" >> "$tmp"
mv "$tmp" "$ENV_FILE"

cd "$APP_PATH"

$PHP_BIN artisan config:clear >/dev/null
$PHP_BIN artisan route:clear >/dev/null
$PHP_BIN artisan view:clear >/dev/null
$PHP_BIN artisan config:cache >/dev/null
$PHP_BIN artisan route:cache >/dev/null
$PHP_BIN artisan view:cache >/dev/null

echo "meta_wa_config_id_set"

