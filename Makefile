.PHONY: ci test prerequisites

# Use any most recent PHP version
PHP=$(shell which php)
PHPDBG=$(shell which phpdbg && echo -qrr || echo php)

# Default parallelism
JOBS=$(shell nproc)

# Default silencer if installed
SILENT=$(shell which chronic)

# PHP CS Fixer
PHP_CS_FIXER=vendor/bin/php-cs-fixer
PHP_CS_FIXER_ARGS=--cache-file=build/cache/.php_cs.cache --verbose
export PHP_CS_FIXER_IGNORE_ENV=1

# PHPUnit
PHPUNIT=vendor/bin/phpunit
PHPUNIT_COVERAGE_CLOVER=--coverage-clover=build/logs/clover.xml
PHPUNIT_GROUP=default
PHPUNIT_ARGS=--coverage-xml=build/logs/coverage-xml --log-junit=build/logs/junit.xml $(PHPUNIT_COVERAGE_CLOVER)
export XDEBUG_MODE=coverage

# Phan
PHAN=vendor/bin/phan
PHAN_ARGS=-j $(JOBS) --allow-polyfill-parser
PHAN_PHP_VERSION=7.1
export PHAN_DISABLE_XDEBUG_WARN=1

# PHPStan
PHPSTAN=vendor/bin/phpstan
PHPSTAN_ARGS_TESTS=analyse src tests --level=2 -c .phpstan.neon
PHPSTAN_ARGS_SRC=analyse -c .phpstan.src.neon

# Psalm
PSALM=vendor/bin/psalm
PSALM_ARGS=--show-info=false

# Composer
COMPOSER=$(PHP) $(shell which composer)

# Infection
INFECTION=vendor/bin/infection
MIN_MSI=90
MIN_COVERED_MSI=90
INFECTION_ARGS=--min-msi=$(MIN_MSI) --min-covered-msi=$(MIN_COVERED_MSI) --threads=$(JOBS) --coverage=build/logs --log-verbosity=default --show-mutations --no-interaction

all: test

##############################################################
# Continuous Integration                                     #
##############################################################

ci-test: SILENT=
ci-test: prerequisites
	$(SILENT) $(PHP) $(PHPUNIT) $(PHPUNIT_COVERAGE_CLOVER) --verbose --group=$(PHPUNIT_GROUP)

ci-analyze: SILENT=
ci-analyze: prerequisites ci-phpunit ci-infection ci-phan ci-phpstan ci-psalm

ci-phpunit: ci-cs
	$(SILENT) $(PHP) $(PHPUNIT) $(PHPUNIT_ARGS)

ci-infection: ci-phpunit
	$(SILENT) $(PHP) $(INFECTION) $(INFECTION_ARGS)

ci-phan: ci-cs
	$(SILENT) $(PHP) $(PHAN) $(PHAN_ARGS)

ci-phpstan: ci-cs .phpstan.neon .phpstan.src.neon
	$(SILENT) $(PHP) $(PHPSTAN) $(PHPSTAN_ARGS_SRC) --no-progress
	$(SILENT) $(PHP) $(PHPSTAN) $(PHPSTAN_ARGS_TESTS) --no-progress

ci-psalm: ci-cs psalm.xml.dist
	$(SILENT) $(PHP) $(PSALM) $(PSALM_ARGS) --no-cache --shepherd

ci-cs: prerequisites
	$(SILENT) $(PHP) $(PHP_CS_FIXER) $(PHP_CS_FIXER_ARGS) --dry-run --stop-on-violation fix

##############################################################
# Development Workflow                                       #
##############################################################

.PHONY: test
test: analyze phpunit infection composer-validate yamllint

.PHONY: composer-validate
composer-validate: test-prerequisites
	$(SILENT) $(COMPOSER) validate --strict
	$(SILENT) $(COMPOSER) normalize --diff --dry-run

.PHONY: test-prerequisites
test-prerequisites: prerequisites composer.lock

.PHONY: phpunit
phpunit: cs
	rm -fr build/logs/*
	$(SILENT) $(PHP) $(PHPUNIT) $(PHPUNIT_ARGS) --verbose

.PHONY: infection
infection: phpunit
	$(SILENT) $(PHP) $(INFECTION) $(INFECTION_ARGS)

.PHONY: analyze
analyze: phan phpstan psalm

.PHONY: phan
phan: cs
	$(SILENT) $(PHP) $(PHAN) $(PHAN_ARGS) --color

.PHONY: phpstan
phpstan: cs .phpstan.src.neon .phpstan.neon
	$(SILENT) $(PHP) $(PHPSTAN) $(PHPSTAN_ARGS_SRC)
	$(SILENT) $(PHP) $(PHPSTAN) $(PHPSTAN_ARGS_TESTS)

.PHONY: psalm
psalm: cs psalm.xml.dist
	$(SILENT) $(PHP) $(PSALM) $(PSALM_ARGS)

.PHONY: cs
cs: test-prerequisites
	$(SILENT) $(PHP) $(PHP_CS_FIXER) $(PHP_CS_FIXER_ARGS) --diff fix

##############################################################
# Prerequisites Setup                                        #
##############################################################

# We need both vendor/autoload.php and composer.lock being up to date
.PHONY: prerequisites
prerequisites: report-php-version build/cache vendor/autoload.php .phan composer.lock

# Do install if there's no 'vendor'
vendor/autoload.php:
	$(SILENT) $(COMPOSER) install --prefer-dist

# If composer.lock is older than `composer.json`, do update,
# and touch composer.lock because composer not always does that
composer.lock: composer.json
	$(SILENT) $(COMPOSER) update && touch composer.lock

.phan:
	$(PHP) $(PHAN) --init --init-level=1 --init-overwrite --target-php-version=$(PHAN_PHP_VERSION) > /dev/null

build/cache:
	mkdir -p build/cache

.PHONY: report-php-version
report-php-version:
	# Using $(PHP)

.PHONY: yamllint
yamllint:
	@find .github/ -name \*.y*ml -print0 | xargs -n 1 -0 yamllint --no-warnings
	@find . -maxdepth 1 -name \*.y*ml -print0 | xargs -n 1 -0 yamllint --no-warnings

##############################################################
# Quick development testing procedure                        #
##############################################################

PHP_VERSIONS=php7.1 php7.2 php7.3 php7.4

.PHONY: quick
quick:
	make --no-print-directory -j test-all

.PHONY: test-all
test-all: $(PHP_VERSIONS)

.PHONY: $(PHP_VERSIONS)
$(PHP_VERSIONS): cs
	@if command -v $@; then make --no-print-directory PHP=$@ PHP_CS_FIXER=/bin/true phpunit; fi

.PHONY: docs
docs:
	mkdocs serve
