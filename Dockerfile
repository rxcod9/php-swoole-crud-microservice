# Dockerfile for PHP Swoole CRUD Microservice
#
# This Dockerfile builds a PHP 8.4 CLI image with Swoole, Redis, Xdebug, Opcache, and other required extensions.
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
# Includes nghttp2 and zlib to ensure HTTP/2 and compression support are built in automatically.
RUN apt-get update && apt-get install -y \
    autoconf \
    build-essential \
    git \
    libbrotli-dev \
    libc-ares-dev \
    libcurl4-openssl-dev \
    libnghttp2-dev \
    libpq-dev \
    libsqlite3-dev \
    libssl-dev \
    libzip-dev \
    pkg-config \
    unzip \
    zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install sockets opcache

# --- Build Swoole from source with required features ---
# Deprecated options removed; HTTP/2, zlib, mysqlnd auto-enabled if dependencies are present.
ARG SWOOLE_VERSION=6.0.0
RUN git clone -b v${SWOOLE_VERSION} https://github.com/swoole/swoole-src.git /usr/src/swoole \
 && cd /usr/src/swoole \
 && phpize \
 && ./configure \
    --enable-openssl \
    --enable-sockets \
    --enable-swoole-curl \
    --enable-cares \
    --enable-mysqlnd \
 && make -j$(nproc) && make install \
 && docker-php-ext-enable swoole opcache

# --- PHP extensions ---
# Installs PDO extensions for MySQL and SQLite.
RUN docker-php-ext-install pdo pdo_mysql pdo_sqlite

# In build stage
# RUN git clone https://github.com/OpenSwoole/mysql.git /usr/src/openswoole-mysql \
#     && cd /usr/src/openswoole-mysql \
#     && phpize \
#     && ./configure \
#     && make -j$(nproc) \
#     && make install \
#     && docker-php-ext-enable openswoole_mysql

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
    git \
    libbrotli-dev \
    libc-ares-dev \
    libcurl4-openssl-dev \
    libnghttp2-dev \
    libpq-dev \
    libsqlite3-dev \
    libssl-dev \
    libzip-dev \
    pkg-config \
    unzip \
    zlib1g-dev \
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
RUN if [ -f composer.lock ]; then \
      cp composer.lock composer.lock && \
      composer install --no-dev --optimize-autoloader || true; \
    fi

# --- PHP configuration ---
# Copies custom PHP configuration including opcache tuning.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-preload.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# --- Copy application source code ---
COPY . .

# --- Ensure logs directory exists ---
RUN mkdir -p /app/logs

# --- Expose Swoole HTTP and custom ports ---
EXPOSE 9501 9310 9502

# --- Default command ---
CMD ["php","public/index.php"]