# Transformation Methods

Transformation methods modify the elements within a pipeline. They are all non-terminal: each returns the same pipeline instance for further chaining.

## `map()`

Transforms each element using a callback. This is the most flexible transformation method: the callback can return a single value, or use `yield` to produce any number of values—including none, which makes `map()` a filter as well.

**Signature**: `map(?callable $func = null): self`

- `$func`: The transformation function. With no callback, `map()` does nothing.

**Callback Signature**: `function(mixed $value): mixed|Generator`

**Behavior**:

- If the callback returns a `Generator` (any callback using `yield`), `map()` expands it, feeding each of its values into the pipeline with the keys the generator provides.
- If the callback returns a plain value, the original key is kept.
- On a fresh, unprimed pipeline, `map()` accepts a callback with no arguments and uses it to seed the pipeline; see the [`map()` helper function](creation.md#map).
- `map()` is always lazy: even on an array-backed pipeline no callback runs until the pipeline is consumed.

**Examples**:

```php
// 1-to-1 transformation
$result = take([1, 2, 3])
    ->map(fn($x) => $x * 2)
    ->toList(); // [2, 4, 6]

// 1-to-many transformation with a generator
$result = take(['a', 'b'])
    ->map(function ($char) {
        yield $char;
        yield strtoupper($char);
    })
    ->toList(); // ['a', 'A', 'b', 'B']

// 1-to-maybe-none: filtering while transforming
$result = take([1, 2, 3, 4])
    ->map(function ($x) {
        if ($x % 2 === 0) {
            yield $x * 10;
        }
    })
    ->toList(); // [20, 40]
```

## `cast()`

Transforms each element using a callback, with a key difference from `map()`: it never expands generators. Whatever the callback returns—even a `Generator` object—becomes the element. This makes it the right tool for strict one-to-one transformations.

**Signature**: `cast(?callable $func = null): self`

- `$func`: The transformation function. With no callback, `cast()` does nothing.

**Behavior**:

- Original keys are always preserved.
- On an array-backed pipeline, `cast()` uses `array_map()` eagerly, creating a new array in memory. Call [`stream()`](utility.md#stream) first to process large arrays lazily.

**Examples**:

```php
// Type casting
$result = take(['1', '2', '3'])
    ->cast(intval(...))
    ->toList(); // [1, 2, 3]

// Wrapping values into objects
$result = take([1, 2, 3])
    ->cast(fn($n) => new Money($n, 'USD'))
    ->toList(); // [Money, Money, Money]
```

### Choosing Between `map()` and `cast()`

| You want to... | Use |
| --- | --- |
| Transform one element into exactly one element | `cast()` |
| Produce zero, one, or many elements per input | `map()` with `yield` |
| Keep `Generator` objects as values | `cast()` |
| Control the keys of produced elements | `map()` with `yield $key => $value` |

## `flatten()`

Flattens nested iterables by one level.

**Signature**: `flatten(): self`

**Examples**:

```php
$result = take([[1, 2], [3, 4]])
    ->flatten()
    ->toList(); // [1, 2, 3, 4]
```

## `unpack()`

Unpacks each array element into separate arguments for a callback—the pipeline counterpart of the spread operator.

**Signature**: `unpack(?callable $func = null): self`

- `$func`: The callback receiving the unpacked arguments. With no callback, `unpack()` behaves like `flatten()`.

**Examples**:

```php
$result = take([[1, 2], [3, 4]])
    ->unpack(fn($a, $b) => $a + $b)
    ->toList(); // [3, 7]

// Pairs naturally with zip()
$result = take([1, 2, 3])
    ->zip([10, 20, 30])
    ->unpack(fn($a, $b) => $a * $b)
    ->toList(); // [10, 40, 90]
```

Like `map()`, the callback may use `yield` to produce several values, and ordinary PHP type declarations on the arguments give you free validation.

## `chunk()`

Splits the pipeline into arrays of a specified length. The last chunk may contain fewer elements.

**Signature**: `chunk(int $length, bool $preserve_keys = false): self`

- `$length`: The size of each chunk.
- `$preserve_keys`: When `true`, keys are preserved inside chunks; by default chunks are reindexed numerically.

**Examples**:

```php
$result = take([1, 2, 3, 4, 5])
    ->chunk(2)
    ->toList(); // [[1, 2], [3, 4], [5]]

$result = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->chunk(2, preserve_keys: true)
    ->toList(); // [['a' => 1, 'b' => 2], ['c' => 3]]
```

## `chunkBy()`

Splits the pipeline into chunks of varying sizes, taken from an iterable of sizes. Chunking stops when either the sizes or the data run out.

**Signature**: `chunkBy(iterable|callable $func, bool $preserve_keys = false): self`

- `$func`: An iterable of chunk sizes, or a callable returning such an iterable (a generator function works well). A size of `0` produces an empty array.
- `$preserve_keys`: Same as in `chunk()`.

**Examples**:

```php
// Fixed list of sizes
$result = take(range(1, 10))
    ->chunkBy([2, 5, 5])
    ->toList(); // [[1, 2], [3, 4, 5, 6, 7], [8, 9, 10]]

// Sizes from a generator: a small first batch, then steady batches
$result = take(range(1, 10))
    ->chunkBy(function () {
        yield 2;
        while (true) {
            yield 4;
        }
    })
    ->toList(); // [[1, 2], [3, 4, 5, 6], [7, 8, 9, 10]]
```

## `slice()`

Extracts a portion of the pipeline, much like `array_slice()` with `$preserve_keys` set to `true`.

**Signature**: `slice(int $offset, ?int $length = null): self`

- `$offset`: The starting position. A negative offset starts that far from the end.
- `$length`: The number of elements to take. A negative length stops that many elements before the end. `null` means "to the end".

**Behavior**:

- Keys are intentionally preserved; follow with [`values()`](utility.md#values) if you need reindexing mid-pipeline.
- On array-backed pipelines, `slice()` delegates to `array_slice()`.
- On streaming pipelines, negative `$offset` or `$length` values require a rolling buffer of that many elements; see [Performance](../advanced/performance.md).

**Examples**:

```php
$result = take([1, 2, 3, 4, 5])
    ->slice(1, 3)
    ->toList(); // [2, 3, 4]

// Negative arguments: drop the first and last elements
$result = take([1, 2, 3, 4, 5])
    ->slice(1, -1)
    ->toList(); // [2, 3, 4]

// Take the last two elements
$result = take([1, 2, 3, 4, 5])
    ->slice(-2)
    ->toList(); // [4, 5]
```
