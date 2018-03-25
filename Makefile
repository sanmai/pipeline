.PHONY: test

export PHAN_DISABLE_XDEBUG_WARN=1

all:
	composer validate --strict
	vendor/bin/phpunit
	vendor/bin/php-cs-fixer --diff --verbose fix
	vendor/bin/phan -p -j $$(nproc) --color; echo
	vendor/bin/phpstan analyse src tests --level=2 -c .phpstan.neon
	php vendor/bin/infection --min-msi=95 --min-covered-msi=100 --log-verbosity=2 --threads=$$(nproc) -n

test:
	composer validate --strict
	vendor/bin/phpunit --verbose
	mkdir -p build/cache && vendor/bin/php-cs-fixer --cache-file=build/cache/.php_cs.cache --diff --dry-run --stop-on-violation --verbose fix
	test -d .phan || vendor/bin/phan --init --init-level=1 --init-overwrite --target-php-version=native > /dev/null && vendor/bin/phan
	vendor/bin/phpstan analyse src tests --level=2 -c .phpstan.neon --no-interaction --no-progress
	php vendor/bin/infection --min-msi=95 --min-covered-msi=100 --log-verbosity=2 --threads=4

