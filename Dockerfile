FROM phpswoole/swoole:php8.5-alpine

LABEL version="1.0"

RUN adduser -D -u 1000 rsotero

RUN set -ex \
  && apk update \
  && apk add --no-cache \
  unzip \
  libmemcached-dev \
  libpq-dev \
  libpng-dev \
  libwebp-dev \
  libzip-dev \
  lsb-release \
  && docker-php-ext-install pdo_pgsql zip

USER 1000:1000
