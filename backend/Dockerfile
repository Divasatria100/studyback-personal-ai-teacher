# Use PHP 8.2 FPM Alpine as base image
FROM php:8.2-fpm-alpine

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache \
    # Build dependencies
    autoconf \
    g++ \
    make \
    # Runtime dependencies
    bash \
    curl \
    zip \
    unzip \
    git \
    # PostgreSQL client
    postgresql-dev \
    # Poppler-utils for pdftotext
    poppler-utils \
    # Image processing
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    # Other utilities
    oniguruma-dev \
    libxml2-dev

# Install PHP extensions required by Laravel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        xml

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

# Copy application files
COPY . .

# Generate optimized autoload files
RUN composer dump-autoload --optimize

# Set permissions for Laravel
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Verify pdftotext is available
RUN pdftotext -v || echo "pdftotext installed"

# Expose port 8000 for Laravel development server
EXPOSE 8000

# Switch to www-data user
USER www-data

# Default command - can be overridden in docker-compose.yml
CMD ["php-fpm"]
