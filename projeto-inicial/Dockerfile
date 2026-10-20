FROM php:8.3-cli-alpine

RUN apk add --no-cache curl \
    && docker-php-ext-install pdo_mysql

WORKDIR /app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --optimize

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
