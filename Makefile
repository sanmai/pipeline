all: test cs

.PHONY: cs
cs:
	@php vendor/bin/php-cs-fixer fix

.PHONY: test
test: 5.6-cli 7.0-cli 7.1-cli 7.2-cli 7.3-cli

%-cli:
	@docker run -t --rm -v "$$PWD":/usr/src -w /usr/src php:$@ php vendor/bin/phpunit --no-coverage
