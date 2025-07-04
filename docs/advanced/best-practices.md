# Best Practices

To make the most of the Pipeline library, follow these best practices for writing clean, efficient, and maintainable code.

## Core Principles

### 1. Think in Streams

The library is designed for streaming data. Always prefer iterators and generators over arrays for large datasets to minimize memory usage.

```php
// Good: Streaming from a file
$result = take(new SplFileObject('data.csv'))
    ->map('str_getcsv')
    ->toList();

// Good: Forcing a stream from a large array
$result = take($largeArray)
    ->stream()
    ->filter(fn($user) => $user['active'])
    ->toList();
```

#### Array Processing Warning

When working with arrays, be aware that certain methods (`filter()`, `cast()`, `slice()`, `chunk()`) use PHP's native array functions for performance. This means they create intermediate arrays:

```php
// This creates intermediate arrays in memory:
$result = take($millionRecords)
    ->filter(fn($r) => $r['active'])     // New array with ~500k elements
    ->map(fn($r) => transform($r))       // Another array with ~500k elements
    ->toList();

// Better: Use stream() for large arrays
$result = take($millionRecords)
    ->stream()                           // Process one element at a time
    ->filter(fn($r) => $r['active'])
    ->map(fn($r) => transform($r))
    ->toList();
```

### 2. Chain Operations

Keep your operations in a single, fluent chain for readability and efficiency.

```php
// Good: A single, readable chain
$result = take($data)
    ->filter($predicate)
    ->map($transformer)
    ->toList();

// Bad: Breaking the chain creates unnecessary intermediate variables
$filtered = take($data)->filter($predicate)->toList();
$result = take($filtered)->map($transformer)->toList();
```

### 3. Prefer `fold()` for Aggregations

For clarity and type safety, use `fold()` instead of `reduce()` for all aggregation tasks. It requires an explicit initial value, which makes your code more predictable.

```php
// Good: Explicit initial value
$sum = take($numbers)->fold(0, fn($a, $b) => $a + $b);

// Bad: Implicit initial value
$sum = take($numbers)->reduce();
```

### 4. Use Strict Filtering

When cleaning data, use `filter(strict: true)` to only remove `null` and `false` values. This prevents accidental removal of falsy values like `0` or empty strings.

```php
// Good: Predictable cleaning
$cleaned = take($data)->filter(strict: true);

// Bad: Aggressive cleaning may remove valid data
$cleaned = take($data)->filter();
```

## Error Handling

Write defensive code to handle potential errors gracefully.

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

## Code Organization

For complex pipelines, consider creating reusable functions or classes to encapsulate logic.

```php
// Reusable pipeline function
function getActiveUsers(iterable $users): Standard
{
    return take($users)
        ->filter(fn($user) => $user['active']);
}

$activeAdmins = getActiveUsers($allUsers)
    ->filter(fn($user) => $user['isAdmin'])
    ->toList();
```

## Antipatterns to Avoid

-   **Reusing a consumed pipeline**: Generators can only be iterated over once. Create a new pipeline for each use.
-   **Modifying the source data during iteration**: This can lead to unexpected behavior. Create new data structures instead.
-   **Overusing pipelines for simple tasks**: For simple operations like `array_sum`, native PHP functions are often more efficient.