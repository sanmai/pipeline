# Transformation Methods

Methods that transform elements flowing through the pipeline. All transformation methods return `$this` for method chaining.

## Core Transformation Methods

### `map(?callable $func = null)`

Transforms each element using a callback. Callbacks can return single values or yield multiple values using generators.

**Parameters:**
- `$func` (?callable): Transformation callback. If null, acts as no-op.

**Returns:** $this (Pipeline\Standard instance)

**Callback signature:** `function(mixed $value): mixed|Generator`

**Examples:**

```php
// Simple transformation
$result = take([1, 2, 3])
    ->map(fn($x) => $x * 2)
    ->toList();
// Result: [2, 4, 6]

// Property extraction
$users = [
    ['name' => 'Alice', 'age' => 30],
    ['name' => 'Bob', 'age' => 25]
];
$names = take($users)
    ->map(fn($user) => $user['name'])
    ->toList();
// Result: ['Alice', 'Bob']

// Generator callback (one-to-many)
$result = take(['hello', 'world'])
    ->map(function($word) {
        foreach (str_split($word) as $char) {
            yield strtoupper($char);
        }
    })
    ->toList();
// Result: ['H', 'E', 'L', 'L', 'O', 'W', 'O', 'R', 'L', 'D']

// Chaining transformations
$result = take([1, 2, 3])
    ->map(fn($x) => $x * 2)
    ->map(fn($x) => $x + 1)
    ->toList();
// Result: [3, 5, 7]

// No-op when null
$result = take([1, 2, 3])
    ->map(null)
    ->toList();
// Result: [1, 2, 3]
```

### `cast(?callable $func = null)`

Transforms each element using a callback that must return exactly one value. Unlike `map()`, generator returns are not specially handled.

**Parameters:**
- `$func` (?callable): Transformation callback. If null, acts as no-op.

**Returns:** $this (Pipeline\Standard instance)

**Callback signature:** `function(mixed $value): mixed`

**Examples:**

```php
// Type casting
$result = take(['1', '2', '3'])
    ->cast('intval')
    ->toList();
// Result: [1, 2, 3]

// String transformation
$result = take(['hello', 'world'])
    ->cast('strtoupper')
    ->toList();
// Result: ['HELLO', 'WORLD']

// Custom casting
$result = take([1.7, 2.3, 3.9])
    ->cast(fn($x) => (int)round($x))
    ->toList();
// Result: [2, 2, 4]

// Object property access
$objects = [
    (object)['value' => 10],
    (object)['value' => 20]
];
$result = take($objects)
    ->cast(fn($obj) => $obj->value)
    ->toList();
// Result: [10, 20]

// Array optimization (uses array_map internally)
$result = take(['a', 'b', 'c'])
    ->cast('strtoupper')
    ->toList();
// Result: ['A', 'B', 'C']
```

## Flattening Methods

### `flatten()`

Flattens nested iterables by one level. Each element that is iterable will have its values yielded individually.

**Returns:** $this (Pipeline\Standard instance)

**Examples:**

```php
// Flatten nested arrays
$result = take([
    [1, 2, 3],
    [4, 5],
    [6]
])
->flatten()
->toList();
// Result: [1, 2, 3, 4, 5, 6]

// Flatten mixed depth
$result = take([
    1,
    [2, 3],
    [[4]],  // Not fully flattened
    5
])
->flatten()
->toList();
// Result: [1, 2, 3, [4], 5]

// Flatten generator results
$result = take([
    range(1, 3),
    range(4, 6)
])
->flatten()
->toList();
// Result: [1, 2, 3, 4, 5, 6]

// Multiple flatten for deep nesting
$result = take([[[1]], [[2]], [[3]]])
    ->flatten()  // First level
    ->flatten()  // Second level
    ->toList();
// Result: [1, 2, 3]
```

### `unpack(?callable $func = null)`

Unpacks array elements as arguments to a callback. Without callback, acts like `flatten()`.

**Parameters:**
- `$func` (?callable): Callback receiving unpacked arguments

**Returns:** $this (Pipeline\Standard instance)

**Callback signature:** `function(...$args): mixed`

**Examples:**

```php
// Unpack array as arguments
$result = take([
    [1, 2],
    [3, 4],
    [5, 6]
])
->unpack(fn($a, $b) => $a + $b)
->toList();
// Result: [3, 7, 11]

// Calculate distances
$coordinates = [
    [3, 4],    // (3, 4)
    [5, 12],   // (5, 12)
    [8, 15]    // (8, 15)
];
$distances = take($coordinates)
    ->unpack(fn($x, $y) => sqrt($x**2 + $y**2))
    ->toList();
// Result: [5.0, 13.0, 17.0]

// Variable arguments
$result = take([
    [1],
    [1, 2],
    [1, 2, 3]
])
->unpack(fn(...$args) => array_sum($args))
->toList();
// Result: [1, 3, 6]

// Without callback (flattens)
$result = take([[1, 2], [3, 4]])
    ->unpack()
    ->toList();
// Result: [1, 2, 3, 4]

// String formatting
$data = [
    ['Alice', 30],
    ['Bob', 25]
];
$result = take($data)
    ->unpack(fn($name, $age) => "$name is $age years old")
    ->toList();
// Result: ['Alice is 30 years old', 'Bob is 25 years old']
```

