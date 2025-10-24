# Best Practices

Follow these best practices for writing clean, efficient, and maintainable pipelines.

## 1. Think in Streams

Prefer iterators and generators over arrays for large datasets to minimize memory usage.

```php
// Good: Streaming from a file
$result = take(new SplFileObject('data.csv'))
    ->map('str_getcsv')
    ->toList();
```

#### Array Processing Warning

When starting a pipeline with a large array, some methods (`filter()`, `cast()`, `slice()`) use PHP's native array functions for performance, which create intermediate arrays in memory. To avoid this, use `stream()` to force lazy, one-by-one processing.

```php
// Bad: Creates intermediate arrays, high memory usage
$result = take($largeArray)
    ->filter(...)
    ->map(...);

// Good: Processes one element at a time
$result = take($largeArray)
    ->stream()
    ->filter(...)
    ->map(...);
```

## 2. Keep a Single, Fluent Chain

Avoid breaking a pipeline into multiple chains. This creates unnecessary intermediate variables and is less efficient.

```php
// Good: A single, readable chain
$result = take($data)
    ->filter($predicate)
    ->map($transformer)
    ->toList();

// Bad: Creates an intermediate array
$filtered = take($data)->filter($predicate)->toList();
$result = take($filtered)->map($transformer)->toList();
```

## 3. Prefer `fold()` for Aggregations

For clarity and type safety, use `fold()` instead of `reduce()`. It requires an explicit initial value, which makes your code more predictable and prevents errors with empty pipelines.

```php
// Good: Explicit initial value
$sum = take($numbers)->fold(0, fn($a, $b) => $a + $b);

// Avoid: Implicit initial value can be ambiguous
$sum = take($numbers)->reduce();
```

## 4. Use Strict Filtering for Data Cleaning

Use `filter(strict: true)` to remove only `null` and `false`. This prevents accidentally removing falsy values like `0` or `''`.

```php
// Good: Predictable cleaning
$cleaned = take($data)->filter(strict: true);

// Avoid: May remove valid data
$cleaned = take($data)->filter();
```

## 5. Handle Errors Gracefully

Write defensive code inside your pipeline steps to handle potential errors.

```php
// Handle missing keys with the null coalescing operator
$result = take($users)
    ->map(fn($user) => [
        'id' => $user['id'] ?? null,
        'name' => $user['name'] ?? 'Unknown',
    ])
    ->filter(fn($user) => $user['id'] !== null)
    ->toList();
```

## Antipatterns to Avoid

-   **Reusing a consumed pipeline**: Generators can only be iterated over once. Create a new pipeline if you need to process the same data again.
-   **Modifying source data during iteration**: This can lead to unpredictable behavior.
-   **Overusing pipelines**: For simple tasks like `array_sum`, native PHP functions are often clearer and more efficient.