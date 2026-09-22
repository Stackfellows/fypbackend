FROM php:8.2-cli-alpine

# Install PostgreSQL, SQLite and PDO extensions
RUN apk add --no-cache postgresql-dev sqlite-dev \
    && docker-php-ext-install pdo pdo_pgsql pdo_mysql pdo_sqlite

WORKDIR /var/www/html

COPY . /var/www/html

# Ensure uploads directory exists and is writable
RUN mkdir -p /var/www/html/uploads && chmod -R 777 /var/www/html/uploads

EXPOSE 8000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8000} -t /var/www/html"]
