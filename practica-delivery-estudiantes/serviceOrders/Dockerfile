# ---------- Etapa 1: dependencias con Composer ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./

# Sin scripts ni autoloader todavia: aun no esta el codigo de la app
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

# ---------- Etapa 2: imagen de ejecución ----------
FROM php:8.4-cli-alpine

# Extension de PostgreSQL para PDO
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo_pgsql \
    && apk del --no-cache postgresql-dev \
    && apk add --no-cache libpq

WORKDIR /app

# Composer (binario) para generar el autoloader final
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Codigo de la aplicacion + vendor de la etapa 1
COPY . .
COPY --from=vendor /app/vendor ./vendor

# Autoloader optimizado + descubrimiento de paquetes (ahora si existe artisan)
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan package:discover --ansi \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

EXPOSE 8000

# El entrypoint ejecuta las migraciones y luego levanta el servidor
ENTRYPOINT ["sh", "docker/entrypoint.sh"]