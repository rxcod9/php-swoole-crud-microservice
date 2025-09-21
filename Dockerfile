# Dockerfile for PHP Swoole CRUD Microservice
#
# This Dockerfile builds a PHP 8.2 CLI image with Swoole, Redis, Xdebug, and other required extensions.
# It uses a multi-stage build to compile Swoole and extensions, then creates a lightweight runtime image.
#
# Stages:
#   1. build: Compiles Swoole and PHP extensions, installs Composer.
#   2. runtime: Copies compiled extensions and Composer, installs runtime dependencies, and sets up the app.
#
# Exposes ports for Swoole HTTP servers.
#
# Usage:
#   docker build -t php-swoole-crud-microservice .
#   docker run -p 9501:9501 php-swoole-crud-microservice

# ================= Build Stage =================
FROM php:8.4-cli AS build

# --- System dependencies ---
# Installs build tools and libraries required for compiling Swoole and PHP extensions.
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libssl-dev libzip-dev zlib1g-dev \
    libbrotli-dev libsqlite3-dev autoconf build-essential \
    libcurl4-openssl-dev pkg-config libc-ares-dev \
    && rm -rf /var/lib/apt/lists/*

# --- Build Swoole from source with Redis and full features ---
# Clones Swoole source code and compiles with desired features.
ENV SWOOLE_VERSION=5.1.3
RUN docker-php-ext-install sockets
RUN git clone -b v${SWOOLE_VERSION} https://github.com/swoole/swoole-src.git /usr/src/swoole \
 && cd /usr/src/swoole \
 && phpize \
 && ./configure \
    --enable-openssl \
    --enable-http2 \
    --enable-sockets \
    --enable-swoole-curl \
    --enable-cares \
    --enable-redis \
    --enable-async-redis \
    --enable-cares \
    --enable-swoole-mysqlnd \
    --enable-swoole-zlib \
 && make -j$(nproc) && make install \
 && docker-php-ext-enable swoole

# --- PHP extensions ---
# Installs PDO extensions for MySQL and SQLite.
RUN docker-php-ext-install pdo pdo_mysql pdo_sqlite

# --- phpredis extension ---
# Installs and enables phpredis via PECL.
RUN pecl install redis \
 && docker-php-ext-enable redis

# --- Xdebug extension ---
# Installs and enables Xdebug for debugging.
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# --- Composer ---
# Copies Composer binary from official Composer image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ================= Runtime Stage =================
FROM php:8.4-cli

# --- Runtime system dependencies ---
# Installs only the libraries required at runtime.
RUN apt-get update && apt-get install -y \
    unzip git libbrotli-dev libpq-dev libssl-dev libzip-dev zlib1g-dev \
    libsqlite3-dev libcurl4-openssl-dev pkg-config libc-ares-dev \
    && rm -rf /var/lib/apt/lists/*

# --- Copy compiled PHP extensions and Composer ---
COPY --from=build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=build /usr/bin/composer /usr/bin/composer

# --- Set working directory ---
WORKDIR /app

# --- Composer install ---
# Copies composer.json and optionally composer.lock, then installs dependencies.
COPY composer.json ./
RUN if [ -f composer.lock ]; then cp composer.lock composer.lock; fi
RUN composer install --no-dev --optimize-autoloader || true

# --- PHP configuration ---
# Copies custom PHP configuration.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-preload.ini

# --- Copy application source code ---
COPY . .

# --- Ensure logs directory exists ---
RUN mkdir -p /app/logs

# --- Expose Swoole HTTP and custom ports ---
EXPOSE 9501 9310 9502

# --- Default command ---
CMD ["php","public/index.php"]