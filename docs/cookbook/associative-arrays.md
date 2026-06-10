# Associative Array Recipes

Working with associative arrays requires more than just transforming values. This guide shows how to manipulate both keys and values using the library's functional patterns.

For simple cases, dedicated methods already exist—reach for them first:

- [`flip()`](../api/utility.md#flip) swaps keys and values.
- [`keys()`](../api/utility.md#keys) and [`values()`](../api/utility.md#values) extract one side.
- `map()` with `yield $key => $value` sets keys directly.

## The Key Manipulation Pattern

For anything beyond that, use this three-step pattern:

1. **`tuples()`** - Convert to `[key, value]` pairs
2. **`map()`** or **`filter()`** - Transform or drop the pairs
3. **`unpack()`** - Reconstruct the key-value stream

The reconstruction step is always the same: `unpack(fn($key, $value) => yield $key => $value)` spreads each tuple back into a key and a value.

### Prefixing Keys

Add a prefix to all keys in an associative array:

```php
use function Pipeline\take;

$data = ['id' => 123, 'name' => 'Alice', 'status' => 'active'];

$result = take($data)
    ->tuples()
    ->map(fn($tuple) => ['user_' . $tuple[0], $tuple[1]])
    ->unpack(fn($key, $value) => yield $key => $value)
    ->toAssoc();

// Result:
// [
//     'user_id' => 123,
//     'user_name' => 'Alice',
//     'user_status' => 'active'
// ]
```

### Filtering by Key

Remove entries based on their keys:

```php
$data = ['user_id' => 1, 'password' => 'secret', 'email' => 'alice@example.com'];

$safe = take($data)
    ->tuples()
    ->filter(fn($tuple) => $tuple[0] !== 'password')
    ->unpack(fn($key, $value) => yield $key => $value)
    ->toAssoc();

// Result: ['user_id' => 1, 'email' => 'alice@example.com']
```

### Transforming Keys and Values Together

Apply different transformations to keys and values in one pass:

```php
$data = ['first_name' => 'alice', 'last_name' => 'smith'];

$result = take($data)
    ->tuples()
    ->map(fn($tuple) => [
        str_replace('_', '-', $tuple[0]),  // kebab-case keys
        ucfirst($tuple[1]),                // capitalize values
    ])
    ->unpack(fn($key, $value) => yield $key => $value)
    ->toAssoc();

// Result: ['first-name' => 'Alice', 'last-name' => 'Smith']
```

## Advanced Patterns

### Merging with Defaults

Ensure all required keys exist with default values:

```php
$defaults = ['name' => 'Unknown', 'age' => 0, 'active' => true];
$input = ['name' => 'Bob', 'age' => 25];

$result = take($defaults)
    ->tuples()
    ->map(function ($tuple) use ($input) {
        [$key, $default] = $tuple;

        return [$key, $input[$key] ?? $default];
    })
    ->unpack(fn($key, $value) => yield $key => $value)
    ->toAssoc();

// Result: ['name' => 'Bob', 'age' => 25, 'active' => true]
```

### Grouping by Key Pattern

Group data by a key prefix:

```php
$data = [
    'user_name' => 'Alice',
    'user_email' => 'alice@example.com',
    'order_id' => 123,
    'order_total' => 99.99,
];

$grouped = take($data)
    ->tuples()
    ->fold([], function ($groups, $tuple) {
        [$key, $value] = $tuple;
        $prefix = explode('_', $key)[0];
        $groups[$prefix][$key] = $value;

        return $groups;
    });

// Result:
// [
//     'user' => ['user_name' => 'Alice', 'user_email' => 'alice@example.com'],
//     'order' => ['order_id' => 123, 'order_total' => 99.99]
// ]
```

## Performance Considerations

On an array-backed pipeline, `tuples()` builds the array of pairs eagerly. For large associative arrays, call `stream()` first to process entries one at a time:

```php
$result = take($largeArray)
    ->stream()
    ->tuples()
    ->map(fn($tuple) => ['prefix_' . $tuple[0], $tuple[1]])
    ->unpack(fn($key, $value) => yield $key => $value)
    ->toAssoc();
```

## See Also

- [Utility Methods](../api/utility.md) - Documentation for `tuples()`, `flip()`, `keys()`, and `values()`
- [Transformation Methods](../api/transformation.md) - Documentation for `map()` and `unpack()`
