.PHONY: test lint

test:
	php artisan test

lint:
	vendor/bin/pint
