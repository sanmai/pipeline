# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP library called `sanmai/pipeline` that provides functional programming capabilities for working with data pipelines using lazy evaluation. The library supports PHP 8.2+ and implements streaming pipelines similar to the pipe operator (`|>`) in functional languages.

## Essential Development Commands

### Testing
- `make test` - Run full test suite (analysis, unit tests, mutation testing)
- `make phpunit` - Run PHPUnit tests with coverage
- `vendor/bin/phpunit tests/SpecificTest.php` - Run a single test file
- `vendor/bin/phpunit --filter methodName` - Run specific test method

### Code Quality
- `make cs` - Fix code style issues (PHP CS Fixer)
- `make analyze` - Run all static analysis tools
- `make phan` - Run Phan static analyzer
- `make phpstan` - Run PHPStan static analysis
- `make psalm` - Run Psalm static analysis
- `make infection` - Run mutation testing

### Build & Validation
- `composer install` - Install dependencies
- `composer update` - Update dependencies
- `composer validate --strict` - Validate composer.json
- `composer normalize` - Normalize composer.json

## Architecture & Code Structure

### Core Components

1. **Main Pipeline Class**: `src/Standard.php`
   - Implements `IteratorAggregate` and `Countable`
   - All methods return the same instance (mutable design, as generators are)
   - Uses generators extensively for lazy evaluation

2. **Helper Functions**: `src/functions.php`
   - Entry points: `map()`, `take()`, `fromArray()`, `fromValues()`, `zip()`
   - All return Pipeline instances

3. **Statistical Helper**: `src/Helper/RunningVariance.php`
   - Implements Welford's online algorithm for variance calculation
   - Used by `runningVariance()` and `finalVariance()` methods

### Key Design Principles

1. **Lazy Evaluation**: Operations are deferred until results are consumed
   - Use generators (`yield`) to maintain laziness
   - Non-generator inputs execute eagerly

2. **No Exceptions**: The library itself doesn't define or throw exceptions
   - Some edge cases may still cause PHP language errors
   - Generally returns sensible defaults or empty results

3. **Mutable Pipeline**: Each method modifies and returns the same instance
   - Not thread-safe (not an issue in PHP)
   - Allows flexible pipeline composition

### Testing Approach

- Comprehensive unit tests in `tests/` directory
- Uses PHPUnit with coverage reporting
- Mutation testing with Infection (90% MSI required)
- Multiple static analyzers for code quality

### Performance Considerations

- Memory efficient for large datasets through lazy evaluation
- Use `stream()` to ensure lazy paths are used
- Avoid `iterator_to_array()` - use `toList()` or `toAssoc()` instead
- Keys are preserved on best-effort basis
