.PHONY: up down build test lint stan install shell fix kphp-check

## Build and start containers
up:
	docker compose up -d --build

## Stop containers
down:
	docker compose down

## Build image only
build:
	docker compose build

## Install composer dependencies
install:
	docker compose run --rm app composer install

## Run PHPUnit tests
test:
	docker compose run --rm app ./vendor/bin/phpunit

## Run PHP-CS-Fixer (lint)
lint:
	docker compose run --rm app ./vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes

## Fix code style
fix:
	docker compose run --rm app ./vendor/bin/php-cs-fixer fix --allow-risky=yes

## Run PHPStan static analysis
stan:
	docker compose run --rm app ./vendor/bin/phpstan analyse

## Run KPHP + PHAR compatibility check
kphp-check:
	docker build -f Dockerfile.check -t lphenom-auth-check .

## Open shell in app container
shell:
	docker compose run --rm app bash

