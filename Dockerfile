FROM php:8.2-fpm

RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources && apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev libpq-dev && docker-php-ext-install curl pdo_pgsql && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY docker/php.ini /usr/local/etc/php/conf.d/nilaiku.ini
COPY . .

CMD ["php", "-S", "0.0.0.0:8000", "-t", ".", "api/router.php"]
