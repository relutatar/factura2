up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

shell:
	docker compose exec app bash

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

fresh:
	docker compose exec app php artisan migrate:fresh --seed

pint:
	docker compose exec app ./vendor/bin/pint

test:
	docker compose exec app php artisan test

tinker:
	docker compose exec app php artisan tinker

permissions:
	docker compose exec app chmod -R 777 storage bootstrap/cache

logs:
	docker compose logs -f
