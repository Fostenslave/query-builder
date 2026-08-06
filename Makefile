.PHONY: init build up down restart shell composer test php clean help

help: ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?##' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

init: build ## Full setup: build Docker image, install composer dependencies
	docker compose run --rm simple-orm composer install

build: ## Build Docker image
	docker compose build

up: build ## Build and start container in background
	docker compose up -d

down: ## Stop and remove container
	docker compose down

restart: ## Restart container
	docker compose restart

shell: up ## Build, start, and open shell in container
	docker compose exec simple-orm sh

composer: ## Run composer inside container. Usage: make composer CMD="require foo/bar"
	docker compose run --rm simple-orm composer $(CMD)

composer-install: ## Install composer dev dependencies
	docker compose run --rm simple-orm composer install --dev
composer-update: ## Update composer dev dependencies
	docker compose run --rm simple-orm composer update --lock
test: ## Run PHPUnit tests. Usage: make test ARGS="--filter FooTest"
	docker compose run --rm simple-orm vendor/bin/phpunit $(ARGS)

php: ## Run PHP inside container. Usage: make php ARGS="-r 'echo 1;'"
	docker compose run --rm simple-orm php $(ARGS)

clean: ## Remove container and volumes
	docker compose down -v --remove-orphans