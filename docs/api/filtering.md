# Filtering Methods

Filtering methods remove elements from a pipeline based on a condition.

## `filter()`

Filters the pipeline with a callback. If no callback is provided, it removes all "falsy" values.

**Signature**: `filter(?callable $func = null, bool $strict = false): self`

-   `$func`: A function that returns `true` to keep an element, `false` to remove it.
-   `$strict`: If `true` and no `$func` is given, only `null` and `false` are removed.
-   For arrays, `filter()` uses the optimized `array_filter()` internally. For large datasets, use `->stream()` first to ensure lazy processing.

**Examples**:

```php
// Keep only even numbers
$result = take([1, 2, 3, 4, 5, 6])
    ->filter(fn($x) => $x % 2 === 0)
    ->toList(); // [2, 4, 6]

// Remove falsy values (0, false, null, '')
$result = take([0, 1, false, 2, null, 3, '', 4])
    ->filter()
    ->toList(); // [1, 2, 3, 4]

// Strict filtering (only removes null and false)
$result = take([0, 1, false, 2, null, 3, '', 4])
    ->filter(strict: true)
    ->toList(); // [0, 1, 2, 3, '', 4]
```

## `skipWhile()`

Skips elements from the beginning of the pipeline while a condition is `true`. After the condition becomes `false` once, no more elements are skipped.

**Signature**: `skipWhile(callable $predicate): self`

**Example**:

```php
// Skip leading zeros
$result = take([0, 0, 1, 0, 2, 0, 3])
    ->skipWhile(fn($x) => $x === 0)
    ->toList(); // [1, 0, 2, 0, 3]
```