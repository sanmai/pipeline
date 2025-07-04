# Collection Methods

Collection methods are used to convert a pipeline into an array or to iterate over its elements.

## Terminal Operations

A pipeline is **lazy** and does not begin processing data until a **terminal operation** is called. These methods consume the pipeline to produce a final result.

Understanding this is key to using the library effectively. No work is done, and no memory is used by the pipeline itself, until one of these methods is invoked.

Common terminal operations include:
- `toList()` and `toAssoc()` - Convert to arrays
- `fold()` and `reduce()` - Aggregate to a single value
- `count()`, `min()`, `max()` - Calculate statistics
- `each()` - Iterate and perform side effects
- `finalVariance()` - Calculate comprehensive statistics

## Array Conversion

### `toList()`

Converts the pipeline to a numerically indexed array, discarding all keys.

**Signature**: `toList(): array`

**Behavior**:

-   This is a terminal operation.
-   It creates a new array with sequential numeric keys, starting from 0.

**Examples**:

```php
// Convert a pipeline to a simple array
$result = take(['a' => 1, 'b' => 2, 'c' => 3])->toList();
// Result: [1, 2, 3]
```

### `toAssoc()`

Converts the pipeline to an associative array, preserving the original keys.

**Signature**: `toAssoc(): array`

**Behavior**:

-   This is a terminal operation.
-   If there are duplicate keys, the last value will overwrite the previous ones.

**Examples**:

```php
// Preserve key-value associations
$result = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->map(fn($x) => $x * 2)
    ->toAssoc();
// Result: ['a' => 2, 'b' => 4, 'c' => 6]
```

## Iteration

### `getIterator()`

Returns an iterator for the pipeline, allowing you to use it in a `foreach` loop.

**Signature**: `getIterator(): Traversable`

**Behavior**:

-   This method is part of the `IteratorAggregate` interface.
-   It allows for manual control over the iteration process.

**Examples**:

```php
// Using foreach
$pipeline = take(['a' => 1, 'b' => 2]);
foreach ($pipeline as $key => $value) {
    echo "$key: $value\n";
}

// Manual iteration
$iterator = take([1, 2, 3])->getIterator();
while ($iterator->valid()) {
    echo $iterator->current();
    $iterator->next();
}
```

### `each()`

Eagerly iterates over all elements in the pipeline, applying a function to each.

**Signature**: `each(callable $func): void`

-   `$func`: The function to call for each element.

**Callback Signature**: `function(mixed $value, mixed $key): void`

**Behavior**:

-   This is a terminal operation.
-   It is primarily used for side effects, such as logging or database operations.

**Examples**:

```php
// Print each value
take([1, 2, 3])->each(fn($x) => print("Value: $x\n"));

// Save to a database
take($users)->each(fn($user) => $user->save());
```
