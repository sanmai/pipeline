# Performance Optimization

This guide provides techniques for optimizing your pipelines for speed and memory efficiency.

## The Power of Streaming

The library's core strength is its ability to process large datasets with minimal memory usage through streaming. Data is pulled through the pipeline one element at a time, and processing stops as soon as the consumer has what it needs.

**Example: Finding Errors in a Large Log File**

Consider the task of finding the first five "ERROR" lines in a 10 GB log file.

**The Inefficient Way (Loading into Memory)**

```php
// Warning: This will likely exhaust your server's memory.
$lines = file('huge-10GB.log'); // Loads the entire 10 GB file into memory
$errors = take($lines)
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->slice(0, 5)
    ->toList();
```

**The Efficient Way (Streaming)**

```php
// This is memory-safe and fast.
$errors = take(new SplFileObject('huge-10GB.log'))
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->slice(0, 5)
    ->toList();
```

The streaming approach reads the file line by line and—just as importantly—stops reading as soon as the fifth error is found. If the errors appear early, almost none of the file is read at all.

## Array Fast Paths vs `stream()`

When a pipeline holds a plain array, many methods take an eager fast path using native array functions: `filter()` and `select()` use `array_filter()`, `cast()` uses `array_map()`, `slice()` uses `array_slice()`, `chunk()` uses `array_chunk()`, and similarly for `keys()`, `values()`, `flip()`, `tuples()`, `fold()`, `count()`, `min()`, and `max()`. Notably, `map()` is always lazy, regardless of the source.

These fast paths are quicker for small-to-medium arrays, but each one creates a new intermediate array in memory:

```php
// Without stream(): intermediate arrays at each eager step
$result = take($largeArray)
    ->filter($predicate)  // New filtered array in memory
    ->cast($transformer)  // Another transformed array
    ->toList();
```

The `stream()` method converts the pipeline to a generator, after which every element flows through the entire chain one at a time:

```php
// With stream(): flat memory usage
$result = take($largeArray)
    ->stream()
    ->filter($predicate)
    ->cast($transformer)
    ->toList();
```

### When to Use `stream()`

Use `stream()` when:

- Working with large arrays that would create memory pressure
- Transformations are expensive and you might not consume all elements
- You want predictable memory usage regardless of input size

### Trade-offs

- **Memory**: `stream()` keeps peak memory flat; fast paths allocate whole arrays.
- **Speed**: Native array functions are typically faster for small-to-medium datasets.
- **Rule of thumb**: Data that is already an array in memory is usually fine to process as an array; data that *could* arrive as a stream is best kept as a stream from the start.

## Operations That Buffer

Even on a streaming pipeline, a few operations must hold elements back to produce correct results. They remain memory-bounded, but the buffer size is worth knowing about:

- **`slice()` with a negative offset** buffers up to `|offset|` trailing elements.
- **`slice()` with a negative length** buffers `|length|` elements in a rolling window.
- **`chunk($n)`** holds up to `$n` elements—one chunk—at a time.
- **`reservoir($n)`** holds the sample of `$n` elements.
- **`last()`, `count()`, `finalVariance()`** consume the whole stream but store almost nothing.

## Memory Management

- **Process in chunks**: Use `chunk()` to hand large datasets to databases and APIs in manageable batches.
- **Count for free**: Prefer `runningCount()` over a separate `count()` pass; the latter is a terminal operation that consumes the pipeline.
- **Release resources**: When a generator holds a file handle or a database cursor, release it in a `finally` block inside the generator.

## Profiling

Before optimizing, always profile your code to identify the actual bottlenecks. Tools like Xdebug or Blackfire will give a clear picture of where pipeline time is really spent—more often than not, in the callbacks rather than the plumbing.
