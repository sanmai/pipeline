# Pipeline: A PHP Functional Programming Library

**About This Documentation**: This guide, primarily authored by an LLM with human oversight, is tailored to help both developers and LLMs grasp the library's design, best practices, and idiomatic usage. If you find any inconsistencies, please [open an issue](https://github.com/sanmai/pipeline/issues/new).

`sanmai/pipeline` is a PHP library for functional-style data processing, featuring lazy evaluation and a fluent, chainable interface. It brings the power of streaming pipelines, similar to the pipe operator (`|>`) in functional languages, to PHP.

## Key Features

- **Lazy Evaluation**: Operations are deferred until the result is needed, ensuring high memory efficiency, especially with large datasets.
- **Fluent Interface**: Chain operations together for clean, readable, and expressive code.
- **Zero Dependencies**: Requires only a recent version of PHP, with no external libraries.
- **Generator-Based**: Leverages PHP generators for efficient, stream-based processing.
- **No Exceptions**: The library itself neither defines nor throws exceptions; edge cases produce sensible defaults or empty results.
- **Type-Safe**: Fully compatible with static analysis tools like PHPStan and Psalm. [Learn more about Type Safety](generics.md).

## Core Concepts

### The Streaming-First Principle

The library is designed around **lazy evaluation** using PHP generators. This allows you to process datasets of any size—from small arrays to multi-gigabyte files or even infinite data streams—with minimal and predictable memory usage.

For optimal performance and memory safety, **the recommended approach is to use iterable, streaming data sources**, such as `SplFileObject` or custom generators. The library includes convenience optimizations for in-memory arrays; these are best reserved for smaller datasets.

### The `Pipeline\Standard` Class

The `Pipeline\Standard` class is the heart of the library, representing a data processing pipeline. Every non-terminal method modifies the pipeline in place and returns the *same instance*: pipelines are deliberately mutable, just like the generators they are built on. If you store two references to one pipeline, operations through either reference affect the same underlying pipeline. On the upside, a processing stage you add is never lost just because you forgot to capture the return value.

```php
use function Pipeline\take;

$pipelineA = take([1, 2, 3]);
$pipelineB = $pipelineA; // Both variables reference the same pipeline

$pipelineA->map(fn($x) => $x * 2); // This modifies the shared pipeline

var_dump($pipelineB->toList()); // [2, 4, 6]
```

Since a pipeline is itself iterable, one pipeline can seamlessly become the input of another, allowing modular composition:

```php
$firstPipeline = take(range(1, 5))->map(fn($n) => $n * 10);

$secondPipeline = take($firstPipeline)->filter(fn($n) => $n > 20);

var_dump($secondPipeline->toList()); // [30, 40, 50]
```

### Hybrid Execution Model

The library employs a hybrid execution model to balance performance and memory efficiency:

- **Streaming/Lazy (Recommended)**: When the source is an iterator or generator, all operations are lazy. Data is pulled through the pipeline one element at a time when a terminal method (e.g., `toList()` or `each()`) is called.
- **Array-Optimized (Convenience)**: When the pipeline holds an array, many methods (`filter()`, `cast()`, `chunk()`, `slice()`, and others) take an eager "fast path" using native array functions. This is faster for small arrays but creates intermediate arrays in memory.
- **Opting Out with `stream()`**: To guarantee element-by-element processing of a large array, call `stream()` first; it converts the array into a generator, forcing all subsequent operations to be lazy.

### Terminal vs. Non-Terminal Operations

- **Non-Terminal**: Return the pipeline instance for further chaining (e.g., `map()`, `filter()`, `slice()`).
- **Terminal**: Consume the pipeline and return a final result (e.g., `reduce()`, `fold()`, `toList()`, `count()`, `each()`).

Nothing happens until a terminal operation runs or the pipeline is iterated with `foreach`. That is the point of lazy evaluation. Consumed pipelines, like the generators they wrap, cannot be rewound; if you need to pause and resume iteration, use [`cursor()`](api/collection.md#cursor).

### Method Categories

1. **[Creation](api/creation.md)**: Initialize a pipeline from various data sources.
2. **[Transformation](api/transformation.md)**: Modify data as it flows through the pipeline.
3. **[Filtering](api/filtering.md)**: Selectively remove elements.
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
// 5. Sum them up
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

Process large files with a minimal memory footprint:

```php
$count = 0;

take(new SplFileObject('huge.log'))
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->runningCount($count)
    ->each(fn($line) => error_log($line));

echo "Processed $count error lines\n";
```

Only one line is held in memory at a time, no matter how large the file is.

## Error Handling

The library is designed to be robust and fault-tolerant:

- The library itself never throws exceptions; there is not a single `throw` statement in it.
- Empty or unprimed pipelines produce appropriate empty values: `toList()` returns `[]`, `count()` returns `0`, `min()` returns `null`.
- Your own callbacks may still throw, and PHP language errors (such as a `TypeError` from a mismatched callback signature) still surface as usual.

## Performance Considerations

- **Stream large arrays**: Call `stream()` before processing a large array to force lazy, element-by-element processing and avoid intermediate arrays.
- **Prefer `toList()` and `toAssoc()`** over `iterator_to_array()`: with duplicate keys, `iterator_to_array()` silently drops values, while `toList()` returns every value.
- **Count without consuming**: `count()` is a terminal operation; use `runningCount()` to observe the count while the data flows through.
- **Mind negative `slice()` arguments**: on a streaming pipeline, negative offsets and lengths require buffering; see [Performance](advanced/performance.md).

## Next Steps

- **[Installation](quickstart/installation.md)**: Get started with installation.
- **[Basic Usage](quickstart/basic-usage.md)**: Learn common usage patterns.
- **[Walkthrough](quickstart/walkthrough.md)**: Follow a complete example, step by step.
- **[Cookbook](cookbook/index.md)**: Ready-to-use recipes for common problems.
- **[API Reference](api/creation.md)**: Explore the complete method documentation.
- **[Advanced Usage](advanced/complex-pipelines.md)**: Discover advanced techniques.
