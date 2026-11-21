# Filtering Methods

Filtering methods remove elements from a pipeline that fail a condition. These methods preserve keys. To reindex the keys mid-pipeline, call [`values()`](utility.md#values) next.

## `select()`

This method keeps elements for which the callback returns `true`. Without a callback, it removes only `null` and `false` values. This default keeps valid falsy data such as `0` and empty strings.

**Signature**: `select(?callable $func = null, bool $strict = true, ?callable $onReject = null): self`

- `$func`: A callback returning `true` to keep an element.
- `$strict`: When `true` (the default), only `null` and `false` test results discard an element.
- `$onReject`: An optional callback invoked with `($value, $key)` for each rejected element. Use it to log or collect rejected elements.

**Behavior**:

- With a callback and the default `strict: true`, `select()` keeps an element unless the callback returns `null` or `false`. Any other return value, including a falsy one, keeps the element.
- With `strict: false`, `select()` evaluates the callback's return value for truthiness, the same as `array_filter()`.
- On array-backed pipelines without `$onReject`, `select()` calls `array_filter()` eagerly. With `$onReject`, `select()` always uses a lazy generator.

**Examples**:

```php
// Safe data cleaning: keeps 0 and ''
$result = take([0, 1, false, 2, null, 3, '', 4])
    ->select()
    ->toList(); // [0, 1, 2, 3, '', 4]

// With a predicate
$result = take($orders)
    ->select(fn($order) => $order->isPaid())
    ->toList();

// Observing rejected elements
$result = take($records)
    ->select(
        fn($record) => $record->isValid(),
        onReject: fn($record, $key) => $logger->warning("Invalid record at $key"),
    )
    ->toList();
```

## `filter()`

This method is an alias for `select()` with `strict: false` as the default. Without a callback, it removes all falsy values, the same as `array_filter()`. With a callback, it evaluates the return value for truthiness.

**Signature**: `filter(?callable $func = null, bool $strict = false): self`

- `$func`: A callback returning a truthy value to keep an element.
- `$strict`: When `true`, the method behaves like `select()`.

**Examples**:

```php
// Keep only even numbers
$result = take([1, 2, 3, 4, 5, 6])
    ->filter(fn($x) => $x % 2 === 0)
    ->toList(); // [2, 4, 6]

// Remove all falsy values, including 0 and ''
$result = take([0, 1, false, 2, null, 3, '', 4])
    ->filter()
    ->toList(); // [1, 2, 3, 4]

// Using built-in type checking functions
$result = take([1, '2', 3.0, 'four'])
    ->filter(is_int(...))
    ->toList(); // [1]
```

### Choosing Between `select()` and `filter()`

Both names call the same method with different defaults. Use the name with the default that matches your intent:

| Goal | Call |
| --- | --- |
| Drop only `null` and `false`, keep `0` and `''` | `select()` |
| Drop every falsy value, like `array_filter()` | `filter()` |
| Keep elements for which your callback returns `true` | Either, with a callback returning `bool` |
| Side effects for rejected elements | `select()` with `onReject:` |

## `skipWhile()`

This method skips elements from the start of the pipeline while the predicate returns `true`. After the predicate returns `false` for the first time, the method keeps all remaining elements and does not call the predicate again.

**Signature**: `skipWhile(callable $predicate): self`

- `$predicate`: A callback returning `true` to continue skipping.

**Examples**:

```php
// Skip leading zeros only; later zeros stay
$result = take([0, 0, 1, 0, 2, 0, 3])
    ->skipWhile(fn($x) => $x === 0)
    ->toList(); // [1, 0, 2, 0, 3]

// Skip lines in a file until a marker is found
$result = take(new SplFileObject('data.txt'))
    ->skipWhile(fn($line) => !str_contains($line, 'START_DATA'))
    ->toList();
```

## Filtering with `map()`

A `map()` callback that yields conditionally filters and transforms elements in a single step. See [`map()`](transformation.md#map).

```php
$result = take([1, 2, 3, 4])
    ->map(function ($x) {
        if ($x % 2 === 0) {
            yield $x * 10;
        }
    })
    ->toList(); // [20, 40]
```
