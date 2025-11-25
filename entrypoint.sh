#!/bin/sh
set -e

# Wait for database to be ready (best-effort)
echo "Waiting for database connection..."
php artisan wait:db 2>/dev/null || sleep 5

echo "Running database migrations..."
php artisan migrate --force

echo "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo "Optimizing applications..."
php artisan config:cache
php artisan route:cache
pho artisan view:cache

# Ensure storage directories exist
echo "Ensuring storage directories..."
mkdir -p storage/app/public

# Create storage symlink idempotently
if [ ! -L public/storage ]; then
	if [ -e public/storage ]; then
		echo "public/storage exists and is not a symlink — renaming to public/storage.bak"
		mv public/storage public/storage.bak || true
	fi

	echo "Creating storage symlink..."
	php artisan storage:link || true
else
	echo "Storage symlink already exists."
fi

# Set owner/permissions (adjust user/group if needed)
echo "Setting permissions for storage and bootstrap/cache..."
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache || true

# Execute the container CMD
exec "$@"
