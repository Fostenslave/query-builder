FROM php:8.5.8-cli-alpine3.23

COPY --from=mlocati/php-extension-installer:2.11 \
    /usr/bin/install-php-extensions \
    /usr/local/bin/

COPY --from=composer:2.10.1 \
    /usr/bin/composer \
    /usr/local/bin/composer

RUN apk add --no-cache \
        git \
        zip \
    && install-php-extensions \
        zip \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
    && echo 'memory_limit=1024M' > /usr/local/etc/php/conf.d/40-custom.ini \
    && rm -rf /tmp/* /var/tmp/* /var/cache/apk/*

CMD ["tail", "-f", "/dev/null"]
