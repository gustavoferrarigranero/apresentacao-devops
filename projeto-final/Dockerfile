FROM composer:2 AS vendor

WORKDIR /app
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.3-cli-alpine

RUN apk add --no-cache curl \
    && docker-php-ext-install opcache pcntl pdo_mysql

WORKDIR /app
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
RUN addgroup -S clinicflow \
    && adduser -S clinicflow -G clinicflow \
    && chown -R clinicflow:clinicflow storage bootstrap/cache

USER clinicflow

EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl --fail http://127.0.0.1:8000/api/health || exit 1
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
