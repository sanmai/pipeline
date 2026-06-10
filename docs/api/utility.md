# Utility Methods

Utility methods cover side effects, sampling, combining data, monitoring, and reshaping keys and values.

## `tap()`

Performs side effects on each element without changing the values in the pipeline. Useful for debugging, logging, or progress reporting.

**Signature**: `tap(callable $func): self`

- `$func`: A callback receiving `($value, $key)`; its return value is ignored.

**Behavior**:

- Non-terminal: values continue unchanged to the next stage, and the callback runs lazily as elements flow through.

**Examples**:

```php
$result = take($orders)
    ->tap(fn($order, $id) => $logger->info("Processing order $id"))
    ->map(fn($order) => $order->total())
    ->toList();
```

## `stream()`

Converts the pipeline to a generator-backed stream, ensuring all subsequent operations are lazy and process elements one by one.

**Signature**: `stream(): self`

**Behavior**:

- Non-terminal. After `stream()`, array fast paths no longer apply: no intermediate arrays are created.
- Essential for memory efficiency when a large array enters a pipeline; a no-op for already-streaming pipelines.

**Examples**:

```php
// Process a large array with flat memory usage
$result = take($largeArray)
    ->stream()
    ->filter(fn($x) => $x->isRelevant())
    ->map(fn($x) => expensiveTransform($x))
    ->toList();
```

## `runningCount()`

Counts elements as they pass through, without consuming the pipeline.

**Signature**: `runningCount(?int &$count): self`

- `&$count`: A reference to a counter; initialized to `0` unless already set.

**Behavior**:

- Non-terminal: counting happens lazily as elements flow through, so the counter is only final after the pipeline has been consumed.

**Examples**:

```php
$processed = 0;
$result = take(range(1, 100))
    ->filter(fn($x) => $x % 2 === 0)
    ->runningCount($processed)
    ->map(fn($x) => $x ** 2)
    ->toList();

echo $processed; // 50
```

## `reservoir()`

Performs [reservoir sampling](https://en.wikipedia.org/wiki/Reservoir_sampling): selects a fixed-size uniform random sample from a stream of unknown length, holding only the sample in memory.

**Signature**: `reservoir(int $size, ?callable $weightFunc = null): array`

- `$size`: The number of elements to sample.
- `$weightFunc`: An optional callback returning a weight for each element, for weighted sampling.

**Behavior**:

- This is a terminal operation returning an array.
- Uses Algorithm R for uniform sampling and Algorithm A-Chao for weighted sampling.

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

Transposes the pipeline with one or more other iterables: each element becomes an array of corresponding elements, like `array_map(null, ...$arrays)` or Python's `zip()`.

**Signature**: `zip(iterable ...$inputs): self`

- `...$inputs`: The iterables to combine with the current sequence.

**Behavior**:

- Shorter inputs are padded with `null`.
- Pairs naturally with [`unpack()`](transformation.md#unpack) to spread each tuple into callback arguments.

**Examples**:

```php
$result = take(['a', 'b'])
    ->zip([1, 2], [true, false])
    ->toList();
// [['a', 1, true], ['b', 2, false]]

take($names)
    ->zip($ages)
    ->unpack(fn($name, $age) => "$name is $age")
    ->toList();
```

## Keys and Values

### `values()`

Keeps only the values, discarding keys—the streaming counterpart of `array_values()`.

**Signature**: `values(): self`

**Examples**:

```php
$result = take(['a' => 1, 'b' => 2])->values()->toAssoc(); // [1, 2]
```

### `keys()`

Keeps only the keys, making them the new values—the streaming counterpart of `array_keys()`.

**Signature**: `keys(): self`

**Examples**:

```php
$result = take(['a' => 1, 'b' => 2])->keys()->toList(); // ['a', 'b']
```

### `flip()`

Swaps keys and values—the streaming counterpart of `array_flip()`.

**Signature**: `flip(): self`

**Examples**:

```php
$result = take(['a' => 1, 'b' => 2])->flip()->toAssoc(); // [1 => 'a', 2 => 'b']
```

On a streaming pipeline values become keys as-is, with no deduplication; collect with `toList()` or `toAssoc()` depending on whether repeated keys matter.

### `tuples()`

Converts the stream into `[key, value]` pairs, making keys accessible to ordinary callbacks.

**Signature**: `tuples(): self`

**Examples**:

```php
$result = take(['a' => 1, 'b' => 2])->tuples()->toList();
// [['a', 1], ['b', 2]]

// Filter by key, then rebuild the array
$result = take($config)
    ->tuples()
    ->filter(fn($tuple) => !str_starts_with($tuple[0], 'secret_'))
    ->unpack(fn($key, $value) => yield $key => $value)
    ->toAssoc();
```

See the [Associative Arrays cookbook](../cookbook/associative-arrays.md) for the full key-manipulation pattern.
