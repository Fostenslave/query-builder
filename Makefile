.PHONY: build up down restart shell composer test php clean help

help: ## показать это сообщение
	@grep -E '^[a-zA-Z_-]+:.*?##' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

build: ## собрать образ
	docker compose build

up: build ## поднять контейнер в фоне
	docker compose up -d

down: ## остановить контейнер
	docker compose down

restart: ## перезапустить контейнер
	docker compose restart

shell: up ## войти в shell контейнера
	docker compose exec simple-orm sh

# make composer CMD="install"     — установить зависимости
# make composer CMD="require ..." — добавить пакет
composer: ## запустить composer (передай CMD="...")
	docker compose run --rm simple-orm composer $(CMD)

# make test                 — запустить все тесты
# make test ARGS="--filter" — запустить конкретный тест
test: ## запустить phpunit (опционально ARGS="...")
	docker compose run --rm simple-orm vendor/bin/phpunit $(ARGS)

# make php ARGS="-v" — запустить php
php: ## запустить php (опционально ARGS="...")
	docker compose run --rm simple-orm php $(ARGS)

clean: ## удалить контейнер и образы
	docker compose down -v --remove-orphans
