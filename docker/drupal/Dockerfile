FROM php:8.4-fpm-bookworm

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libonig-dev \
    default-mysql-client \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        intl \
        mbstring \
        mysqli \
        opcache \
        pdo_mysql \
        xml \
        zip \
    && pecl install opentelemetry \
    && docker-php-ext-enable opentelemetry \
    && rm -rf /tmp/pear \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY drupal/composer.json drupal/composer.lock ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress

COPY drupal/ ./

RUN mkdir -p web/sites/default/files \
    && chown -R www-data:www-data web/sites/default/files

COPY docker/drupal/php.ini /usr/local/etc/php/conf.d/observability-lab.ini

CMD ["php-fpm"]