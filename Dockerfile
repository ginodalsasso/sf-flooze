FROM dunglas/frankenphp:1-php8.4 AS frankenphp_upstream

# ── Base stage ───────────────────────────────────────────────────────────────
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

VOLUME /app/var/

# hadolint ignore=DL3008
RUN apt-get update && apt-get install -y --no-install-recommends \
	file \
	git \
	&& rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
	@composer \
	apcu \
	intl \
	opcache \
	pdo_pgsql \
	zip

###> recipes ###
###< recipes ###

COPY --link frankenphp/conf.d/app.ini $PHP_INI_DIR/conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/caddy/Caddyfile

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# ── Development stage ─────────────────────────────────────────────────────────
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev XDEBUG_MODE=off

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN install-php-extensions xdebug

COPY --link frankenphp/conf.d/app.dev.ini $PHP_INI_DIR/conf.d/

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--watch"]

# ── Production stage ──────────────────────────────────────────────────────────
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/

# Install dependencies without dev packages first (layer cache)
COPY --link composer.* symfony.lock ./
RUN set -eux; \
	composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --link . ./
RUN rm -Rf frankenphp/

RUN set -eux; \
	mkdir -p var/cache var/log; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console; sync
