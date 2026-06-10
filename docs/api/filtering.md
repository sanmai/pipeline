# Filtering Methods

Filtering methods remove elements from a pipeline based on a condition. Keys are always preserved; follow with [`values()`](utility.md#values) if you need reindexing mid-pipeline.

## `select()`

Selects elements for which the callback returns `true`. Without a callback, it removes only `null` and `false` values—a safe default that keeps legitimate falsy data such as `0` and empty strings.

**Signature**: `select(?callable $func = null, bool $strict = true, ?callable $onReject = null): self`

- `$func`: A callback returning `true` to keep an element.
- `$strict`: When `true` (the default), only `null` and `false` test results discard an element.
- `$onReject`: An optional callback invoked with `($value, $key)` for each rejected element—useful for logging or collecting rejects.

**Behavior**:

- With no callback, `select()` removes only `null` and `false` values.
- With a callback and the default `strict: true`, an element is kept unless the callback returns `null` or `false`; any other return value, even a falsy one, keeps the element.
- With `strict: false`, the callback's return value is evaluated for truthiness, exactly like `array_filter()` would.
- On array-backed pipelines, `select()` delegates to `array_filter()` eagerly.

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

An alias for `select()` with `strict: false` as the default: without a callback it removes all falsy values, exactly like `array_filter()`. With a callback, the return value is evaluated for truthiness.

**Signature**: `filter(?callable $func = null, bool $strict = false): self`

- `$func`: A callback returning a truthy value to keep an element.
- `$strict`: When `true`, behaves like `select()`.

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

The two are the same method with different defaults; pick the one whose default reads as the intent:

| Goal | Call |
| --- | --- |
| Drop only `null` and `false`, keep `0` and `''` | `select()` |
| Drop every falsy value, like `array_filter()` | `filter()` |
| Keep elements your callback approves | Either, with a callback returning `bool` |
| Side effects for rejected elements | `select()` with `onReject:` |

## `skipWhile()`

Skips elements from the beginning of the pipeline as long as a predicate returns `true`. Once the predicate returns `false` for the first time, all remaining elements pass through unchecked.

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

A `map()` callback that conditionally yields acts as a filter and a transformer in one step; see [`map()`](transformation.md#map):

```php
$result = take([1, 2, 3, 4])
    ->map(function ($x) {
        if ($x % 2 === 0) {
            yield $x * 10;
        }
    })
    ->toList(); // [20, 40]
```
