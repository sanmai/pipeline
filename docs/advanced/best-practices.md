# Best Practices

Follow these practices to keep pipeline code clear and memory-efficient.

## Core Principles

### 1. Think in Streams

The library is designed for streaming data. Prefer iterators and generators over arrays for large datasets to minimize memory usage.

```php
// Good: Streaming from a file
$result = take(new SplFileObject('data.csv'))
    ->map(str_getcsv(?, escape: ''))
    ->toList();

// Good: Forcing a stream from a large array
$result = take($largeArray)
    ->stream()
    ->filter(fn($user) => $user['active'])
    ->toList();
```

When the pipeline stores an array, several methods (`filter()`, `cast()`, `slice()`, `chunk()`, and others) use eager fast paths that create intermediate arrays. `stream()` disables these paths. See [Performance](performance.md) for the details.

### 2. Chain Operations

Keep operations in a single chain. Do not convert the data to arrays between stages.

```php
// Good: A single, readable chain
$result = take($data)
    ->filter($predicate)
    ->map($transformer)
    ->toList();

// Bad: Each toList() materializes an array and loses laziness
$filtered = take($data)->filter($predicate)->toList();
$result = take($filtered)->map($transformer)->toList();
```

Pipelines are mutable and return the same instance, so intermediate variables are harmless. Conversion to arrays between stages is not harmless.

### 3. Prefer `fold()` for Aggregations

Use `fold()` instead of `reduce()` for aggregation. Its required initial value makes both the intent and the result type explicit.

```php
// Good: Explicit initial value
$sum = take($numbers)->fold(0);

// Less clear: the initial value is implicit
$sum = take($numbers)->reduce();
```

### 4. Choose the Filter That Says What You Mean

When cleaning data, use `select()` to remove only `null` and `false`. This prevents accidental removal of valid falsy values like `0` or empty strings.

```php
// Good: Predictable cleaning, keeps 0 and ''
$cleaned = take($data)->select();

// Risky: drops 0, '', and '0' as well
$cleaned = take($data)->filter();
```

Use `filter()` only when you require `array_filter()` semantics.

### 5. Prefer Explicit Operations

Give each stage a single, obvious operation: filter, then transform, then aggregate. Do not combine several concerns in a single callback. The next reader (human or LLM) must be able to follow the data flow without simulating the code.

## Error Handling

The library never throws exceptions of its own. Error handling therefore applies only to your data and your callbacks. Write defensive callbacks for malformed input:

```php
// Handle missing keys with the null coalescing operator
$result = take($users)
    ->map(fn($user) => [
        'id' => $user['id'] ?? null,
        'name' => $user['name'] ?? 'Unknown',
    ])
    ->select(fn($user) => $user['id'] !== null)
    ->toList();
```

To collect or log rejected items, see [`select()` with `onReject`](../api/filtering.md#select).

## Code Organization

For complex pipelines, encapsulate logic in reusable functions or classes.

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

For testable multi-stage workflows, see the [Pipeline-Helper Pattern](../cookbook/testable-pipelines.md).

## Antipatterns to Avoid

- **Reusing a consumed pipeline**: Streaming pipelines, like generators, allow a single iteration. A second pass throws "Cannot traverse an already closed generator". Create a new pipeline for each use, or use [`cursor()`](../api/collection.md#cursor) to pause and resume iteration.
- **`iterator_to_array()` on a pipeline**: It silently drops values with duplicate keys. Use `toList()` or `toAssoc()`.
- **Modifying the source data during iteration**: This causes undefined behavior. Produce new values instead.
- **Overusing pipelines for trivial tasks**: For a small array that requires a single `array_sum()` call, the native function is simpler and faster. A pipeline becomes useful when several operations compose or when the data streams.
