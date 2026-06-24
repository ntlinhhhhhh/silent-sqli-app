FROM php:8.0-fpm

# Install PDO, PDO MySQL, and mysqli extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Install build dependencies for uopz and pcov
RUN apt-get update && apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS \
    && pecl install uopz pcov \
    && docker-php-ext-enable uopz pcov \
    && rm -rf /var/lib/apt/lists/*

# Configure uopz and pcov settings
RUN echo "uopz.exit = 1" >> /usr/local/etc/php/conf.d/docker-php-ext-uopz.ini
RUN echo "pcov.enabled = 1" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini
RUN echo "pcov.directory = /var/www/html" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini

# Enable auto_prepend and auto_append files for fuzzer instrumentation
RUN echo "auto_prepend_file = /var/www/fuzzer/__fuzzer__startcov.php" >> /usr/local/etc/php/conf.d/fuzzer.ini
RUN echo "auto_append_file = /var/www/fuzzer/__fuzzer__stopcov.php" >> /usr/local/etc/php/conf.d/fuzzer.ini
