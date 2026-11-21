# Aggregation Methods

Aggregation methods reduce a pipeline to a single value. They are terminal operations: they consume the pipeline and produce a final result.

## `fold()`

Reduces the pipeline to a single value, starting from a required initial value. Use this method for aggregations. The explicit initial value makes the result type predictable for readers and for static analyzers.

**Signature**: `fold($initial, ?callable $func = null): mixed`

- `$initial`: The required initial value for the accumulator. `fold()` returns this value for an empty pipeline.
- `$func`: The reduction function. If `null`, it defaults to summation.

**Callback Signature**: `function(mixed $carry, mixed $item): mixed`

**Examples**:

```php
// Summation is the default
$sum = take([1, 2, 3])->fold(0); // 6

// Building an array
$result = take([1, 2, 3])->fold([], fn($arr, $item) => [...$arr, $item * 2]); // [2, 4, 6]

// Grouping items
$grouped = take($items)->fold([], function ($groups, $item) {
    $groups[$item['category']][] = $item;

    return $groups;
});
```

## `reduce()`

This method is an alias for `fold()`. It reverses the argument order and makes the initial value optional, as `array_reduce()` does.

**Signature**: `reduce(?callable $func = null, $initial = null): mixed`

- `$func`: The reduction function. If `null`, it defaults to summation.
- `$initial`: The initial value for the accumulator. `reduce()` replaces `null` with `0`, which suits the default summation.

**Examples**:

```php
// Default summation
$sum = take([1, 2, 3, 4, 5])->reduce(); // 15

// Sum with an initial value
$sum = take([1, 2, 3])->reduce(null, 10); // 16

// Product
$product = take([2, 3, 4])->reduce(fn($carry, $item) => $carry * $item, 1); // 24

// String concatenation
$string = take(['Hello', ' ', 'World'])->reduce(fn($carry, $item) => $carry . $item, ''); // "Hello World"
```

### Prefer `fold()` Over `reduce()`

```php
// With reduce(): the initial value and the result type are implicit
$result = take($items)->reduce($buildArray);

// With fold(): the initial value, and therefore the type, is explicit
$result = take($items)->fold([], $buildArray);
```

## `count()`

Counts the elements in the pipeline. The pipeline implements `Countable`, so PHP's `count()` function also accepts it.

**Signature**: `count(): int`

**Behavior**:

- This is a terminal operation. It consumes a streaming pipeline. To count elements without consuming them, use [`runningCount()`](utility.md#runningcount).
- Returns `0` for an empty or unprimed pipeline.

**Examples**:

```php
$count = take([1, 2, 3, 4, 5])->count(); // 5

// Count after filtering
$count = take(range(1, 100))
    ->filter(fn($x) => $x % 2 === 0)
    ->count(); // 50
```

## `min()`

Finds the lowest value using standard PHP comparison rules.

**Signature**: `min(): mixed|null`

**Behavior**:

- Returns `null` for an empty pipeline.

**Examples**:

```php
$min = take([5, 2, 8, 1, 9])->min(); // 1

$min = take(['banana', 'apple', 'cherry'])->min(); // "apple"
```

## `max()`

Finds the highest value using standard PHP comparison rules.

**Signature**: `max(): mixed|null`

**Behavior**:

- Returns `null` for an empty pipeline.

**Examples**:

```php
$max = take([5, 2, 8, 1, 9])->max(); // 9

$max = take(['banana', 'apple', 'cherry'])->max(); // "cherry"
```

## `first()`

Returns the first element of the pipeline.

**Signature**: `first(): mixed|null`

**Behavior**:

- Returns `null` for an empty pipeline.
- Stops processing after the first element. With a streaming source, the pipeline computes only the elements up to and including the first result.

**Examples**:

```php
$first = take([1, 2, 3])->first(); // 1

// Finds the first match without scanning the rest of the file
$firstError = take(new SplFileObject('app.log'))
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->first();
```

## `last()`

Returns the last element of the pipeline.

**Signature**: `last(): mixed|null`

**Behavior**:

- Returns `null` for an empty pipeline.
- Consumes the entire pipeline to find the last element.

**Examples**:

```php
$last = take([1, 2, 3])->last(); // 3
```
