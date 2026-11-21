# Pipeline: A PHP Functional Programming Library

**About This Documentation**: This guide, primarily authored by an LLM with human oversight, is written for both developers and LLMs. It describes the library's design, best practices, and idiomatic usage. If you find an inconsistency, please [open an issue](https://github.com/sanmai/pipeline/issues/new).

`sanmai/pipeline` is a PHP library for functional-style data processing. It uses lazy evaluation and a fluent, chainable interface. It implements streaming pipelines for PHP, similar to the pipe operator (`|>`) in functional languages.

## Key Features

- **Lazy Evaluation**: The library defers operations until the result is consumed. This keeps memory usage low with large datasets.
- **Fluent Interface**: Chain operations in a single expression.
- **Zero Dependencies**: The library requires only a recent version of PHP. It has no external dependencies.
- **Generator-Based**: The library uses PHP generators for stream-based processing.
- **No Exceptions**: The library neither defines nor throws exceptions. Edge cases produce default or empty results.
- **Type-Safe**: The library supports static analysis with PHPStan and Psalm. [Learn more about Type Safety](generics.md).

## Core Concepts

### The Streaming-First Principle

The library uses lazy evaluation with PHP generators. You can process datasets of any size (small arrays, multi-gigabyte files, or infinite data streams) with low and predictable memory usage.

For large data, use streaming data sources, such as `SplFileObject` or custom generators. The library also has optimizations for in-memory arrays. Use these for smaller datasets.

### The `Pipeline\Standard` Class

The `Pipeline\Standard` class is the main class of the library. Each instance is a data processing pipeline. Every non-terminal method modifies the pipeline in place and returns the *same instance*. Pipelines are mutable by design, like the generators they use. If you store two references to a single pipeline, operations through either reference change the same pipeline. A processing stage that you add stays in the pipeline even if you do not capture the return value.

```php
use function Pipeline\take;

$pipelineA = take([1, 2, 3]);
$pipelineB = $pipelineA; // Both variables reference the same pipeline

$pipelineA->map(fn($x) => $x * 2); // This modifies the shared pipeline

var_dump($pipelineB->toList()); // [2, 4, 6]
```

A pipeline is iterable, so a pipeline can be the input of another pipeline:

```php
$firstPipeline = take(range(1, 5))->map(fn($n) => $n * 10);

$secondPipeline = take($firstPipeline)->filter(fn($n) => $n > 20);

var_dump($secondPipeline->toList()); // [30, 40, 50]
```

### Hybrid Execution Model

The library uses two execution paths:

- **Streaming/Lazy (Recommended)**: When the source is an iterator or generator, all operations are evaluated lazily. The pipeline processes a single element at a time when a terminal method (e.g., `toList()` or `each()`) runs.
- **Array-Optimized (Convenience)**: When the pipeline contains an array, many methods (`filter()`, `cast()`, `chunk()`, `slice()`, and others) execute eagerly with native array functions. This is faster for small arrays, but it creates intermediate arrays in memory.
- **Opting Out with `stream()`**: To process a large array element by element, call `stream()` first. It converts the array into a generator, and all subsequent operations then use the lazy path.

### Terminal vs. Non-Terminal Operations

- **Non-Terminal**: Return the pipeline instance for further chaining (e.g., `map()`, `filter()`, `slice()`).
- **Terminal**: Consume the pipeline and return a final result (e.g., `reduce()`, `fold()`, `toList()`, `count()`, `each()`).

The pipeline defers execution until a terminal operation runs or a `foreach` loop iterates it. You cannot rewind a consumed pipeline, as with the generators it wraps. To pause and resume iteration, use [`cursor()`](api/collection.md#cursor).

### Method Categories

1. **[Creation](api/creation.md)**: Initialize a pipeline from a data source.
2. **[Transformation](api/transformation.md)**: Transform the data in the pipeline.
3. **[Filtering](api/filtering.md)**: Remove elements.
4. **[Aggregation](api/aggregation.md)**: Reduce the pipeline to a single value (terminal).
5. **[Collection](api/collection.md)**: Convert the pipeline into an array or iterate it (terminal).
6. **[Utility](api/utility.md)**: Side effects, sampling, keys-and-values reshaping, and more.
7. **[Statistics](api/statistics.md)**: Online statistical analysis of numeric streams.

## Quick Example

```php
use function Pipeline\take;

// This pipeline will:
// 1. Take numbers from 1 to 100
// 2. Keep only the even numbers
// 3. Square each number
// 4. Take the first 5 results
// 5. Sum them
$result = take(range(1, 100))
    ->filter(fn($n) => $n % 2 === 0)
    ->map(fn($n) => $n ** 2)
    ->slice(0, 5)
    ->reduce(); // 4 + 16 + 36 + 64 + 100 = 220

// Combining multiple data sources
$result = take([1, 2, 3])
    ->append([4, 5, 6])
    ->prepend([0])
    ->map(fn($x) => $x * 2)
    ->toList(); // [0, 2, 4, 6, 8, 10, 12]
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

// From any iterable: an array, iterator, or generator
$pipeline = take($data);

// Same thing, using the constructor
$pipeline = new Standard($data);

// From a generator function
$pipeline = map(function () {
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

This example processes a large file with low memory usage:

```php
$count = 0;

take(new SplFileObject('huge.log'))
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->runningCount($count)
    ->each(fn($line) => error_log($line));

echo "Processed $count error lines\n";
```

The pipeline keeps a single line in memory at a time, for any file size.

## Error Handling

- The library never throws exceptions. Its source code contains no `throw` statements.
- Empty or unprimed pipelines produce empty values: `toList()` returns `[]`, `count()` returns `0`, `min()` returns `null`.
- Your own callbacks can throw exceptions. PHP language errors (such as a `TypeError` from a mismatched callback signature) also occur as usual.

## Performance Considerations

- **Stream large arrays**: Call `stream()` before you process a large array. This forces element-by-element processing and prevents intermediate arrays.
- **Prefer `toList()` and `toAssoc()`** over `iterator_to_array()`: with duplicate keys, `iterator_to_array()` drops values without a warning. `toList()` returns every value.
- **Count without consuming**: `count()` is a terminal operation. Use `runningCount()` to count values while the pipeline processes them.
- **Mind negative `slice()` arguments**: on a streaming pipeline, negative offsets and lengths require buffering. See [Performance](advanced/performance.md).

## Next Steps

- **[Installation](quickstart/installation.md)**: Install the library.
- **[Basic Usage](quickstart/basic-usage.md)**: Learn common usage patterns.
- **[Walkthrough](quickstart/walkthrough.md)**: Follow a complete example, step by step.
- **[Cookbook](cookbook/index.md)**: Find recipes for common problems.
- **[API Reference](api/creation.md)**: Read the complete method documentation.
- **[Advanced Usage](advanced/complex-pipelines.md)**: Learn advanced techniques.
