# Aggregation Methods

Aggregation methods reduce a pipeline to a single value. They are terminal operations: they consume the pipeline and produce a final result.

## `fold()`

Reduces the pipeline to a single value, starting from a required initial value. **This is the recommended method for aggregations**: the explicit initial value makes the result type predictable, for both readers and static analyzers.

**Signature**: `fold($initial, ?callable $func = null): mixed`

- `$initial`: The required initial value for the accumulator. Also the result for an empty pipeline.
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

An alias for `fold()` with the arguments reversed and the initial value optional, mirroring `array_reduce()`.

**Signature**: `reduce(?callable $func = null, $initial = null): mixed`

- `$func`: The reduction function. If `null`, it defaults to summation.
- `$initial`: The initial value for the accumulator; `null` is treated as `0`, which suits the default summation.

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

### Why Choose `fold()` Over `reduce()`?

```php
// With reduce(): what is the initial value? What type comes out?
$result = take($items)->reduce($buildArray);

// With fold(): the initial value, and therefore the type, is explicit
$result = take($items)->fold([], $buildArray);
```

## `count()`

Counts the elements in the pipeline. A pipeline can also be passed to PHP's `count()` function, as it implements `Countable`.

**Signature**: `count(): int`

**Behavior**:

- This is a terminal operation: it consumes a streaming pipeline. To count elements without consuming them, use [`runningCount()`](utility.md#runningcount).
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

**Signature**: `min(): mixed`

**Behavior**:

- Returns `null` for an empty pipeline.

**Examples**:

```php
$min = take([5, 2, 8, 1, 9])->min(); // 1

$min = take(['banana', 'apple', 'cherry'])->min(); // "apple"
```

## `max()`

Finds the highest value using standard PHP comparison rules.

**Signature**: `max(): mixed`

**Behavior**:

- Returns `null` for an empty pipeline.

**Examples**:

```php
$max = take([5, 2, 8, 1, 9])->max(); // 9

$max = take(['banana', 'apple', 'cherry'])->max(); // "cherry"
```

## `first()`

Returns the first element of the pipeline.

**Signature**: `first(): mixed`

**Behavior**:

- Returns `null` for an empty pipeline.
- Stops processing immediately after the first element: with a streaming source, only one element is ever computed.

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

**Signature**: `last(): mixed`

**Behavior**:

- Returns `null` for an empty pipeline.
- Consumes the entire pipeline to find the last element.

**Examples**:

```php
$last = take([1, 2, 3])->last(); // 3
```
