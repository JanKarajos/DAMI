# OZ DAMI – rezervačný systém (PHP 8.3 + Apache + SQLite)
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" gd exif \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod headers \
 && echo "ServerTokens Prod\nServerSignature Off" > /etc/apache2/conf-enabled/zz-dami.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-dami.ini
COPY docker/entrypoint.sh /usr/local/bin/dami-entrypoint
RUN chmod +x /usr/local/bin/dami-entrypoint

WORKDIR /var/www/html
EXPOSE 80
ENTRYPOINT ["dami-entrypoint"]
CMD ["apache2-foreground"]
