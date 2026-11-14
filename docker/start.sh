#!/bin/sh
set -eu
export PORT="${PORT:-10000}"
case "$PORT" in
    ''|*[!0-9]*) echo 'PORT debe ser numérico.' >&2; exit 1 ;;
esac
if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
    echo 'PORT fuera de rango.' >&2
    exit 1
fi
php -r '
$key = getenv("APP_KEY") ?: "";
if (str_starts_with($key, "base64:")) $key = base64_decode(substr($key, 7), true);
if (!is_string($key) || strlen($key) !== 32) {
    fwrite(STDERR, "APP_KEY inválida para AES-256-CBC.\n");
    exit(1);
}'
umask 077
php artisan config:cache
php artisan route:cache
exec apache2 -f /etc/apache2/fit-home.conf -DFOREGROUND
