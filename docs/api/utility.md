# Utility Methods

Utility methods provide specialized functionality for tasks like sampling, combining data, and monitoring pipelines.

## `reservoir()`

Performs reservoir sampling to select a random subset of elements from a pipeline. This is highly memory-efficient, as it does not require loading the entire dataset into memory.

**Signature**: `reservoir(int $size, ?callable $weightFunc = null): array`

-   `$size`: The number of elements to sample.
-   `$weightFunc`: An optional function to calculate the weight of each element for weighted sampling.

**Behavior**:

-   This is a terminal operation.
-   It uses Algorithm R for uniform sampling and Algorithm A-Chao for weighted sampling.

**Examples**:

```php
// Get 10 random lines from a large file
$sample = take(new SplFileObject('large.log'))
    ->reservoir(10);

// Weighted sampling
$sample = take($items)
    ->reservoir(5, fn($item) => $item['priority']);
```

## `zip()`

Combines multiple iterables into a single pipeline of tuples.

**Signature**: `zip(iterable ...$inputs): self`

-   `...$inputs`: The iterables to combine.

**Behavior**:

-   Creates a new pipeline where each element is an array of corresponding elements from the input iterables.
-   Shorter iterables are padded with `null`.

**Examples**:

```php
$result = take(['a', 'b'])
    ->zip([1, 2], [true, false])
    ->toList();
// [['a', 1, true], ['b', 2, false]]
```

## `runningCount()`

Counts elements as they pass through the pipeline without consuming it.

**Signature**: `runningCount(?int &$count): self`

-   `&$count`: A reference to a counter variable, which will be updated.

**Behavior**:

-   This is a non-terminal operation.
-   It is useful for monitoring the number of elements that have been processed at a certain point in the pipeline.

**Examples**:

```php
$count = 0;
$result = take(range(1, 100))
    ->filter(fn($x) => $x % 2 === 0)
    ->runningCount($count)
    ->toList();

echo "Count: $count"; // 50
```

## `stream()`

Converts an array-based pipeline into a generator-based one, forcing all subsequent operations to be lazy and process elements one by one.

**Signature**: `stream(): self`

**Behavior**:

-   This is a non-terminal operation.
-   It is crucial for memory efficiency when working with large arrays.

**Examples**:

```php
// Process a large array with low memory usage
$result = take($largeArray)
    ->stream()
    ->map(fn($x) => expensive_operation($x))
    ->toList();
```
