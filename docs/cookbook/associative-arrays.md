# Associative Array Recipes

Working with associative arrays requires more than just transforming values. This guide shows you how to manipulate both keys and values using the library's functional patterns.

## The Key Manipulation Pattern

To modify array keys, use this three-step pattern:

1. **`tuples()`** - Convert to `[key, value]` pairs
2. **`map()`** - Transform the pairs
3. **`unpack()`** - Reconstruct the array

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

### Swapping Keys and Values

Flip keys and values, similar to `array_flip()`:

```php
$data = ['a' => 1, 'b' => 2, 'c' => 3];

$result = take($data)
    ->tuples()
    ->map(fn($tuple) => [$tuple[1], $tuple[0]])
    ->unpack(fn($key, $value) => yield $key => $value)
    ->toAssoc();

// Result: [1 => 'a', 2 => 'b', 3 => 'c']
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

### Transforming Keys and Values

Apply different transformations to keys and values:

```php
$data = ['first_name' => 'alice', 'last_name' => 'smith'];

$result = take($data)
    ->tuples()
    ->map(fn($tuple) => [
        str_replace('_', '-', $tuple[0]),  // kebab-case keys
        ucfirst($tuple[1])                 // capitalize values
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
    ->map(function($tuple) use ($input) {
        [$key, $default] = $tuple;
        return [$key, $input[$key] ?? $default];
    })
    ->unpack(fn($key, $value) => yield $key => $value)
    ->toAssoc();

// Result: ['name' => 'Bob', 'age' => 25, 'active' => true]
```

### Grouping by Key Pattern

Group data by a key pattern:

```php
$data = [
    'user_name' => 'Alice',
    'user_email' => 'alice@example.com',
    'order_id' => 123,
    'order_total' => 99.99
];

$grouped = take($data)
    ->tuples()
    ->fold([], function($groups, $tuple) {
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

The `tuples -> map -> unpack` pattern creates intermediate arrays. For large associative arrays, consider using `stream()` first to process entries one at a time:

```php
$result = take($largeArray)
    ->stream()
    ->tuples()
    ->map(fn($tuple) => ['prefix_' . $tuple[0], $tuple[1]])
    ->unpack(fn($key, $value) => yield $key => $value)
    ->toAssoc();
```

## See Also

- [Utility Methods](../api/utility.md) - Documentation for `tuples()`, `flip()`, and `keys()`
- [Transformation Methods](../api/transformation.md) - Documentation for `map()` and `unpack()`