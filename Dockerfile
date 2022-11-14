FROM composer:2 AS stage_composer
ARG APP_ENV='prod'
ARG COMPOSER_AUTH
ENV COMPOSER_AUTH ${COMPOSER_AUTH}

WORKDIR /vendor

COPY ./composer.json ./
COPY ./composer.lock ./

RUN composer install --ignore-platform-reqs --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts  \
    && rm -rf /root/.composer



FROM php:7.4-fpm as base
RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libicu-dev \
        cron \
        libmagickwand-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

RUN apt-get update && apt-get install -y  git wget libzip-dev
RUN docker-php-ext-install zip && \
    docker-php-ext-install pcntl && \
    #docker-php-ext-install opcache && \
    docker-php-ext-install intl
RUN apt-get install -y nginx \
        && ln -sf /dev/stdout /var/log/nginx/access.log \
        && ln -sf /dev/stderr /var/log/nginx/error.log \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/*

RUN pecl install imagick \
&& docker-php-ext-enable imagick

# add composer executable
COPY --from=stage_composer /usr/bin/composer /usr/bin/composer

# download tini
ARG TINI_VERSION='v0.19.0'
ADD https://github.com/krallin/tini/releases/download/${TINI_VERSION}/tini /usr/local/bin/tini
RUN chmod +x /usr/local/bin/tini




FROM base as production

WORKDIR /project

ARG UID=1000
ARG PHP_FPM_INI_DIR="/usr/local/etc/php"

COPY --from=stage_composer /vendor ./
COPY . ./

RUN usermod -u $UID www-data
RUN composer run-script auto-scripts

RUN mkdir -p var/log && mkdir -p var/cache
RUN chown -R www-data:www-data var/log && chown -R www-data:www-data var/cache

COPY docker-image/nginx.conf /etc/nginx/sites-enabled/default
COPY docker-image/entrypoint.sh /usr/local/bin/docker-entrypoint
COPY docker-image/platform.prod.ini $PHP_FPM_INI_DIR/conf.d/platform.ini

RUN mkdir -p /sock
RUN rm /usr/local/etc/php-fpm.d/*
COPY docker-image/php.conf /usr/local/etc/php-fpm.d/

ENTRYPOINT ["tini", "docker-entrypoint", "--"]

CMD []


FROM production as development

RUN pecl install xdebug-3.1.5
COPY docker-image/xdebug.ini "/usr/local/etc/php/conf.d/xdebug.ini"

#FROM php:7.4-fpm
#
#ARG COMPOSER_VERSION='1.9.1'
#ARG TINI_VERSION='0.18.0'
#
#WORKDIR /project
#
#COPY . ./
#
#ARG UID=1000
#ARG PHP_FPM_INI_DIR="/usr/local/etc/php"
#
#COPY docker-image/platform.prod.ini $PHP_FPM_INI_DIR/conf.d/platform.ini
#
#RUN usermod -u $UID www-data
#
#RUN apt-get update \
#    && apt-get install -y wget unzip nano git \
#    && apt-get install -y \
#        libzip-dev libfreetype6-dev libjpeg62-turbo-dev libmcrypt-dev libpng-dev libicu-dev libpq-dev libonig-dev libmagickwand-dev \
#    && docker-php-ext-install -j$(nproc) zip mbstring json gd iconv pcntl intl \
#    && apt-get install -y nginx \
#        && ln -sf /dev/stdout /var/log/nginx/access.log \
#        && ln -sf /dev/stderr /var/log/nginx/error.log \
#    && apt-get clean && rm -rf /var/lib/apt/lists/*
#
#RUN pecl install imagick \
#&& docker-php-ext-enable imagick
#
#ADD https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar /usr/local/bin/composer
#RUN chmod +x /usr/local/bin/composer
#
#ADD https://github.com/krallin/tini/releases/download/v${TINI_VERSION}/tini /tini
#RUN chmod +x /tini
#
#COPY docker-image/nginx.conf /etc/nginx/sites-enabled/default
#COPY docker-image/entrypoint.sh /usr/local/bin/docker-entrypoint
#
#RUN mkdir -p /sock
#RUN rm /usr/local/etc/php-fpm.d/*
#COPY docker-image/php.conf /usr/local/etc/php-fpm.d/
#
#RUN composer install --ignore-platform-reqs --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts $NO_DEV  \
#    && rm -rf /root/.composer && rm -rf var/cache/*
#
#RUN chown -R www-data:www-data var/log
#RUN chown -R www-data:www-data var/cache
#
#
#ENTRYPOINT ["/tini", "docker-entrypoint", "--"]
#CMD []






#FROM php:7.4-fpm as base
#
#RUN apt-get update \
#    && apt-get install -y wget unzip nano sudo \
#    && apt-get install -y nginx \
#    && apt-get install -y \
#        libzip-dev libfreetype6-dev libjpeg62-turbo-dev libmcrypt-dev libpng-dev libicu-dev libpq-dev libonig-dev libmagickwand-dev \
#    && docker-php-ext-install -j$(nproc) zip mbstring json gd iconv pcntl intl opcache \
#    && docker-php-source delete && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/*
#
#RUN pecl install imagick \
#&& docker-php-ext-enable imagick
#
#RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
#RUN sed -i "s/^\(upload_max_filesize\).*/\1 = 5M/" "$PHP_INI_DIR/php.ini"
#
#COPY docker-image/www.conf /usr/local/etc/php-fpm.d/www.conf
#
## disable php access logs, nginx access logs are enough
#RUN sed -i "s/^\(access.log\).*/\1 = \/dev\/null/" /usr/local/etc/php-fpm.d/docker.conf
#
#RUN cd /var/log/nginx \
#    && rm -f access.log && ln -s /dev/stdout access.log \
#    && rm -r error.log && ln -s /dev/stderr error.log
#
#FROM base
#
#ARG TINI_VERSION='0.19.0'
#ARG COMPOSER_VERSION='2.0.11'
#
#ENV APP_ENV=prod
#
#RUN wget -q -O /usr/local/bin/tini https://github.com/krallin/tini/releases/download/v${TINI_VERSION}/tini \
#    && chmod +x /usr/local/bin/tini
#
#RUN wget -q -O /usr/local/bin/composer https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar \
#    && chmod +x /usr/local/bin/composer
#
#COPY docker-image/nginx.conf /etc/nginx/conf.d/default.conf
#RUN rm -rf /etc/nginx/sites-enabled/*
#
## Check max upload size
#RUN php-fpm -i | grep 'upload_max_filesize => 5M'
#RUN nginx -T | grep 'client_max_body_size 5M'
#
#WORKDIR /project
#
#COPY . ./
#
#RUN composer install --no-cache --no-interaction --no-progress --optimize-autoloader \
#    && composer check-platform-reqs \
#    && rm -rf var/cache/dev/* var/cache/prod/* var/log/* /tmp/*
#
#RUN chown -R www-data:www-data var
#
#COPY docker-image/entrypoint.sh /usr/local/bin/docker-entrypoint
#ENTRYPOINT ["tini", "docker-entrypoint", "--"]
#CMD []
