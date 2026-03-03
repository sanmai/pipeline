#!/bin/bash

chronic docker build -t pipeline-test .

mkdir -p .composer-cache

docker run --rm \
    -v "$PWD:/app" \
    -v "$PWD/.composer-cache:/opt/pipeline/.composer" \
    pipeline-test \
    sh -c '
        chronic composer remove --no-update --dev phpstan/phpstan vimeo/psalm infection/infection friendsofphp/php-cs-fixer php-coveralls/php-coveralls
        for n in $(seq 1 10); do
            chronic php -d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=64M /usr/local/bin/composer update --prefer-dist --no-interaction --no-progress --dry-run -vvv || { echo "FAILED on run $n"; exit 1; }
            echo "OK ($n)"
        done
        echo "All 10 runs succeeded"
    '
