.PHONY: ci test prerequisites

# Use any most recent PHP version
PHP=$(shell which php7.2 || which php7.1 || which php)

# Default parallelism
JOBS=$(shell nproc)

# Default silencer if installed
SILENT=$(shell which chronic)

# PHP CS Fixer
PHP_CS_FIXER=vendor/bin/php-cs-fixer
PHP_CS_FIXER_ARGS=--cache-file=build/cache/.php_cs.cache --verbose

# PHPUnit
PHPUNIT=vendor/bin/phpunit
PHPUNIT_ARGS=--coverage-xml=coverage/coverage-xml --log-junit=coverage/phpunit.junit.xml

# Coverage Check
COVERAGE_CHECK=vendor/bin/coverage-check
COVERAGE_CHECK_ARGS=build/logs/clover.xml 100

# Phan
PHAN=vendor/bin/phan
PHAN_ARGS=-j $(JOBS)
export PHAN_DISABLE_XDEBUG_WARN=1

# PHPStan
PHPSTAN=vendor/bin/phpstan
PHPSTAN_ARGS=analyse src tests --level=2 -c .phpstan.neon

# Composer
COMPOSER=composer

# Infection
INFECTION=vendor/bin/infection
MIN_MSI=95
MIN_COVERED_MSI=100
INFECTION_ARGS=--min-msi=$(MIN_MSI) --min-covered-msi=$(MIN_COVERED_MSI) --threads=$(JOBS) --coverage=coverage

all: test

##############################################################
# Continuous Integration                                     #
##############################################################

ci: SILENT=
ci: prerequisites ci-phpunit ci-analyze
	$(SILENT) $(COMPOSER) validate --strict

ci-phpunit: ci-cs
	$(SILENT) $(PHP) $(PHPUNIT) $(PHPUNIT_ARGS)
	$(SILENT) $(COVERAGE_CHECK) $(COVERAGE_CHECK_ARGS)
	$(SILENT) $(PHP) $(INFECTION) $(INFECTION_ARGS) --quiet

ci-analyze: ci-cs
	$(SILENT) $(PHP) $(PHAN) $(PHAN_ARGS)
	$(SILENT) $(PHP) $(PHPSTAN) $(PHPSTAN_ARGS) --no-progress

ci-cs: prerequisites
	$(SILENT) $(PHP) $(PHP_CS_FIXER) $(PHP_CS_FIXER_ARGS) --dry-run --stop-on-violation fix

##############################################################
# Development Workflow                                       #
##############################################################

test: phpunit analyze
	$(SILENT) $(COMPOSER) validate --strict

test-prerequisites: prerequisites composer.lock

phpunit: cs
	$(SILENT) $(PHP) $(PHPUNIT) $(PHPUNIT_ARGS) --verbose
	$(SILENT) $(COVERAGE_CHECK) $(COVERAGE_CHECK_ARGS)
	$(SILENT) $(PHP) $(INFECTION) $(INFECTION_ARGS) --log-verbosity=2 --show-mutations

analyze: cs
	$(SILENT) $(PHP) $(PHAN) $(PHAN_ARGS) --color
	$(SILENT) $(PHP) $(PHPSTAN) $(PHPSTAN_ARGS)

cs: test-prerequisites
	$(SILENT) $(PHP) $(PHP_CS_FIXER) $(PHP_CS_FIXER_ARGS) --diff fix

##############################################################
# Prerequisites Setup                                        #
##############################################################

# We need both vendor/autoload.php and composer.lock being up to date
.PHONY: prerequisites
prerequisites: build/cache vendor/autoload.php .phan composer.lock

# Do install if there's no 'vendor'
vendor/autoload.php:
	$(SILENT) $(COMPOSER) install --prefer-dist

# If composer.lock is older than `composer.json`, do update,
# and touch composer.lock because composer not always does that
composer.lock: composer.json
	$(SILENT) $(COMPOSER) update && touch composer.lock

.phan:
	$(PHP) $(PHAN) --init --init-level=1 --init-overwrite --target-php-version=native > /dev/null

build/cache:
	mkdir -p build/cache

