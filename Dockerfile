# ------------------------------------------------------------------------
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
#
# NOTE: This file is optimized to use BuildKit cache mounts and a dedicated cache scope
#       in CI so that cache for Debian-based image is isolated from other flavors.
# ------------------------------------------------------------------------

# Use BuildKit syntax features (for --mount=type=cache)
# make sure DOCKER_BUILDKIT=1 in local dev or CI
# syntax=docker/dockerfile:1.4

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
    && rm -rf /var/lib/apt/lists/* \
    # Create supervisor log directory
    && mkdir -p /var/log/supervisor /app/logs

# ================= Build Stage =================
FROM base AS build

# --- Build-only dependencies ---
RUN apt-get update && apt-get install -y \
    autoconf \
    build-essential \
    ca-certificates \
    git \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# --- PHP extensions ---
# Use a cache mount for PHP extension sources to avoid re-downloading/building unchanged sources
# Cache target is unique for Debian flavor: /usr/src/php/ext-debian
RUN --mount=type=cache,target=/usr/src/php/ext-debian \
    docker-php-ext-install sockets opcache pdo pdo_mysql pdo_sqlite

# --- Build Swoole from source with caching ---
ARG SWOOLE_VERSION=6.0.0
# Use a dedicated cache mount for the swoole build to speed up rebuilds for this flavor.
# The cache target path is unique (swoole-build-debian) to avoid sharing with other flavors.
RUN --mount=type=cache,target=/tmp/swoole-build-debian \
    git clone -b v${SWOOLE_VERSION} https://github.com/swoole/swoole-src.git /usr/src/swoole \
 && cd /usr/src/swoole \
 && phpize \
 && ./configure --enable-openssl --enable-sockets --enable-swoole-curl --enable-cares --enable-mysqlnd --enable-http2 \
 && make -j$(nproc) && make install \
 && docker-php-ext-enable swoole opcache

# --- PECL extensions with caching ---
# Dedicated cache target for PECL build artifacts for Debian flavor.
RUN --mount=type=cache,target=/tmp/pecl-debian \
    pecl install redis xdebug \
 && docker-php-ext-enable redis xdebug

# Allow runtime toggle for Xdebug
ARG XDEBUG_MODE=off
ENV XDEBUG_MODE=${XDEBUG_MODE}

# --- Composer ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Set working directory ---
WORKDIR /app

# --- Copy only composer files first for caching ---
COPY composer.json composer.lock ./

# --- Install application dependencies with composer cache (Debian-specific composer cache) ---
# Composer cache mount target is unique: /root/.composer/cache-debian
# This avoids sharing composer caches with the Alpine build.
RUN --mount=type=cache,target=/root/.composer/cache-debian \
    composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction

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
