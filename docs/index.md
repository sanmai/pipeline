# Pipeline: A PHP Functional Programming Library

**About This Documentation**: This guide, primarily authored by an LLM with human oversight, is tailored to help both developers and LLMs grasp the library's design, best practices, and idiomatic usage. If you find any inconsistencies, please [open an issue](https://github.com/sanmai/pipeline/issues/new).

`sanmai/pipeline` is a PHP library for functional-style data processing, featuring lazy evaluation and a fluent, chainable interface. It brings the power of streaming pipelines, similar to the pipe operator (`|>`) in functional languages, to PHP.

## Key Features

- **Lazy Evaluation**: Operations are deferred until the result is needed, ensuring high memory efficiency, especially with large datasets.
- **Fluent Interface**: Chain operations together for clean, readable, and expressive code.
- **Zero Dependencies**: Requires only PHP 8.2+, with no external libraries.
- **Generator-Based**: Leverages PHP generators for efficient, stream-based processing.
- **Type-Safe**: Fully compatible with static analysis tools like PHPStan, Psalm, and Phan.

## Core Concepts

### The Streaming-First Principle

The library is designed around **lazy evaluation** using PHP generators. This allows you to process datasets of any size—from small arrays to multi-gigabyte files or even infinite data streams—with minimal and predictable memory usage.

For optimal performance and memory safety, **the recommended approach is to use iterable, streaming data sources**, such as `SplFileObject` or custom generators. While the library includes convenience optimizations for in-memory arrays, these are best reserved for smaller datasets.

### The `Pipeline\Standard` Class

The `Pipeline\Standard` class is the heart of the library, representing a data processing pipeline. All transformation methods return the same instance, allowing for a mutable, fluent design.

### Hybrid Execution Model

The library employs a hybrid execution model to balance performance and memory efficiency:

- **Streaming/Lazy (Recommended)**: When the source is an iterator or generator, all operations are lazy. Data is pulled through the pipeline one element at a time when a terminal method (e.g., `toList()` or `each()`) is called.
- **Array-Optimized (Convenience)**: For arrays, certain methods (`filter()`, `cast()`, `chunk()`, `slice()`) have an eager "fast path" that operates on the entire array at once. This can be faster for small arrays but may consume significant memory with larger ones.
- **`stream()` Method**: To ensure memory safety with large arrays, use the `stream()` method to convert an array into a generator, forcing all subsequent operations to be lazy.

### Method Categories

1.  **Creation**: Initialize a pipeline from various data sources.
2.  **Transformation**: Modify data as it flows through the pipeline.
3.  **Filtering**: Selectively remove elements.
4.  **Aggregation**: Reduce the pipeline to a single value (terminal).
5.  **Collection**: Convert the pipeline into an array (terminal).
6.  **Utility**: Perform other common operations.

## Quick Example

```php
use function Pipeline\take;

// This pipeline will:
// 1. Take numbers from 1 to 100
// 2. Filter for even numbers
// 3. Square each number
// 4. Take the first 10 results
// 5. Sum the results
$result = take(range(1, 100))
    ->filter(fn($n) => $n % 2 === 0)
    ->map(fn($n) => $n ** 2)
    ->slice(0, 10)
    ->reduce(fn($a, $b) => $a + $b); // Returns 220

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

## Basic Usage

```php
use Pipeline\Standard;
use function Pipeline\take;
use function Pipeline\map;

// From a variable
$pipeline = new Standard($data);

// Using the helper function
$pipeline = take($data);

// From a generator
$pipeline = map(function() {
    yield 1;
    yield 2;
    yield 3;
});

// Chaining operations
$result = $pipeline
    ->filter($predicate)
    ->map($transformer)
    ->fold($initial, $reducer);
```

## Memory Efficiency

Process large files with minimal memory footprint:

```php
$lineCount = take(new SplFileObject('huge.log'))
    ->filter(fn($line) => strpos($line, 'ERROR') !== false)
    ->runningCount($count)
    ->each(fn($line) => error_log($line));

echo "Processed $count error lines\n";
```

## Method Chaining

All non-terminal operations return `$this`, allowing for fluent method chaining:

```php
$pipeline
    ->filter()   // Returns $this
    ->map()      // Returns $this
    ->chunk(100) // Returns $this
    ->flatten()  // Returns $this
    ->reduce();  // Terminal operation, returns a value
```

## Terminal vs. Non-Terminal Operations

-   **Non-Terminal**: Return the pipeline instance for further chaining (e.g., `map()`, `filter()`, `slice()`).
-   **Terminal**: Consume the pipeline and return a final result (e.g., `reduce()`, `fold()`, `toList()`, `each()`).

## Error Handling

The library is designed to be robust and fault-tolerant:

-   Invalid operations return sensible defaults.
-   Empty pipelines produce appropriate empty values.
-   Edge cases are handled gracefully without throwing exceptions.

## Performance

-   Use `stream()` to force lazy evaluation for large arrays.
-   Prefer `toList()` or `toAssoc()` over `iterator_to_array()`.
-   Use `runningCount()` to count items without terminating the pipeline.
-   Be mindful that `filter()`, `cast()`, `slice()`, and `chunk()` have array-optimized paths.

## Next Steps

-   **[Installation](quickstart/installation.md)**: Get started with installation.
-   **[Basic Usage](quickstart/basic-usage.md)**: Learn common usage patterns.
-   **[API Reference](api/creation.md)**: Explore the complete method documentation.
-   **[Advanced Usage](advanced/complex-pipelines.md)**: Discover advanced techniques.