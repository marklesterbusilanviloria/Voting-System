FROM php:8.3-cli
RUN docker-php-ext-install pdo_mysql
WORKDIR /app
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY . /app
RUN mkdir -p /app/uploads/profiles /app/uploads/documents
# several workers so one slow request (login throttling) never blocks everyone
ENV PHP_CLI_SERVER_WORKERS=4
CMD ["sh", "-c", "exec php -S 0.0.0.0:${PORT:-8080} -t /app /app/router.php"]
