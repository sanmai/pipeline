# Aggregation Methods

Aggregation methods reduce a pipeline to a single value. They are terminal operations, meaning they consume the pipeline and produce a final result.

## `reduce()`

Reduces the pipeline to a single value. It is a flexible method that can be used for a variety of aggregation tasks.

**Signature**: `reduce(?callable $func = null, $initial = null): mixed`

-   `$func`: The reduction function. If `null`, it defaults to summation.
-   `$initial`: The initial value for the accumulator.

**Callback Signature**: `function(mixed $carry, mixed $item): mixed`

**Behavior**:

-   If `$func` is `null`, `reduce` calculates the sum of the elements.
-   If `$initial` is provided, it is used as the starting value for the reduction.
-   For improved clarity and type safety, especially in complex cases, consider using `fold()` instead.

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

## `fold()`

Similar to `reduce()`, but with a required initial value, making it more predictable and type-safe. **This is the recommended method for aggregations.**

**Signature**: `fold($initial, ?callable $func = null): mixed`

-   `$initial`: The required initial value for the accumulator.
-   `$func`: The reduction function. If `null`, it defaults to summation.

**Behavior**:

-   `fold()` is the recommended method for most aggregation tasks due to its explicit nature.
-   The type of the result is determined by the type of the initial value.

### Why Choose `fold()` Over `reduce()`?

While `reduce()` offers convenience with its implicit initial value, `fold()` provides better clarity and type safety:

```php
// With reduce(): What's the initial value? What type will we get?
$result = take($items)->reduce($buildArray);

// With fold(): Clear initial value and expected type
$result = take($items)->fold([], $buildArray);  // Clearly building an array

// Type-safe aggregation with fold()
$stats = take($numbers)->fold(
    ['sum' => 0, 'count' => 0, 'avg' => 0.0],
    function($acc, $num) {
        $acc['sum'] += $num;
        $acc['count']++;
        $acc['avg'] = $acc['sum'] / $acc['count'];
        return $acc;
    }
);
```

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

## `min()`

Finds the minimum value in the pipeline.

**Signature**: `min(): mixed|null`

**Behavior**:

-   Returns the minimum value, or `null` for an empty pipeline.
-   Uses standard PHP comparison rules.

**Examples**:

```php
// Numeric minimum
$min = take([5, 2, 8, 1, 9])->min(); // 1

// String minimum
$min = take(['banana', 'apple', 'cherry'])->min(); // "apple"
```

## `max()`

Finds the maximum value in the pipeline.

**Signature**: `max(): mixed|null`

**Behavior**:

-   Returns the maximum value, or `null` for an empty pipeline.
-   Uses standard PHP comparison rules.

**Examples**:

```php
// Numeric maximum
$max = take([5, 2, 8, 1, 9])->max(); // 9

// String maximum
$max = take(['banana', 'apple', 'cherry'])->max(); // "cherry"
```

## `count()`

Counts the number of elements in the pipeline.

**Signature**: `count(): int`

**Behavior**:

-   This is a terminal operation that consumes the pipeline.
-   For arrays, it uses the native `count()` function for efficiency.

**Examples**:

```php
// Basic count
$count = take([1, 2, 3, 4, 5])->count(); // 5

// Count after filtering
$count = take(range(1, 100))
    ->filter(fn($x) => $x % 2 === 0)
    ->count(); // 50
```
