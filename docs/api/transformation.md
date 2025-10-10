# Transformation Methods

Transformation methods modify the elements within a pipeline.

## `map()`

Transforms each element with a callback. The callback can return a single value or a `Generator` to produce multiple values.

**Signature**: `map(?callable $func = null): self`

-   If the callback returns a `Generator`, `map()` expands it, yielding each of its values into the main pipeline.

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
```

## `cast()`

Performs a simple 1-to-1 transformation on each element. Unlike `map()`, it does not expand generators.

**Signature**: `cast(?callable $func = null): self`

-   `cast()` is faster than `map()` for simple transformations on arrays because it uses `array_map()` internally. For large datasets, consider using `->stream()` first to ensure lazy processing.

**Examples**:

```php
// Type casting
$result = take(['1', '2', '3'])
    ->cast(intval(...))
    ->toList(); // [1, 2, 3]

// cast() treats a returned Generator as a single value
$result = take(['a', 'b'])
    ->cast(function ($char) {
        return (function () use ($char) { yield $char; })();
    })
    ->toList(); // [Generator, Generator]
```

## `flatten()`

Flattens a nested pipeline by one level.

**Signature**: `flatten(): self`

**Example**:

```php
$result = take([[1, 2], [3, 4]])
    ->flatten()
    ->toList(); // [1, 2, 3, 4]
```

## `unpack()`

Unpacks array elements from the pipeline as arguments to a callback. If no callback is provided, it behaves like `flatten()`.

**Signature**: `unpack(?callable $func = null): self`

**Example**:

```php
$result = take([[1, 2], [3, 4]])
    ->unpack(fn($a, $b) => $a + $b)
    ->toList(); // [3, 7]
```

## `chunk()`

Splits the pipeline into chunks of a fixed size.

**Signature**: `chunk(int $length, bool $preserve_keys = false): self`

**Example**:

```php
$result = take([1, 2, 3, 4, 5])
    ->chunk(2)
    ->toList(); // [[1, 2], [3, 4], [5]]
```

## `chunkBy()`

Splits the pipeline into chunks of variable sizes.

**Signature**: `chunkBy(iterable|callable $func, bool $preserve_keys = false): self`

**Example**:

```php
$result = take([1, 2, 3, 4, 5, 6, 7])
    ->chunkBy([2, 3, 1])
    ->toList(); // [[1, 2], [3, 4, 5], [6]]
```

## `slice()`

Extracts a portion of the pipeline.

**Signature**: `slice(int $offset, ?int $length = null): self`

**Example**:

```php
$result = take([1, 2, 3, 4, 5])
    ->slice(1, 3)
    ->toList(); // [2, 3, 4]
```
