FROM dunglas/frankenphp
RUN install-php-extensions pgsql pdo_pgsql
COPY . /app/public