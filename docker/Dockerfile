# syntax=docker/dockerfile:1
# Imagen única del LMS UES. El mismo contenedor cumple cuatro roles según su comando
# (docker/entrypoint.sh): web (nginx + php-fpm), horizon, scheduler y reverb.
# Se construye desde la raíz del repositorio: docker build -f docker/Dockerfile .

# 1. Dependencias de PHP. Con COMPOSER_DEV=1 se instalan las de desarrollo:
#    los seeders de la demo usan Faker (staging). En producción: COMPOSER_DEV=0.
FROM composer:2 AS vendor
ARG COMPOSER_DEV=1
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader \
        --ignore-platform-reqs $([ "$COMPOSER_DEV" = "1" ] || echo --no-dev)

# 2. Recursos de Vite. Tailwind lee también vendor/ y app/, por eso se copia todo.
FROM oven/bun:1.2-alpine AS assets
WORKDIR /app
COPY package.json bun.lock ./
RUN bun install --frozen-lockfile
COPY . .
COPY --from=vendor /app/vendor ./vendor
ARG VITE_REVERB_APP_KEY=lms-staging-key
ENV VITE_APP_NAME="LMS UES" \
    VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY}
RUN bun run build

# 3. Imagen final con paquetes de PHP 8.4 de Alpine.
FROM alpine:3.22
RUN apk add --no-cache \
        php84 php84-fpm php84-opcache php84-pdo_pgsql php84-pgsql php84-intl php84-zip php84-pcntl php84-posix \
        php84-bcmath php84-mbstring php84-xml php84-dom php84-xmlwriter php84-xmlreader php84-simplexml \
        php84-tokenizer php84-session php84-fileinfo php84-ctype php84-curl php84-openssl php84-phar \
        php84-iconv php84-sodium php84-sockets \
        nginx supervisor curl su-exec tzdata \
    && ln -sf /usr/bin/php84 /usr/bin/php \
    && adduser -u 82 -D -S -H -G www-data www-data
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/php.ini /etc/php84/conf.d/99-lms.ini
COPY docker/php-fpm.conf /etc/php84/php-fpm.d/zz-lms.conf
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/lms-entrypoint
RUN chmod +x /usr/local/bin/lms-entrypoint

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-interaction \
    && rm -f .env bootstrap/cache/*.php \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80 8080
ENTRYPOINT ["lms-entrypoint"]
CMD ["web"]
