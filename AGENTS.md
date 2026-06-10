# AGENTS.md

## Project Overview

This is a PHP library called `sanmai/pipeline` that provides functional programming capabilities for working with data pipelines using lazy evaluation. The library implements streaming pipelines similar to the pipe operator (`|>`) in functional languages.

**Documentation Note**: The documentation in the `docs/` directory is primarily LLM-authored and specifically designed to help AI assistants understand the library's patterns, best practices, and idiomatic usage. It emphasizes explicit operations over implicit magic, making the library's behavior predictable and easy to reason about.

## Essential Development Commands

### Testing
- `make -j -k` - Run full test suite (all in parallel)
- `make phpunit` - Run PHPUnit tests with line coverage enforcement
- `php vendor/bin/phpunit tests/SpecificTest.php` - Run a single test file
- `php vendor/bin/phpunit --filter methodName` - Run specific test method

### Code Quality
- `make cs` - Fix code style issues (PHP CS Fixer)
- `make analyze` - Run all static analysis tools
- `make phpstan` - Run PHPStan static analysis
- `make infection` - Run mutation testing (with a set MSI minimum)

### Build & Validation
- `composer install` - Install dependencies
- `composer update` - Update dependencies
- `composer validate --strict` - Validate composer.json
- `composer normalize` - Normalize composer.json format

## Architecture & Code Structure

### Core Components

1. **Main Pipeline Class**: `src/Standard.php` - see main @README.md
   - Implements `IteratorAggregate` and `Countable`
   - All methods return the same instance (mutable design, as generators are)
   - Uses generators extensively for lazy evaluation
   - Default callbacks for common operations (filter removes falsy, reduce sums)
   - Internal state is a single `array|Iterator $pipeline` property with three states: unset (unprimed), array (eager), iterator (lazy). The private `empty()` check (unset or `[]`) gates most methods as no-ops.
   - `IteratorAggregate` inputs are unwrapped in `replace()` before storage, so downstream code only ever sees arrays or plain `Iterator`s.
   - Most methods have dual paths: an array shortcut (`array_map`, `array_filter`, `array_slice`, ...) and a generator path. This is why arrays execute eagerly while generators stay lazy.
   - `map()` flattens returned generators using their own keys (SelectMany); `cast()` is strict 1:1 and preserves original keys.
   - `select()` is the canonical filter (strict: drops only `null`/`false`); `filter()` is the alias with `strict: false` (drops all falsy).
   - `peek()` is destructive: it eagerly consumes the first N items (as tuples, to survive duplicate keys) and leaves the rest in the pipeline.
   - Callbacks for `each()`/`tap()`/`select(onReject:)` receive `($value, $key)`; an `ArgumentCountError` from arity-sensitive internal functions (e.g. `printf(...)`) is caught once and the callable permanently wrapped to single-argument form (`callWithValueKey()`).

2. **Helper Functions**: @src/functions.php
   - Entry points: `map()`, `take()`, `fromArray()`, `fromValues()`, `zip()`
   - All return Pipeline instances

3. **Statistical Helper**: `src/Helper/RunningVariance.php`
   - Implements Welford's online algorithm for variance calculation
   - Used by `runningVariance()` and `finalVariance()` methods

4. **Cursor Iterator Helper**: `src/Helper/CursorIterator.php`
   - Extends `NoRewindIterator` with auto-advance behavior
   - Unlike `NoRewindIterator` (which repeats current element on resume), advances past it like a database cursor
   - Used by `cursor()` method to maintain position across multiple iterations
   - Enables partial consumption patterns (e.g., peek operations)

### Key Design Principles

1. **Lazy Evaluation**: Operations are deferred until results are consumed
   - Use generators (`yield`) to maintain laziness
   - Non-generator inputs execute eagerly
   - Nothing happens until you iterate or reduce

2. **No Exceptions**: The library itself doesn't define or throw exceptions
   - Some edge cases may still cause PHP language errors
   - Generally returns sensible defaults or empty results

3. **Mutable Pipeline**: Each method modifies and returns the same instance
   - Not thread-safe (not an issue in PHP)
   - Allows flexible pipeline composition
   - Cannot reuse/rewind pipelines after consumption (PHP generator limitation)

### Testing Approach

- Comprehensive unit tests in `tests/` directory organized by functionality
- PHPUnit with coverage metadata required (`#[CoversClass]`/`#[CoversMethod]` attributes)
- 100% line coverage enforced in CI (`coverage-check ... 100`)
- Mutation testing with Infection (100% MSI and covered MSI required)
- Multiple static analyzers: Phan, Psalm, PHPStan (level 2 over src+tests, plus level max over `tests/Inference/` for generics/type-inference tests)
- CI matrix testing across multiple PHP versions
- Data providers wrap array inputs in `ArrayIterator` variants to exercise both the eager (array) and lazy (iterator) paths
- `DocumentationTest` and `DocumentationMethodsTest` enforce that README and `docs/` stay in sync with the actual public API: every public method needs a README mention and header, and documented methods must exist

### Performance Considerations

- Memory efficient for large datasets through lazy evaluation
- Use `stream()` to ensure lazy paths are used
- Avoid `iterator_to_array()` - use `toList()` or `toAssoc()` instead
- Keys are preserved on best-effort basis
- For counting operations, prefer `runningCount()` to avoid terminal operations

### Practice What You Preach

- Nested loops are unacceptable anywhere in the project, including tests.
- That said, one-off `foreach` loops are allowed for clarity and simplicity.

## Testing

Instead of inline comments in tests, use assertion-level messaging.

Bad:

```
// Buffer is empty before valid() is called
$this->assertCount(0, $buffer);
```

Good:

```
$this->assertCount(0, $buffer, 'Buffer must be empty before valid() is called');
```
