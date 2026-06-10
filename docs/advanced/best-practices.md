# Best Practices

To make the most of the Pipeline library, follow these practices for writing clean, efficient, and maintainable code.

## Core Principles

### 1. Think in Streams

The library is designed for streaming data. Prefer iterators and generators over arrays for large datasets to minimize memory usage.

```php
// Good: Streaming from a file
$result = take(new SplFileObject('data.csv'))
    ->map(str_getcsv(...))
    ->toList();

// Good: Forcing a stream from a large array
$result = take($largeArray)
    ->stream()
    ->filter(fn($user) => $user['active'])
    ->toList();
```

When the pipeline holds an array, several methods (`filter()`, `cast()`, `slice()`, `chunk()`, and others) take eager fast paths that create intermediate arrays; `stream()` opts out of them. See [Performance](performance.md) for the details.

### 2. Chain Operations

Keep operations in a single, fluent chain; don't round-trip through arrays between stages.

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

Because pipelines are mutable and return the same instance, capturing intermediate variables is harmless—but converting to arrays between stages is not.

### 3. Prefer `fold()` for Aggregations

Use `fold()` instead of `reduce()` for aggregation. Its required initial value makes both the intent and the result type explicit.

```php
// Good: Explicit initial value
$sum = take($numbers)->fold(0);

// Less clear: where does the accumulator start?
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

Reserve plain `filter()` for when you really do want `array_filter()` semantics.

### 5. Prefer Explicit Operations

A pipeline reads best when each stage does one obvious thing: filter, then transform, then aggregate. Resist the urge to hide several concerns inside one clever callback—the next reader (human or LLM) should be able to follow the data without simulating your code in their head.

## Error Handling

The library never throws exceptions of its own, so error handling is about your data and your callbacks. Write defensive callbacks for malformed input:

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

For collecting or logging the rejected items, see [`select()` with `onReject`](../api/filtering.md#select).

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

- **Reusing a consumed pipeline**: Streaming pipelines, like generators, can only be iterated once; a second pass throws "Cannot traverse an already closed generator". Create a new pipeline for each use, or use [`cursor()`](../api/collection.md#cursor) when you need to pause and resume.
- **`iterator_to_array()` on a pipeline**: With duplicate keys it silently drops values. Use `toList()` or `toAssoc()`.
- **Modifying the source data during iteration**: This leads to undefined behavior. Produce new values instead.
- **Overusing pipelines for trivial tasks**: For a small array that needs one `array_sum()`, the native function is simpler and faster. The pipeline pays off as soon as operations compose or data streams.
