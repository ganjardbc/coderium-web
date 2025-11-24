#!/bin/sh

# Set permissions for storage and bootstrap/cache directories
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Execute the Docker CMD
exec "$@"
