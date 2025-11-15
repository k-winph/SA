FROM php:8.2-fpm

LABEL maintainer="Codex Automation"

# Install system dependencies, Node.js, and PHP extensions
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        curl \
        gnupg2 \
        git \
        unzip \
        zip \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        libicu-dev \
        libssl-dev \
        libcurl4-openssl-dev \
        pkg-config \
        netcat-openbsd && \
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y --no-install-recommends nodejs && \
    npm install -g npm@latest && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install \
        bcmath \
        exif \
        gd \
        intl \
        pcntl \
        pdo_mysql \
        zip && \
    pecl install redis && docker-php-ext-enable redis && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy Composer from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy entrypoint script
COPY docker/app/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

# Copy application source for image builds (will be overridden by bind-mount in dev)
COPY . .

# Ensure storage paths exist and are writable
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs && \
    chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/app-entrypoint"]
CMD ["php-fpm"]
