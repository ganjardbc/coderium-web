
# Use an official PHP runtime as a parent image
FROM php:8.3-fpm

# Set working directory
WORKDIR /var/www

# Install dependencies for the operating system software.
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    libonig-dev \
    libzip-dev

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files
COPY composer.json composer.lock ./

# Install composer dependencies
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

# Copy existing application directory contents
COPY . .

# Copy entrypoint script
COPY entrypoint.sh /usr/local/bin/entrypoint.sh

# Make entrypoint script executable
RUN chmod +x /usr/local/bin/entrypoint.sh

# Generate autoloader
RUN composer dump-autoload --optimize

# Set entrypoint
ENTRYPOINT ["entrypoint.sh"]

# Expose port 8000 and start php-fpm server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
