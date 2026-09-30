# Performance Optimization

This guide describes how to make pipelines faster and how to reduce their memory usage.

## The Power of Streaming

A pipeline processes large datasets with low memory usage because it streams them. The consumer pulls elements through the pipeline one at a time. Processing stops when the consumer receives all the elements it requires.

**Example: Finding Errors in a Large Log File**

This example finds the first five "ERROR" lines in a 10 GB log file.

**The Inefficient Way (Loading into Memory)**

```php
// Warning: This can exhaust the memory of your server.
$lines = file('huge-10GB.log'); // Loads the entire 10 GB file into memory
$errors = take($lines)
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->slice(0, 5)
    ->toList();
```

**The Efficient Way (Streaming)**

```php
// Memory usage stays constant.
$errors = take(new SplFileObject('huge-10GB.log'))
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->slice(0, 5)
    ->toList();
```

The streaming version reads the file line by line. It stops reading when it finds the fifth error. If the errors occur near the start of the file, the pipeline reads only a small part of the file.

## Array Fast Paths vs `stream()`

When a pipeline contains a plain array, many methods use an eager fast path with native array functions:

- `filter()` and `select()` use `array_filter()`.
- `cast()` uses `array_map()`.
- `slice()` uses `array_slice()`.
- `chunk()` uses `array_chunk()`.
- `zip()` builds its tuples eagerly.
- `keys()`, `values()`, `flip()`, `tuples()`, `fold()`, `count()`, `min()`, and `max()` also use array paths.

`map()` is always lazy, regardless of the source.

These fast paths are faster for small and medium arrays. But each fast path creates a new intermediate array in memory:

```php
// Without stream(): intermediate arrays at each eager step
$result = take($largeArray)
    ->filter($predicate)  // New filtered array in memory
    ->cast($transformer)  // Another transformed array
    ->toList();
```

The `stream()` method converts the pipeline to a generator. After this call, each element passes through the full chain one at a time:

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

- Large arrays would cause memory pressure.
- Transformations are expensive and the consumer can stop before the last element.
- Memory usage must stay constant for all input sizes.

### Trade-offs

- **Memory**: `stream()` keeps peak memory flat. Fast paths allocate full arrays.
- **Speed**: Native array functions are faster for small and medium datasets.
- **Rule of thumb**: If the data is already an array in memory, process it as an array. If the data can arrive as a stream, keep it as a stream from the start.

## Operations That Buffer

Some operations must buffer elements to produce correct results, also on a streaming pipeline. The memory usage of each buffer has a limit:

- **`slice()` with a negative offset** buffers up to `|offset|` trailing elements.
- **`slice()` with a negative length** buffers `|length|` elements in a rolling window.
- **`chunk($n)`** stores up to `$n` elements (a single chunk) at a time.
- **`reservoir($n)`** stores the sample of `$n` elements.
- **`last()`, `count()`, `finalVariance()`** consume the full stream but store almost no data.

## Memory Management

- **Process in chunks**: Use `chunk()` to send large datasets to databases and APIs in batches.
- **Count while streaming**: Use `runningCount()` instead of a separate `count()` pass. `count()` is a terminal operation and consumes the pipeline.
- **Release resources**: If a generator opens a file handle or a database cursor, release it in a `finally` block of the generator.

## Profiling

Profile your code before you optimize it. Tools such as Xdebug or Blackfire show where the pipeline spends its time. Usually the callbacks use more time than the pipeline itself.
