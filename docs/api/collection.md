# Collection Methods

Collection methods convert a pipeline into an array or iterate over its elements.

## Terminal Operations

A pipeline defers execution until a **terminal operation** starts. Terminal operations consume the pipeline and produce a final result.

The pipeline processes data only when one of these methods (or a `foreach` loop) pulls data from it. After consumption, you cannot rewind a streaming pipeline. The same limit applies to the generators that it uses.

Terminal operations include:

- `toList()` and `toAssoc()` - Convert to arrays
- `collect()` - Pass all values to a callback
- `fold()` and `reduce()` - Aggregate to a single value
- `count()`, `min()`, `max()`, `first()`, `last()` - Compute simple aggregates
- `each()` - Iterate and perform side effects
- `finalVariance()` - Calculate statistics
- `reservoir()` - Sample random elements

## Array Conversion

### `toList()`

Returns all values as a numerically indexed array and discards keys.

**Signature**: `toList(): array`

**Behavior**:

- This is a terminal operation.
- Because it discards keys, it returns every value, also when keys repeat. Repeated keys are frequent after `map()` expands generators.

**Examples**:

```php
$result = take(['a' => 1, 'b' => 2, 'c' => 3])->toList();
// [1, 2, 3]

// Duplicate keys are not a problem
$result = map(function () {
    yield 'foo' => 'bar';
    yield 'foo' => 'baz';
})->toList();
// ['bar', 'baz']; iterator_to_array() returns only ['foo' => 'baz']
```

### `toAssoc()`

Returns all values as an associative array and preserves keys.

**Signature**: `toAssoc(): array`

**Behavior**:

- This is a terminal operation.
- With duplicate keys, later values overwrite earlier ones, as in any PHP array. Use `toList()` when you need every value more than the keys.

**Examples**:

```php
$result = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->map(fn($x) => $x * 2)
    ->toAssoc();
// ['a' => 2, 'b' => 4, 'c' => 6]
```

The deprecated `toArray()` method is an older spelling. Replace `toArray()` and `toArray(false)` with `toList()`. Replace `toArray(true)` with `toAssoc()`.

### `collect()`

Passes a list of all values to a callback and returns the callback's result.

**Signature**: `collect(?callable $func = null): mixed`

- `$func`: A callback that receives a `list` of all values. Without a callback, `collect()` returns the list, the same as `toList()`.

**Behavior**:

- This is a terminal operation.
- It discards keys, as `toList()` does.
- Use it to end a pipeline with a function that requires the whole array, such as `implode()` or `array_sum()`.

**Examples**:

```php
$csv = take(ItemCondition::cases())
    ->cast(fn(ItemCondition $condition) => $condition->value)
    ->collect(fn(array $values) => implode(',', $values));

// With PHP 8.6 partial function application
$csv = take($values)->collect(implode(',', ...));
```

## Iteration

### `getIterator()`

This method lets you use the pipeline directly in a `foreach` loop. It implements the `IteratorAggregate` interface, and PHP calls it for you.

**Signature**: `getIterator(): Traversable`

**Examples**:

```php
$pipeline = take(['a' => 1, 'b' => 2]);

foreach ($pipeline as $key => $value) {
    echo "$key: $value\n";
}
```

An unprimed pipeline iterates as empty. Thus a function can return `new Standard()` instead of `null`, and callers need no special checks. Where code requires an `Iterator` (not only a `Traversable`), wrap the pipeline: `new IteratorIterator($pipeline)`.

### `each()`

Eagerly iterates over all elements and applies a callback to each.

**Signature**: `each(callable $func, bool $discard = true): void`

- `$func`: A callback that receives `($value, $key)`. The method ignores its return value.
- `$discard`: By default, the method discards the pipeline after iteration to prevent accidental reuse. Pass `false` to keep an array-backed pipeline for further use.

**Behavior**:

- This is a terminal operation for side effects such as logging or database writes.

**Examples**:

```php
// Print each value
take([1, 2, 3])->each(fn($x) => print("Value: $x\n"));

// Keys are available as the second argument
take(['a' => 1])->each(fn($value, $key) => printf('%s => %s', $key, $value));

// Save to a database
take($users)->each(fn($user) => $user->save());
```

## Partial Consumption

### `cursor()`

Returns a forward-only iterator that keeps its position across `foreach` loops. On a second loop, a generator throws "Cannot traverse an already closed generator". A cursor continues from the position where the previous loop stopped, as a database cursor does.

**Signature**: `cursor(): Iterator`

**Behavior**:

- If you break out of a loop and start a new loop, iteration continues *after* the last element that the first loop received.
- An exhausted cursor iterates as empty and causes no errors.
- Pass the cursor to `take()` to apply pipeline operations to the remaining elements.

**Examples**:

```php
$cursor = take([1, 2, 3, 4, 5])->cursor();

foreach ($cursor as $value) {
    echo $value; // 1, 2
    if ($value === 2) {
        break;
    }
}

// Continue with the remaining elements
foreach ($cursor as $value) {
    echo $value; // 3, 4, 5
}

// Or re-enter the pipeline world
$remaining = take($cursor)->count();
```

### `peek()`

Removes the first N elements from the pipeline and returns them as a *new* pipeline. The original pipeline continues with the remaining elements.

**Signature**: `peek(int $count): self`

- `$count`: The number of elements to take.

**Behavior**:

- The returned pipeline is a new instance with up to `$count` elements. It preserves keys, including duplicate keys.
- This operation is destructive: it consumes the peeked elements from the source pipeline. To restore them, use `prepend()`.

**Examples**:

```php
$pipeline = take([1, 2, 3, 4, 5]);

$head = $pipeline->peek(2)->toList(); // [1, 2]
$rest = $pipeline->toList();          // [3, 4, 5]

// Non-destructive inspection of the first elements
$pipeline = take($stream);
$sample = $pipeline->peek(10)->toList();
$pipeline->prepend($sample); // Restore the peeked elements
```
