# Performance Optimization

This guide provides techniques for optimizing your pipelines for speed and memory efficiency.

## Streaming for Memory Efficiency

The library's core strength is processing large datasets with minimal memory usage through streaming. This is achieved by using iterators and generators, which process data one element at a time.

**Example: Finding Errors in a Large Log File**

To find the first five "ERROR" lines in a 10 GB log file:

**Inefficient (High Memory Usage):**
```php
// Warning: This will likely exhaust memory.
$lines = file('huge-10GB.log'); // Loads the entire 10GB file into memory
$errors = take($lines)
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->slice(0, 5)
    ->toList();
```

**Efficient (Streaming):**
```php
// Memory-safe and fast.
$errors = take(new SplFileObject('huge-10GB.log'))
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->slice(0, 5)
    ->toList();
```
The streaming approach is far more efficient because it reads the file line by line and stops as soon as the five errors are found.

## Forcing Lazy Processing with `stream()`

The `stream()` method converts an array-based pipeline into a generator-based one. This forces all subsequent operations to be lazy and process elements one by one.

By default, some operations on arrays are optimized for speed and create intermediate arrays in memory. `stream()` prevents this.

```php
// Without stream(): Creates intermediate arrays, high memory usage
$result = take($largeArray)
    ->filter($predicate)
    ->map($transformer)
    ->toList();

// With stream(): Processes one element at a time, low memory usage
$result = take($largeArray)
    ->stream()
    ->filter($predicate)
    ->map($transformer)
    ->toList();
```

### When to Use `stream()`

-   When working with large arrays that would cause high memory usage.
-   When your transformations are expensive and you might not need to process all elements.
-   When you need predictable, low memory usage regardless of input size.

## Other Considerations

-   **`chunk()`**: Use `chunk()` to process large datasets in smaller, manageable batches, which can be useful for operations like database inserts.
-   **Resource Management**: When using generators that interact with resources like files or database connections, be sure to release them (e.g., in a `finally` block).
-   **Profiling**: Before optimizing, always profile your code with tools like Xdebug or Blackfire to identify the actual bottlenecks. For simple operations, native PHP functions may be faster.
