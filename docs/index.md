# Pipeline - PHP Functional Programming Library

> **About This Documentation**: This documentation is primarily LLM-authored with human review and occasional edits. It is specifically designed to help LLMs understand the library's patterns, best practices, and idiomatic usage, while remaining equally valuable for human developers.

**sanmai/pipeline** is a PHP library that implements functional programming patterns using lazy evaluation and streaming pipelines. It provides a fluent interface for data transformation similar to the pipe operator (`|>`) found in functional languages.

## Key Features

- **Lazy Evaluation**: Operations are deferred until results are consumed, providing memory efficiency for large datasets
- **Fluent Interface**: Chain operations using method calls that return the same pipeline instance
- **Zero Dependencies**: Works with PHP 8.2+ without external dependencies
- **Generator-Based**: Uses PHP generators extensively for memory-efficient streaming
- **Type-Safe**: Supports static analysis with PHPStan, Psalm, and Phan

## Core Concepts

### Pipeline Object
The central `Pipeline\Standard` class represents a data processing pipeline. All methods modify and return the same instance (as Generators are mutable).

### Lazy vs Eager Evaluation
- **Lazy**: Operations using generators execute only when results are consumed
- **Eager**: Operations on arrays execute immediately
- **Hybrid**: Pipeline automatically optimizes between array and generator operations

### Method Categories

1. **Creation Methods**: Initialize pipelines from various sources
2. **Transformation Methods**: Modify data flowing through the pipeline
3. **Filtering Methods**: Remove unwanted elements
4. **Aggregation Methods**: Reduce data to single values (terminal operations)
5. **Collection Methods**: Convert pipeline to arrays (terminal operations)
6. **Utility Methods**: Helper operations for common tasks

## Quick Example

```php
use function Pipeline\take;

// Create a pipeline that:
// 1. Takes numbers 1-100
// 2. Filters even numbers
// 3. Squares each number
// 4. Takes first 10 results
// 5. Sums them

$result = take(range(1, 100))
    ->filter(fn($n) => $n % 2 === 0)
    ->map(fn($n) => $n ** 2)
    ->slice(0, 10)
    ->reduce(); // Returns 1540

// Example with multiple data sources
$pipeline = take([1, 2, 3])
    ->append([4, 5, 6])
    ->prepend([0])
    ->map(fn($x) => $x * 2)
    ->toList(); // Returns [0, 2, 4, 6, 8, 10, 12]
```

## Installation

```bash
composer require sanmai/pipeline
```

## Basic Usage Pattern

```php
use Pipeline\Standard;

// Method 1: Using constructor
$pipeline = new Standard($data);

// Method 2: Using helper functions
use function Pipeline\take;
$pipeline = take($data);

// Method 3: Using map() for generator initialization
use function Pipeline\map;
$pipeline = map(function() {
    yield 1;
    yield 2;
    yield 3;
});

// Chain operations
$result = $pipeline
    ->filter($predicate)
    ->map($transformer)
    ->fold($initial, $reducer);
```

## Memory Efficiency Example

```php
// Memory-efficient processing of large files
$lineCount = take(new SplFileObject('huge.log'))
    ->filter(fn($line) => strpos($line, 'ERROR') !== false)
    ->runningCount($count)
    ->each(fn($line) => error_log($line));

echo "Processed $count error lines\n";
```

## Method Chaining

All non-terminal operations return `$this`, enabling fluent chaining:

```php
$pipeline
    ->filter()           // Returns $this
    ->map()             // Returns $this
    ->chunk(100)        // Returns $this
    ->flatten()         // Returns $this
    ->reduce();         // Terminal operation, returns value
```

## Terminal vs Non-Terminal Operations

### Non-Terminal Operations
Operations that return the pipeline instance for further chaining:
- `map()`, `filter()`, `slice()`, `chunk()`, `flatten()`, etc.

### Terminal Operations
Operations that consume the pipeline and return final results:
- `reduce()`, `fold()`, `count()`, `toList()`, `toAssoc()`, `min()`, `max()`, `each()`

## Error Handling

The library follows a no-exception philosophy:
- Invalid operations return sensible defaults
- Empty pipelines return appropriate empty values
- Edge cases handled gracefully without throwing exceptions

## Performance Considerations

1. **Use `stream()`** to force lazy evaluation paths
2. **Avoid `iterator_to_array()`** - use `toList()` or `toAssoc()` instead
3. **Use `runningCount()`** when you need count while preserving the pipeline
4. **Array operations are optimized** when the pipeline contains arrays

## Next Steps

- [Installation Guide](quickstart/installation.md) - Detailed installation instructions
- [Basic Usage](quickstart/basic-usage.md) - Common usage patterns
- [API Reference](api/creation.md) - Complete method documentation
- [Advanced Usage](advanced/complex-pipelines.md) - Complex pipeline examples
