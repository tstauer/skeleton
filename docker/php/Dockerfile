# PHP for running the reproducer without a local PHP installation
FROM php:8.4-cli

COPY --from=mlocati/php-extension-installer:2.9 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl gd zip pdo_mysql
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
RUN echo "memory_limit=-1" > "$PHP_INI_DIR/conf.d/memory-limit.ini"

WORKDIR /app
