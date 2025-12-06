#!/bin/sh
set -e

# # Wait for database to be ready with retry logic
# echo "Waiting for database connection..."
# max_retries=30
# retry_count=0
# until php artisan migrate:status > /dev/null 2>&1 || [ $retry_count -eq $max_retries ]; do
#     echo "Database not ready yet... waiting (attempt $((retry_count+1))/$max_retries)"
#     sleep 2
#     retry_count=$((retry_count+1))
# done

# if [ $retry_count -eq $max_retries ]; then
#     echo "Warning: Could not connect to database after $max_retries attempts. Continuing anyway..."
# fi

# echo "Running database migrations..."
# php artisan migrate --force

# If you want to run a specific migration file, uncomment the line below and specify the path
# php artisan migrate --path=database/migrations/2025_11_28_153535_update_posts_table_add_stack_gallery_type.php

echo "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo "Optimizing applications..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

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
