# Performance Optimization

This guide provides techniques for optimizing your pipelines for speed and memory efficiency.

## The Power of Streaming

The library's core strength is its ability to process large datasets with minimal memory usage through streaming. This is achieved by using iterators and generators, which process data one element at a time.

**Example: Finding Errors in a Large Log File**

Consider the task of finding the first five "ERROR" lines in a 10 GB log file.

**The Inefficient Way (Loading into Memory)**

```php
// Warning: This will likely exhaust your server's memory.
$lines = file('huge-10GB.log'); // Loads the entire 10GB file into memory
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

The streaming approach is significantly faster and more memory-efficient because it reads the file line by line and stops as soon as the required number of errors has been found.

## The `stream()` Method

If you must start with an array, you can use the `stream()` method to convert it to a lazy, generator-based pipeline. This is crucial for memory safety when working with large arrays.

```php
// Force a large array to be processed as a stream
$result = take($largeArray)
    ->stream()
    ->map(fn($item) => process_item($item))
    ->toList();
```

## Array Optimizations

For convenience, some methods are optimized for speed when working with small arrays:

-   `filter()`
-   `cast()`
-   `slice()`
-   `chunk()`

These methods use native PHP array functions internally, which can be faster for small datasets. However, they create intermediate arrays in memory, so they should be used with caution.

## Memory Management

-   **Process in chunks**: Use the `chunk()` method to process large datasets in smaller, more manageable batches.
-   **Release resources**: When using generators to interact with resources like files or database connections, be sure to release them in a `finally` block.

## Profiling

Before optimizing, always profile your code to identify the actual bottlenecks. Use tools like Xdebug or Blackfire to get a clear picture of your pipeline's performance.
