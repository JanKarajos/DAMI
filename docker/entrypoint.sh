#!/bin/sh
# Priečinky na zápis (databáza, fotky) musia patriť používateľovi webového servera.
set -e
cd /var/www/html
mkdir -p data uploads/therapists
chown -R www-data:www-data data uploads
exec docker-php-entrypoint "$@"
