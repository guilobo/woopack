#!/bin/sh

set -eu

BRANCH="main"
APP_PATH="/home/dh_j2957h/apps/woopack"
WEB_PATH="/home/dh_j2957h/woopack.gel5.com"
PHP_BIN="/usr/local/bin/php-8.4"
COMPOSER_BIN="/home/dh_j2957h/.php/composer/composer"

while read oldrev newrev refname
do
  if [ "$refname" != "refs/heads/$BRANCH" ]; then
    continue
  fi

  mkdir -p "$APP_PATH"
  git --work-tree="$APP_PATH" --git-dir="$(pwd)" checkout -f "$BRANCH"

  if [ -x "$COMPOSER_BIN" ]; then
    cd "$APP_PATH"
    "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
  fi

  mkdir -p \
    "$APP_PATH/bootstrap/cache" \
    "$APP_PATH/storage/framework/cache/data" \
    "$APP_PATH/storage/framework/sessions" \
    "$APP_PATH/storage/framework/views" \
    "$APP_PATH/storage/logs" \
    "$WEB_PATH"

  chmod -R u+rwX "$APP_PATH/storage" "$APP_PATH/bootstrap/cache"

  rsync -a --delete --exclude='.well-known' "$APP_PATH/public/" "$WEB_PATH/"

  if [ -f "$APP_PATH/public/.htaccess" ]; then
    cp "$APP_PATH/public/.htaccess" "$WEB_PATH/.htaccess"
  fi

  cat > "$WEB_PATH/index.php" <<PHP
<?php

use Illuminate\\Foundation\\Application;
use Illuminate\\Http\\Request;

define('LARAVEL_START', microtime(true));

if (file_exists(\$maintenance = '$APP_PATH/storage/framework/maintenance.php')) {
    require \$maintenance;
}

require '$APP_PATH/vendor/autoload.php';

/** @var Application \$app */
\$app = require_once '$APP_PATH/bootstrap/app.php';

\$app->handleRequest(Request::capture());
PHP

  cd "$APP_PATH"
  "$PHP_BIN" artisan migrate --force
  "$PHP_BIN" artisan config:clear
  "$PHP_BIN" artisan route:clear
  "$PHP_BIN" artisan view:clear
  "$PHP_BIN" artisan config:cache
  "$PHP_BIN" artisan route:cache
  "$PHP_BIN" artisan view:cache
done
