# syntax=docker/dockerfile:1
FROM php:8.2-apache

# pdo_mysql build sẵn trên mysqlnd (không cần lib dev ngoài).
# Entrypoint chờ MySQL bằng chính PDO của PHP (xem docker-entrypoint.sh) nên
# không cần cài thêm mysql-client — image nhẹ hơn và tránh lệch hành vi TLS
# giữa CLI client (MariaDB) và mysqlnd khi MySQL 8 dùng self-signed cert mặc định.
RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite headers

# DocumentRoot -> public/, cho phép .htaccess override (mod_rewrite)
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && printf '<Directory /var/www/html/public>\n\tAllowOverride All\n\tRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/app.conf \
    && a2enconf app

# Ẩn version PHP/Apache khỏi response header — giảm bề mặt fingerprint cho app public.
# Sửa thẳng vào security.conf gốc (không thêm file riêng) vì conf-enabled nạp theo thứ tự
# alphabet — security.conf (mặc định ServerTokens OS/ServerSignature On) nạp SAU mọi file
# "security-*" nên sẽ ghi đè ngược lại nếu tách file riêng.
RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/no-expose-php.ini \
    && sed -ri 's/^ServerTokens OS/ServerTokens Prod/; s/^ServerSignature On/ServerSignature Off/' /etc/apache2/conf-available/security.conf

WORKDIR /var/www/html

COPY public ./public
COPY database ./database
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p /var/www/html/public/uploads \
    && chown -R www-data:www-data /var/www/html/public/uploads

# PORT mặc định 3000 — khớp default health-check của Vibe Host (Git URL deploy
# không đọc vibehost.json, giả định cổng kiểu Node.js). Đổi Listen/VirtualHost
# theo $PORT ngay trong docker-entrypoint.sh (chạy lúc container start, không
# phải build time) để 1 image chạy đúng trên cả platform đòi cổng khác nhau.
ENV PORT=3000
EXPOSE 3000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
