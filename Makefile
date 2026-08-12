.PHONY: init build up down restart shell composer test phpstan php clean help

help: ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?##' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

init: build ## Full setup: build Docker image, install composer dependencies
	docker compose run --rm query-builder-php-cli composer install

build: ## Build Docker image
	docker compose build

up: build ## Build and start container in background
	docker compose up -d

down: ## Stop and remove container
	docker compose down

restart: ## Restart container
	docker compose restart

shell: up ## Build, start, and open shell in container
	docker compose exec query-builder-php-cli sh

composer: ## Run composer inside container. Usage: make composer CMD="require foo/bar"
	docker compose run --rm query-builder-php-cli composer $(CMD)

composer-install: ## Install composer dev dependencies
	docker compose run --rm query-builder-php-cli composer install --dev
composer-update: ## Update composer dev dependencies
	docker compose run --rm query-builder-php-cli composer update --lock
test: ## Run PHPUnit tests. Usage: make test ARGS="--filter FooTest"
	docker compose run --rm query-builder-php-cli vendor/bin/phpunit $(ARGS)
phpstan: ## Run phpstan static code analyzer
	docker compose run --rm query-builder-php-cli vendor/bin/phpstan analyse --memory-limit=1G
phpcs: ## Run phpcs code style checks for PSR-12 standard
	docker compose run --rm query-builder-php-cli vendor/bin/phpcs
phpcbf: ## Run phpcbf for automatic code formatting
	docker compose run --rm query-builder-php-cli vendor/bin/phpcbf
check-q: phpstan test ## Runs tests and code analyzers

php: ## Run PHP inside container. Usage: make php ARGS="-r 'echo 1;'"
	docker compose run --rm query-builder-php-cli php $(ARGS)

clean: ## Remove container and volumes
	docker compose down -v --remove-orphans