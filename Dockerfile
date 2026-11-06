FROM php:8.1-cli-alpine

# Install PostgreSQL client & build dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    bash

# Install extensions required by Laravel & PostgreSQL
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        zip \
        bcmath \
        gd \
        mbstring

# Get Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create non-root user (UID 1000) for compatibility with cloud sandboxes
RUN adduser -D -u 1000 appuser

WORKDIR /app

# Copy application files
COPY --chown=appuser:appuser . /app

# Use production env file
RUN if [ -f .env.production ]; then cp .env.production .env; fi

# Install dependencies (production only)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R appuser:appuser /app \
    && chmod -R 775 /app/storage /app/bootstrap/cache

USER appuser

# Expose ports
EXPOSE 7860
EXPOSE 8080

# Run Laravel on PORT (default 7860)
CMD ["sh", "-c", "php artisan config:clear && php artisan route:clear && php artisan serve --host=0.0.0.0 --port=${PORT:-7860}"]
