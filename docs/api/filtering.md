# Filtering Methods

Filtering methods are used to remove elements from a pipeline based on a condition.

## `filter()`

Filters the pipeline using a callback function. If no callback is provided, it removes all "falsy" values.

**Signature**: `filter(?callable $func = null, bool $strict = false): self`

-   `$func`: A function that returns `true` to keep an element or `false` to remove it.
-   `$strict`: If `true` and `$func` is `null`, only `null` and `false` are removed.

**Behavior**:

-   Preserves the original keys of the elements.
-   For arrays, it uses the highly optimized `array_filter()` function.

**Examples**:

```php
// Keep only even numbers
$result = take([1, 2, 3, 4, 5, 6])
    ->filter(fn($x) => $x % 2 === 0)
    ->toList(); // [2, 4, 6]

// Remove falsy values
$result = take([0, 1, false, 2, null, 3, '', 4])
    ->filter()
    ->toList(); // [1, 2, 3, 4]

// Strict filtering
$result = take([0, 1, false, 2, null, 3, '', 4])
    ->filter(strict: true)
    ->toList(); // [0, 1, 2, 3, '', 4]
```

## `skipWhile()`

Skips elements from the beginning of the pipeline as long as a condition is true.

**Signature**: `skipWhile(callable $predicate): self`

-   `$predicate`: A function that returns `true` to continue skipping.

**Behavior**:

-   Once the predicate returns `false`, no more elements are skipped.

**Examples**:

```php
// Skip leading zeros
$result = take([0, 0, 1, 0, 2, 0, 3])
    ->skipWhile(fn($x) => $x === 0)
    ->toList(); // [1, 0, 2, 0, 3]

// Skip lines in a file until a marker is found
$result = take(new SplFileObject('data.txt'))
    ->skipWhile(fn($line) => !str_contains($line, 'START_DATA'))
    ->toList();
```