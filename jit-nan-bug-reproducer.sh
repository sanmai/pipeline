#!/bin/bash
#
# PHP JIT NAN Comparison Bug Reproducer
#
# This script demonstrates a bug in PHP's JIT (tracing mode) where NAN comparisons
# may incorrectly return true instead of false (violating IEEE 754).
#
# Bug: With JIT tracing enabled, `NAN > $float` or `NAN < $float` can return TRUE
# Expected: Per IEEE 754, all comparisons involving NAN should return FALSE
#
# Requirements:
#   - PHP 8.4+ with JIT support
#   - Composer dependencies installed
#
# Usage:
#   # Run with system PHP (must have JIT enabled)
#   ./jit-nan-bug-reproducer.sh
#
#   # Run with Docker
#   docker run --rm -v "$(pwd):/app" -w /app php:8.4-cli ./jit-nan-bug-reproducer.sh
#
# The bug is flaky and requires JIT warmup from running the full test suite.
# It typically reproduces within 10-20 attempts.
#

set -e

MAX_ATTEMPTS="${1:-20}"
PHP_BIN="${PHP_BIN:-php}"

# Ensure JIT is enabled
JIT_CONFIG="-d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=64M"

echo "PHP JIT NAN Comparison Bug Reproducer"
echo "======================================"
echo ""
$PHP_BIN -v
echo ""
echo "JIT Configuration:"
$PHP_BIN $JIT_CONFIG -r 'echo "  opcache.jit = " . ini_get("opcache.jit") . "\n";'
$PHP_BIN $JIT_CONFIG -r 'echo "  opcache.jit_buffer_size = " . ini_get("opcache.jit_buffer_size") . "\n";'
echo ""
echo "Running test suite up to $MAX_ATTEMPTS times until failure..."
echo ""

for i in $(seq 1 $MAX_ATTEMPTS); do
    # Run the full test suite with JIT enabled
    # The bug requires JIT traces to be built from running many tests
    # Running only RunningVarianceTest is not sufficient to trigger the bug
    output=$($PHP_BIN $JIT_CONFIG vendor/bin/phpunit --group=default 2>&1) || true

    if echo "$output" | grep -q "FAILURES\|Failed asserting"; then
        echo "=== BUG REPRODUCED on attempt $i ==="
        echo ""
        echo "$output" | grep -A5 "testNAN\|Failed asserting\|FAILURES"
        echo ""
        echo "The test 'testNAN' failed because getMin() or getMax() returned NAN"
        echo "instead of the expected value (M_PI = 3.141592653589793)."
        echo ""
        echo "This demonstrates that PHP JIT incorrectly evaluated 'NAN > \$float'"
        echo "as TRUE, causing the max value to be overwritten with NAN."
        exit 1
    fi

    if (( i % 10 == 0 )); then
        echo "  Completed $i attempts..."
    fi
done

echo ""
echo "Bug did not reproduce in $MAX_ATTEMPTS attempts."
echo "Try running again or increasing MAX_ATTEMPTS."
exit 0
