# Transformation Methods

Transformation methods are used to modify the elements within a pipeline.

## `map()`

Transforms each element using a callback function. This is the most flexible transformation method, as the callback can return a single value or a `Generator` to yield multiple values.

**Signature**: `map(?callable $func = null): self`

-   `$func`: The transformation function.

**Callback Signature**: `function(mixed $value): mixed|Generator`

**Behavior**:

-   If the callback returns a `Generator`, `map()` will expand it, yielding each of its values into the main pipeline.

> **Performance Note**
>
> When starting with an array, `map()` processes lazily but other methods in your chain might not. For large datasets, consider using `->stream()` first to ensure all operations process one element at a time, avoiding high memory usage.

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

Transforms each element using a callback, but with a key difference from `map()`: it does not expand generators. This makes it ideal for simple, 1-to-1 transformations, especially on arrays, where it is highly optimized.

**Signature**: `cast(?callable $func = null): self`

-   `$func`: The transformation function.

**Behavior**:

-   If the callback returns a `Generator`, `cast()` will treat it as a single value, not expand it.
-   It is faster than `map()` for simple transformations on arrays because it uses `array_map()` internally.

> **Performance Note**
>
> When working with arrays, `cast()` uses PHP's `array_map()` internally, creating a new array in memory. For large datasets, use `->stream()` first to process elements one at a time.

**Examples**:

```php
// Type casting
$result = take(['1', '2', '3'])
    ->cast(intval(...))
    ->toList(); // [1, 2, 3]

// Creating a pipeline of generators
$result = take(['a', 'b'])
    ->cast(function ($char) {
        return (function () use ($char) {
            yield $char;
        })();
    })
    ->toList(); // [Generator, Generator]
```

## `flatten()`

Flattens a nested pipeline by one level.

**Signature**: `flatten(): self`

**Examples**:

```php
$result = take([[1, 2], [3, 4]])
    ->flatten()
    ->toList(); // [1, 2, 3, 4]
```

## `unpack()`

Unpacks array elements as arguments to a callback function.

**Signature**: `unpack(?callable $func = null): self`

-   `$func`: The callback to receive the unpacked arguments.

**Behavior**:

-   If no callback is provided, it behaves like `flatten()`.

**Examples**:

```php
$result = take([[1, 2], [3, 4]])
    ->unpack(fn($a, $b) => $a + $b)
    ->toList(); // [3, 7]
```

## `chunk()`

Splits the pipeline into chunks of a specified size.

**Signature**: `chunk(int $length, bool $preserve_keys = false): self`

-   `$length`: The size of each chunk.
-   `$preserve_keys`: Whether to preserve the original keys.

**Examples**:

```php
$result = take([1, 2, 3, 4, 5])
    ->chunk(2)
    ->toList(); // [[1, 2], [3, 4], [5]]
```

## `slice()`

Extracts a portion of the pipeline.

**Signature**: `slice(int $offset, ?int $length = null): self`

-   `$offset`: The starting position.
-   `$length`: The number of elements to extract.

**Examples**:

```php
$result = take([1, 2, 3, 4, 5])
    ->slice(1, 3)
    ->toList(); // [2, 3, 4]
```
