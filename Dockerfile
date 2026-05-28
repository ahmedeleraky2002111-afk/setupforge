FROM dunglas/frankenphp
RUN install-php-extensions pgsql pdo_pgsql
ENV FRANKENPHP_NO_HTTPS=1
ENV SERVER_NAME=":80"
COPY . /app/public