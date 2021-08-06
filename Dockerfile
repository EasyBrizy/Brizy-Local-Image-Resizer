FROM php:7.4-fpm as base

RUN apt-get update \
    && apt-get install -y wget unzip nano sudo \
    && apt-get install -y nginx \
    && apt-get install -y \
        libzip-dev libfreetype6-dev libjpeg62-turbo-dev libmcrypt-dev libpng-dev libicu-dev libpq-dev libonig-dev libmagickwand-dev \
    && docker-php-ext-install -j$(nproc) zip mbstring json gd iconv pcntl intl opcache \
    && docker-php-source delete && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/*

RUN pecl install imagick \
&& docker-php-ext-enable imagick

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN sed -i "s/^\(upload_max_filesize\).*/\1 = 5M/" "$PHP_INI_DIR/php.ini"

COPY docker-image/www.conf /usr/local/etc/php-fpm.d/www.conf

# disable php access logs, nginx access logs are enough
RUN sed -i "s/^\(access.log\).*/\1 = \/dev\/null/" /usr/local/etc/php-fpm.d/docker.conf

RUN cd /var/log/nginx \
    && rm -f access.log && ln -s /dev/stdout access.log \
    && rm -r error.log && ln -s /dev/stderr error.log

FROM base

ARG TINI_VERSION='0.19.0'
ARG COMPOSER_VERSION='2.0.11'

ENV APP_ENV=prod

RUN wget -q -O /usr/local/bin/tini https://github.com/krallin/tini/releases/download/v${TINI_VERSION}/tini \
    && chmod +x /usr/local/bin/tini

RUN wget -q -O /usr/local/bin/composer https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar \
    && chmod +x /usr/local/bin/composer

COPY docker-image/nginx.conf /etc/nginx/conf.d/default.conf
RUN rm -rf /etc/nginx/sites-enabled/*

# Check max upload size
RUN php-fpm -i | grep 'upload_max_filesize => 5M'
RUN nginx -T | grep 'client_max_body_size 5M'

WORKDIR /project

COPY . ./

RUN composer install --no-cache --no-interaction --no-progress --optimize-autoloader \
    && composer check-platform-reqs \
    && rm -rf var/cache/dev/* var/cache/prod/* var/log/* /tmp/*

RUN chown -R www-data:www-data var

COPY docker-image/entrypoint.sh /usr/local/bin/docker-entrypoint
ENTRYPOINT ["tini", "docker-entrypoint", "--"]
CMD []