## Chunking

### `chunk(int $length, bool $preserve_keys = false)`

Splits the pipeline into chunks of specified size.

**Parameters:**
- `$length` (int): Size of each chunk (must be >= 1)
- `$preserve_keys` (bool): Whether to preserve original keys (default: false)

**Returns:** $this (Pipeline\Standard instance)

**Examples:**

```php
// Basic chunking
$result = take([1, 2, 3, 4, 5, 6, 7])
    ->chunk(3)
    ->toList();
// Result: [[1, 2, 3], [4, 5, 6], [7]]

// Preserve keys
$data = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];
$result = take($data)
    ->chunk(2, preserve_keys: true)
    ->toList();
// Result: [['a' => 1, 'b' => 2], ['c' => 3, 'd' => 4]]

// Process in batches
take(range(1, 100))
    ->chunk(10)
    ->each(function($batch) {
        echo "Processing batch of " . count($batch) . " items\n";
        processBatch($batch);
    });

// Chunk and transform
$result = take(range(1, 10))
    ->chunk(3)
    ->map(fn($chunk) => array_sum($chunk))
    ->toList();
// Result: [6, 15, 24, 10]

// Large file processing
take(new SplFileObject('large.csv'))
    ->map('str_getcsv')
    ->chunk(1000)
    ->each(function($records) {
        insertBatchToDatabase($records);
    });
```

## Slicing

### `slice(int $offset, ?int $length = null)`

Extracts a portion of the pipeline, similar to `array_slice()`.

**Parameters:**
- `$offset` (int): Start position (negative counts from end)
- `$length` (?int): Number of elements (negative stops before end)

**Returns:** $this (Pipeline\Standard instance)

**Examples:**

```php
// Basic slice
$result = take([1, 2, 3, 4, 5])
    ->slice(1, 3)
    ->toList();
// Result: [2, 3, 4]

// Negative offset (from end)
$result = take([1, 2, 3, 4, 5])
    ->slice(-3)
    ->toList();
// Result: [3, 4, 5]

// Negative length (stop before end)
$result = take([1, 2, 3, 4, 5])
    ->slice(0, -1)
    ->toList();
// Result: [1, 2, 3, 4]

// Middle section
$result = take(range(1, 10))
    ->slice(3, 4)
    ->toList();
// Result: [4, 5, 6, 7]

// Skip first N elements
$result = take(range(1, 10))
    ->slice(5)
    ->toList();
// Result: [6, 7, 8, 9, 10]

// Take first N elements
$result = take(range(1, 10))
    ->slice(0, 3)
    ->toList();
// Result: [1, 2, 3]

// Pagination pattern
function paginate($data, $page, $perPage = 10) {
    $offset = ($page - 1) * $perPage;
    return take($data)
        ->slice($offset, $perPage)
        ->toList();
}
```

## Complex Transformation Examples

### Data Normalization

```php
// Normalize user data
$users = [
    ['name' => 'alice', 'email' => 'ALICE@EXAMPLE.COM'],
    ['name' => 'BOB', 'email' => 'bob@example.com'],
];

$normalized = take($users)
    ->map(function($user) {
        return [
            'name' => ucfirst(strtolower($user['name'])),
            'email' => strtolower($user['email']),
            'id' => uniqid()
        ];
    })
    ->toList();
```

### Expanding Data

```php
// Generate time series data
$result = take(['2024-01-01', '2024-01-02', '2024-01-03'])
    ->map(function($date) {
        $timestamp = strtotime($date);
        yield ['date' => $date, 'hour' => 0, 'value' => rand(0, 100)];
        yield ['date' => $date, 'hour' => 12, 'value' => rand(0, 100)];
    })
    ->toList();
// Result: 6 entries (2 per date)
```

### Recursive Flattening

```php
// Flatten deeply nested structure
function deepFlatten($data) {
    return take([$data])
        ->map(function($item) {
            if (is_iterable($item)) {
                foreach ($item as $value) {
                    yield from deepFlatten($value)->toList();
                }
            } else {
                yield $item;
            }
        })
        ->toList();
}

$nested = [1, [2, [3, [4, 5]]], 6];
$flat = deepFlatten($nested);
// Result: [1, 2, 3, 4, 5, 6]
```

## Performance Tips

1. **Use `cast()` for simple transformations** - it's optimized for arrays
2. **Use `map()` when yielding multiple values** - it handles generators efficiently
3. **Chain transformations** rather than creating intermediate arrays
4. **Chunk before heavy processing** to manage memory usage

## Next Steps

- [Filtering Methods](filtering.md) - Methods for filtering elements
- [Aggregation Methods](aggregation.md) - Methods for reducing to single values