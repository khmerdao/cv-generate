.PHONY: up down sh db-reset test lint
up:
	docker compose up -d --build
down:
	docker compose down
sh:
	docker compose exec php sh
db-reset:
	docker compose exec php php bin/console doctrine:database:drop --force --if-exists
	docker compose exec php php bin/console doctrine:database:create
	docker compose exec php php bin/console doctrine:migrations:migrate -n
test:
	docker compose exec php php bin/phpunit
	docker compose run --rm node npx vitest run
lint:
	docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff
	docker compose exec php vendor/bin/phpstan analyse
	docker compose run --rm node npx eslint assets --ext .ts,.tsx
