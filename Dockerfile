# Dockerfile for PHP Swoole CRUD Microservice
#
# This Dockerfile builds a PHP 8.4 CLI image with Swoole, Redis, Xdebug, Opcache, and other required extensions.
# It uses a multi-stage build to compile Swoole and extensions, then creates a runtime image with all extensions included.
#
# Supervisor is used to run the PHP process and manage restarts.
#
# Exposes ports for Swoole HTTP servers.
#
# Usage:
#   docker build -t php-swoole-crud-microservice .
#   docker run -p 9501:9501 php-swoole-crud-microservice

# ================= Base Stage =================
# Contains common runtime dependencies
FROM php:8.4-cli AS base

# --- Runtime system dependencies ---
RUN apt-get update && apt-get install -y \
    git \
    libbrotli-dev \
    libc-ares-dev \
    libcurl4-openssl-dev \
    libnghttp2-dev \
    libpq-dev \
    libsqlite3-dev \
    libssl-dev \
    libzip-dev \
    supervisor \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Create supervisor log directory
RUN mkdir -p /var/log/supervisor

# ================= Build Stage =================
FROM base AS build

# --- Build-only dependencies ---
RUN apt-get update && apt-get install -y \
    autoconf \
    build-essential \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# --- PHP extensions ---
RUN docker-php-ext-install sockets opcache pdo pdo_mysql pdo_sqlite

# --- Build Swoole from source with caching ---
ARG SWOOLE_VERSION=6.0.0
RUN --mount=type=cache,target=/tmp/swoole-build \
    git clone -b v${SWOOLE_VERSION} https://github.com/swoole/swoole-src.git /usr/src/swoole \
 && cd /usr/src/swoole \
 && phpize \
 && ./configure --enable-openssl --enable-sockets --enable-swoole-curl --enable-cares --enable-mysqlnd \
 && make -j$(nproc) && make install \
 && docker-php-ext-enable swoole opcache

# --- PECL extensions with caching ---
RUN --mount=type=cache,target=/tmp/pecl \
    pecl install redis xdebug \
 && docker-php-ext-enable redis xdebug

# --- Composer ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Set working directory ---
WORKDIR /app

# --- Copy only composer files first for caching ---
COPY composer.json composer.lock* ./

# --- Install application dependencies with caching ---
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-dev --optimize-autoloader --prefer-dist

# --- Ensure logs directory exists ---
RUN mkdir -p /app/logs

# ================= Runtime Stage =================
FROM base AS runtime

# --- Set working directory ---
WORKDIR /app

# --- Copy compiled PHP extensions and Composer/vendor from build stage ---
COPY --from=build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=build /app/vendor /app/vendor
# COPY --from=build /app /app

# --- PHP configuration ---
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-preload.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# --- Supervisor configuration ---
COPY docker/supervisord.conf /etc/supervisor/conf.d/swoole.conf

# --- Copy all application source code in a single step ---
COPY . .

# --- Expose Swoole HTTP and custom ports ---
EXPOSE 9501 9310 9502

# --- Default command ---
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/swoole.conf"]
