# Collection and Iteration Methods

These methods are used to convert a pipeline into an array or to iterate over its elements. Most of these are **terminal operations**, which consume the pipeline to produce a final result or trigger side effects.

## Array Conversion

### `toList()`

Converts the pipeline to a numerically indexed array, discarding keys. This is a terminal operation.

**Signature**: `toList(): array`

**Example**:

```php
$result = take(['a' => 1, 'b' => 2, 'c' => 3])->toList();
// Result: [1, 2, 3]
```

### `toAssoc()`

Converts the pipeline to an associative array, preserving original keys. This is a terminal operation.

**Signature**: `toAssoc(): array`

-   If there are duplicate keys, the last value overwrites previous ones.

**Example**:

```php
$result = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->map(fn($x) => $x * 2)
    ->toAssoc();
// Result: ['a' => 2, 'b' => 4, 'c' => 6]
```

## Iteration

### `getIterator()`

Returns an iterator for the pipeline, allowing it to be used in a `foreach` loop. This is the standard way to consume a pipeline.

**Signature**: `getIterator(): Traversable`

**Example**:

```php
$pipeline = take(['a' => 1, 'b' => 2]);
foreach ($pipeline as $key => $value) {
    echo "$key: $value\n";
}
```

### `each()`

Eagerly iterates over all elements, applying a function to each. This is a terminal operation, primarily used for side effects like logging or database writes.

**Signature**: `each(callable $func): void`

**Callback Signature**: `function(mixed $value, mixed $key): void`

**Example**:

```php
// Print each value
take([1, 2, 3])->each(fn($x) => print("Value: $x\n"));

// Save to a database
take($users)->each(fn($user) => $user->save());
```
