FROM php:8.1-fpm

# Install PDO and PDO MySQL extensions
RUN docker-php-ext-install pdo pdo_mysql
