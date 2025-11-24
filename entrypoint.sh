#!/bin/sh

# Wait for database to be ready
echo "Waiting for database connection..."
php artisan wait:db 2>/dev/null || sleep 5

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Clear all caches first
echo "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Cache config and routes for production
echo "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions for storage and bootstrap/cache directories
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Execute the Docker CMD
exec "$@"
