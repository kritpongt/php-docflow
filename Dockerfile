# syntax=docker/dockerfile:1

FROM node:24-bookworm-slim AS assetsnode
WORKDIR /app
ENV PUPPETEER_SKIP_DOWNLOAD=1
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci
COPY resources/ resources/
COPY public/ public/
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
ENV COMPOSER_HOME=/root/.composer
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer composer install --no-dev --no-interaction --prefer-dist \
      --optimize-autoloader --classmap-authoritative

FROM node:24-bookworm-slim AS pdfnode
WORKDIR /app
ENV PUPPETEER_SKIP_DOWNLOAD=1
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci --omit=dev --ignore-scripts

FROM php:8.5-fpm-bookworm AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
      chromium fonts-thai-tlwg libpq-dev libicu-dev libzip-dev libonig-dev \
  && docker-php-ext-install pdo_mysql pdo_pgsql mbstring intl bcmath opcache zip gd \
  && rm -rf /var/lib/apt/lists/*

COPY --from=assetsnode /usr/local/bin/node /usr/local/bin/node
COPY --from=vendor      /app/vendor        /var/www/html/vendor
COPY --from=pdfnode     /app/node_modules  /var/www/html/node_modules
COPY --from=assetsnode  /app/public        /var/www/html/public
COPY app/ bin/ config/ core/ migrations/ /var/www/html/
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

ARG UID=1000 GID=1000
RUN groupadd -g "$GID" app \
  && useradd -u "$UID" -g "$GID" -m -s /usr/sbin/nologin app \
  && sed -i 's/^user = www-data/user = app/; s/^group = www-data/group = app/' \
       /usr/local/etc/php-fpm.d/www.conf \
  && mkdir -p /var/www/html/storage/cache/twig /var/www/html/storage/logs \
  && chown -R app:app /var/www/html/storage

ENV PDF_CHROME_PATH=/usr/bin/chromium \
    PDF_NODE_MODULE_PATH=/var/www/html/node_modules \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium
WORKDIR /var/www/html
USER app
EXPOSE 9000

FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=assetsnode /app/public /var/www/html/public
EXPOSE 8080