#!/bin/sh
set -e

MAX_TRIES=30
SLEEP_SECONDS=2

echo "[entrypoint] Chờ MySQL sẵn sàng tại ${DB_HOST:-127.0.0.1}:${DB_PORT:-3306} ..."
i=0
# Dùng PDO của chính PHP (mysqlnd) để test kết nối — khớp 100% với cách app thật sự
# kết nối lúc chạy. mysqladmin/CLI client (MariaDB) không dùng vì nó strict-verify
# TLS theo cách khác với mysqlnd, dẫn tới false-negative với self-signed cert mặc định của MySQL 8.
until php -r '
$dsn = sprintf(
    "mysql:host=%s;port=%s;dbname=%s",
    getenv("DB_HOST") ?: "127.0.0.1",
    getenv("DB_PORT") ?: "3306",
    getenv("DB_DATABASE") ?: ""
);
try {
    new PDO($dsn, getenv("DB_USERNAME") ?: "", getenv("DB_PASSWORD") ?: "", [PDO::ATTR_TIMEOUT => 3]);
    exit(0);
} catch (Throwable $e) {
    exit(1);
}
' >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -ge "$MAX_TRIES" ]; then
        echo "[entrypoint] LỖI: MySQL không sẵn sàng sau $((MAX_TRIES * SLEEP_SECONDS))s. Dừng khởi động." >&2
        exit 1
    fi
    echo "[entrypoint] MySQL chưa sẵn sàng (lần $i/$MAX_TRIES), thử lại sau ${SLEEP_SECONDS}s..."
    sleep "$SLEEP_SECONDS"
done
echo "[entrypoint] MySQL đã sẵn sàng."

mkdir -p /var/www/html/public/uploads
chown -R www-data:www-data /var/www/html/public/uploads || true

# Apache mặc định Listen 80 (build time) — đổi theo $PORT lúc runtime để 1 image
# chạy đúng trên mọi nền tảng đòi cổng khác nhau (Vibe Host Git-URL deploy mặc
# định healthcheck cổng 3000, nền tảng khác có thể set PORT khác qua env).
APP_PORT="${PORT:-3000}"
echo "[entrypoint] Cấu hình Apache lắng nghe cổng ${APP_PORT}..."
sed -ri "s/^Listen .*/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "[entrypoint] Chạy migrate + seed/update admin (database/init.php)..."
php /var/www/html/database/init.php

exec "$@"
