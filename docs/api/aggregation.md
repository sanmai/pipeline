# Aggregation Methods

Aggregation methods reduce a pipeline to a single value. They are terminal operations, meaning they consume the pipeline to produce a final result.

## `reduce()`

Reduces the pipeline to a single value using a callback.

**Signature**: `reduce(?callable $func = null, $initial = null): mixed`

-   `$func`: The reduction function `function($carry, $item)`. If `null`, it defaults to summation.
-   `$initial`: An optional initial value for the reduction.

**Examples**:

```php
// Default summation
$sum = take([1, 2, 3, 4, 5])->reduce(); // 15

// Product with an initial value
$product = take([2, 3, 4])->reduce(fn($carry, $item) => $carry * $item, 1); // 24
```

## `fold()`

Reduces the pipeline to a single value, but requires an initial value, making it more predictable and type-safe. **This is the recommended method for most aggregations.**

**Signature**: `fold($initial, ?callable $func = null): mixed`

-   `$initial`: The required initial value.
-   `$func`: The reduction function. Defaults to summation if `null`.

The type of the result is determined by the type of the initial value, which helps with static analysis and prevents errors with empty pipelines.

**Examples**:

```php
// Summation
$sum = take([1, 2, 3])->fold(0); // 6

// Building an array
$result = take([1, 2, 3])->fold([], fn($arr, $item) => [...$arr, $item * 2]); // [2, 4, 6]

// Grouping items
$grouped = take($items)->fold([], function($groups, $item) {
    $key = $item['category'];
    $groups[$key][] = $item;
    return $groups;
});
```

## `min()` and `max()`

Find the minimum or maximum value in the pipeline.

-   `min(): mixed|null`
-   `max(): mixed|null`

Both return `null` for an empty pipeline and use standard PHP comparison rules.

**Examples**:

```php
$min = take([5, 2, 8, 1, 9])->min(); // 1
$max = take([5, 2, 8, 1, 9])->max(); // 9
```

## `count()`

Counts the number of elements in the pipeline. This is a terminal operation.

**Signature**: `count(): int`

-   For arrays, it uses the native `count()` for efficiency.

**Example**:

```php
$count = take(range(1, 100))
    ->filter(fn($x) => $x % 2 === 0)
    ->count(); // 50
```
