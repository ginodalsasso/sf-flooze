#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
    if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
        composer install --prefer-dist --no-progress --no-interaction
    fi

    echo "Waiting for database to be ready..."
    ATTEMPTS=60
    until [ $ATTEMPTS -eq 0 ] || php bin/console dbal:run-sql -q "SELECT 1" 2>/dev/null; do
        sleep 1
        ATTEMPTS=$((ATTEMPTS - 1))
        echo "Still waiting... $ATTEMPTS attempts left."
    done

    if [ $ATTEMPTS -eq 0 ]; then
        echo "Database is not reachable. Exiting."
        exit 1
    fi

    echo "Database is ready."

    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

exec docker-php-entrypoint "$@"
