# Utility Methods

Utility methods provide specialized functionality for tasks like sampling and monitoring pipelines.

## `reservoir()`

Performs reservoir sampling to select a random subset of elements from a pipeline. This is a terminal operation and is highly memory-efficient, as it does not require loading the entire dataset into memory.

**Signature**: `reservoir(int $size, ?callable $weightFunc = null): array`

-   `$size`: The number of elements to sample.
-   `$weightFunc`: An optional function to calculate the weight of each element for weighted sampling.

**Examples**:

```php
// Get 10 random lines from a large file
$sample = take(new SplFileObject('large.log'))
    ->reservoir(10);

// Weighted sampling based on priority
$sample = take($items)
    ->reservoir(5, fn($item) => $item['priority']);
```

## `runningCount()`

Counts elements as they pass through the pipeline without consuming it. This is a non-terminal operation, useful for monitoring.

**Signature**: `runningCount(?int &$count): self`

-   `&$count`: A reference to a counter variable that will be updated as elements pass through.

**Example**:

```php
$count = 0;
$result = take(range(1, 100))
    ->filter(fn($x) => $x % 2 === 0)
    ->runningCount($count)
    ->toList();

echo "Count: $count"; // 50
```

## `stream()`

Converts an array-based pipeline into a generator-based one. This forces all subsequent operations to be lazy and process elements one by one, which is crucial for memory efficiency with large arrays. This is a non-terminal operation.

**Signature**: `stream(): self`

**Example**:

```php
// Process a large array with low memory usage
$result = take($largeArray)
    ->stream()
    ->map(fn($x) => expensive_operation($x))
    ->toList();
```
