# Collection Methods

Collection methods convert a pipeline into an array or iterate over its elements.

## Terminal Operations

A pipeline is **lazy**: it does not process data until a **terminal operation** is called. These methods consume the pipeline to produce a final result.

Understanding this is key to using the library effectively. No work is done until one of these methods (or a `foreach` loop) pulls data through the pipeline. Once consumed, a streaming pipeline cannot be rewound—just like the generators it is built on.

Common terminal operations include:

- `toList()` and `toAssoc()` - Convert to arrays
- `fold()` and `reduce()` - Aggregate to a single value
- `count()`, `min()`, `max()`, `first()`, `last()` - Compute simple aggregates
- `each()` - Iterate and perform side effects
- `finalVariance()` - Calculate comprehensive statistics
- `reservoir()` - Sample random elements

## Array Conversion

### `toList()`

Returns all values as a numerically indexed array, discarding keys.

**Signature**: `toList(): array`

**Behavior**:

- This is a terminal operation.
- Because keys are discarded, every value is returned—even when keys repeat, as commonly happens after `map()` expands generators.

**Examples**:

```php
$result = take(['a' => 1, 'b' => 2, 'c' => 3])->toList();
// [1, 2, 3]

// Duplicate keys are not a problem
$result = map(function () {
    yield 'foo' => 'bar';
    yield 'foo' => 'baz';
})->toList();
// ['bar', 'baz'] — iterator_to_array() would have returned just ['foo' => 'baz']
```

### `toAssoc()`

Returns all values as an associative array, preserving keys.

**Signature**: `toAssoc(): array`

**Behavior**:

- This is a terminal operation.
- With duplicate keys, later values overwrite earlier ones—an inherent property of PHP arrays. Use `toList()` when every value matters more than the keys.

**Examples**:

```php
$result = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->map(fn($x) => $x * 2)
    ->toAssoc();
// ['a' => 2, 'b' => 4, 'c' => 6]
```

The deprecated `toArray()` method is an older spelling: prefer `toList()` and `toAssoc()`.

## Iteration

### `getIterator()`

Makes the pipeline usable directly in a `foreach` loop; part of the `IteratorAggregate` interface, normally called by PHP itself.

**Signature**: `getIterator(): Traversable`

**Examples**:

```php
$pipeline = take(['a' => 1, 'b' => 2]);

foreach ($pipeline as $key => $value) {
    echo "$key: $value\n";
}
```

An unprimed pipeline iterates as empty, so a function can return `new Standard()` instead of `null` and callers need no special checks. Where an `Iterator` (not just a `Traversable`) is required, wrap the pipeline: `new IteratorIterator($pipeline)`.

### `each()`

Eagerly iterates over all elements, applying a callback to each.

**Signature**: `each(callable $func, bool $discard = true): void`

- `$func`: A callback receiving `($value, $key)`; its return value is ignored.
- `$discard`: By default the pipeline is discarded after iteration, protecting against accidental reuse. Pass `false` to keep an array-backed pipeline intact for further use.

**Behavior**:

- This is a terminal operation, intended for side effects such as logging or database writes.

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

Returns a forward-only iterator that keeps its position across `foreach` loops. Where a generator would throw "Cannot traverse an already closed generator" on a second loop, a cursor simply continues from where the previous loop stopped—like a database cursor.

**Signature**: `cursor(): Iterator`

**Behavior**:

- Breaking out of a loop and re-entering continues *after* the last seen element.
- An exhausted cursor iterates as empty; no errors.
- Pass the cursor to `take()` to continue with pipeline operations on the remaining elements.

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

- The returned pipeline is a new instance holding up to `$count` elements, keys preserved—including duplicates.
- This is destructive: the peeked elements are consumed from the source pipeline. To put them back, use `prepend()`.

**Examples**:

```php
$pipeline = take([1, 2, 3, 4, 5]);

$head = $pipeline->peek(2)->toList(); // [1, 2]
$rest = $pipeline->toList();          // [3, 4, 5]

// Non-destructive look at the first elements
$pipeline = take($stream);
$sample = $pipeline->peek(10)->toList();
$pipeline->prepend($sample); // Restore the peeked elements
```
