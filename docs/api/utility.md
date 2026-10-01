# Utility Methods

Utility methods cover side effects, sampling, combining data, monitoring, and reshaping keys and values.

## `tap()`

Performs side effects on each element and does not change the values in the pipeline. Use it for debugging, logging, or progress reporting.

**Signature**: `tap(callable $func): self`

- `$func`: A callback receiving `($value, $key)`; its return value is ignored.

**Behavior**:

- Non-terminal: the next stage receives the values unchanged. The pipeline calls the callback for each element only upon iteration.

**Examples**:

```php
$result = take($orders)
    ->tap(fn($order, $id) => $logger->info("Processing order $id"))
    ->map(fn($order) => $order->total())
    ->toList();
```

## `stream()`

Converts the pipeline to a generator-backed stream. All subsequent operations are evaluated lazily and process a single element at a time.

**Signature**: `stream(): self`

**Behavior**:

- Non-terminal. After `stream()`, the array fast paths do not apply, and the pipeline creates no intermediate arrays.
- Use it to limit memory usage when a large array enters a pipeline. On a generator-backed pipeline, `stream()` has no effect.

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

Counts elements during iteration and does not consume the pipeline.

**Signature**: `runningCount(?int &$count): self`

- `&$count`: A reference to a counter; initialized to `0` unless already set.

**Behavior**:

- Non-terminal: the counter increments lazily for each element. Its value is final only after the pipeline is consumed.

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

Performs [reservoir sampling](https://en.wikipedia.org/wiki/Reservoir_sampling): selects a fixed-size uniform random sample from a stream of unknown length. Only the sample stays in memory.

**Signature**: `reservoir(int $size, ?callable $weightFunc = null): array`

- `$size`: The number of elements to sample.
- `$weightFunc`: An optional callback returning a weight for each element, for weighted sampling.

**Behavior**:

- This is a terminal operation returning an array.
- Uniform sampling uses Algorithm R. Weighted sampling uses Algorithm A-Chao.

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
- Use [`unpack()`](transformation.md#unpack) after `zip()` to pass each tuple to a callback as separate arguments.

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

Keeps only the values and discards the keys. This is the streaming counterpart of `array_values()`.

**Signature**: `values(): self`

**Examples**:

```php
$result = take(['a' => 1, 'b' => 2])->values()->toList(); // [1, 2]
```

### `keys()`

Keeps only the keys and outputs them as the new values. This is the streaming counterpart of `array_keys()`.

**Signature**: `keys(): self`

**Examples**:

```php
$result = take(['a' => 1, 'b' => 2])->keys()->toList(); // ['a', 'b']
```

### `flip()`

Swaps keys and values. This is the streaming counterpart of `array_flip()`.

**Signature**: `flip(): self`

**Examples**:

```php
$result = take(['a' => 1, 'b' => 2])->flip()->toAssoc(); // [1 => 'a', 2 => 'b']
```

On a streaming pipeline, values become keys without deduplication. Use `toList()` to keep every item, or `toAssoc()` to keep only the last value for each repeated key.

### `tuples()`

Converts the stream into `[key, value]` pairs. Ordinary callbacks then receive the keys as part of each value.

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

## Enum Helpers

PHP has no property reference syntax, so a callback that reads an enum property needs a full closure: `fn(Suit $suit) => $suit->value`. `Pipeline\Helper\Enums` provides these callbacks as static methods. Use them as first-class callables with [`cast()`](transformation.md#cast) or any other method that accepts a callback.

The examples use this enum:

```php
enum Suit: string
{
    case Hearts = 'H';
    case Spades = 'S';
}
```

### `Enums::value()`

Returns the backing value of an enum case.

**Signature**: `Enums::value(BackedEnum $case): int|string`

**Behavior**:

- Accepts only backed enums. PHP raises a `TypeError` for a pure enum case or any other value.
- Static analyzers infer the backing type: a string-backed enum yields `string` values.

**Examples**:

```php
use Pipeline\Helper\Enums;

$result = take(Suit::cases())
    ->cast(Enums::value(...))
    ->toList(); // ['H', 'S']

$csv = take(Suit::cases())
    ->cast(Enums::value(...))
    ->collect(fn(array $values) => implode(',', $values)); // 'H,S'
```

### `Enums::name()`

Returns the name of an enum case.

**Signature**: `Enums::name(UnitEnum $case): string`

**Behavior**:

- Accepts pure and backed enums.

**Examples**:

```php
use Pipeline\Helper\Enums;

$result = take(Suit::cases())
    ->cast(Enums::name(...))
    ->toList(); // ['Hearts', 'Spades']
```
