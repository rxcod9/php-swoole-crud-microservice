FROM php:8.2-cli AS build

# --- System deps ---
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libssl-dev libzip-dev zlib1g-dev \
    libbrotli-dev libsqlite3-dev autoconf build-essential \
    libcurl4-openssl-dev pkg-config libc-ares-dev \
    && rm -rf /var/lib/apt/lists/*

# --- Build Swoole from source with Redis + full features ---
ENV SWOOLE_VERSION=5.1.3
RUN docker-php-ext-install sockets
RUN git clone -b v${SWOOLE_VERSION} https://github.com/swoole/swoole-src.git /usr/src/swoole \
 && cd /usr/src/swoole \
 && phpize \
 && ./configure \
      --enable-openssl \
      --enable-http2 \
      --enable-swoole-curl \
      --enable-sockets \
      --enable-cares \
      --enable-redis \
 && make -j$(nproc) && make install \
 && docker-php-ext-enable swoole

# --- PHP extensions ---
RUN docker-php-ext-install pdo pdo_mysql pdo_sqlite

# --- phpredis ---
RUN pecl install redis \
 && docker-php-ext-enable redis

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# --- Composer ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer


# ================= Runtime =================
FROM php:8.2-cli

# Runtime system deps
RUN apt-get update && apt-get install -y \
    unzip git libbrotli-dev libpq-dev libssl-dev libzip-dev zlib1g-dev \
    libsqlite3-dev libcurl4-openssl-dev pkg-config libc-ares-dev \
    && rm -rf /var/lib/apt/lists/*

# Copy compiled PHP extensions + config
COPY --from=build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=build /usr/bin/composer /usr/bin/composer

WORKDIR /app

# --- Composer install ---
# Copy composer.json first (required)
COPY composer.json ./

# Copy composer.lock only if it exists
# (avoids build failure when no lock file is present)
RUN if [ -f composer.lock ]; then cp composer.lock composer.lock; fi

RUN composer install --no-dev --optimize-autoloader || true

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-preload.ini
# Copy app source
COPY . .

# Ensure logs dir
RUN mkdir -p /app/logs

EXPOSE 9501 9310 9502

CMD ["php","public/index.php"]
