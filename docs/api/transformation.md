# Transformation Methods

Methods that transform elements flowing through the pipeline. All transformation methods return `$this` for method chaining.

## Core Transformation Methods

### `map(?callable $func = null)`

Transforms each element using a callback. Callbacks can return single values or yield multiple values using generators.

**Parameters:**
- `$func` (?callable): Transformation callback. If null, acts as no-op.

**Returns:** $this (Pipeline\Standard instance)

**Callback signature:** `function(mixed $value): mixed|Generator`

**When to Use:**
Use `map()` for any transformation, especially when your callback might return multiple values (by yielding from a generator). It's the most flexible transformation method.

**Performance Notes:**
- Processes elements one by one using a foreach loop
- Handles generators specially, yielding from them
- For simple 1-to-1 transformations on arrays, consider `cast()` for better performance

**Comparison with `cast()`:**
- `map()`: Handles generators specially by yielding from them, can expand one element to many
- `cast()`: Optimized for arrays, always 1-to-1 transformation, no special generator handling

### `map()` vs `cast()`: The Generator Gotcha

A critical difference between `map()` and `cast()` is how they handle a callback that returns a `Generator`.

- **`map()`** will **expand** the returned generator, yielding each of its values into the main pipeline. This is useful for one-to-many transformations.
- **`cast()`** will treat the returned generator as a **single value**, placing the `Generator` object itself into the pipeline.

Failing to choose the right method can lead to unexpected results.

**Example: The Gotcha in Action**

```php
// Goal: Create a pipeline containing two Generator objects.

// Using map() - This does NOT work as intended.
$result = take(['a', 'b'])
    ->map(function($letter) {
        // This generator will be EXPANDED by map(), not kept as an object.
        return (function() use ($letter) {
            yield $letter;
            yield strtoupper($letter);
        })();
    })
    ->toList();

// Incorrect Result: ['a', 'A', 'b', 'B'] - The generators were expanded!

// Using cast() - This is the correct solution.
$result = take(['a', 'b'])
    ->cast(function($letter) {
        // cast() treats the generator as a regular return value.
        return (function() use ($letter) {
            yield $letter;
            yield strtoupper($letter);
        })();
    })
    ->toList();

// Correct Result: [Generator, Generator] - The generators are preserved as values.
```

**Use `cast()` when you need to transform elements into `Generator` objects themselves.** Use `map()` when you want to transform one element into many.

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

**When to Use:**
Use `cast()` for simple, 1-to-1 value transformations (like type casting with `'intval'` or property access), especially when your input is an array. It does not provide special handling for generator return values.

**Performance Notes:**
- Highly optimized for arrays using `array_map()` internally
- Faster than `map()` for simple transformations on arrays
- No special generator handling - treats generator returns as regular values

**Why `cast()` Exists:**
`cast()` was created to address specific needs that `map()` cannot handle due to its generator-expanding behavior:
1. When you need to transform values into generator objects without expansion
2. When you want guaranteed 1-to-1 transformation without surprises
3. When working with arrays and want the performance benefit of `array_map()`

**Comparison with `map()`:**
- `cast()`: Array-optimized, always 1-to-1, no generator expansion
- `map()`: More flexible, expands generators by yielding from them, can transform one element to many

Both have predictable behavior - the key difference is that `map()` treats generator returns as a source of values to yield, while `cast()` treats them as regular return values.

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
```

**Flattening Deeply Nested Structures**

Since `flatten()` only reduces one level of nesting at a time, you may need to call it multiple times for deeper structures.

```php
$deeplyNested = [[[1]], [[2, 3]], [[4]]];

$partiallyFlat = take($deeplyNested)
    ->flatten()
    ->toList();
// Result: [[1], [2, 3], [4]]

$fullyFlat = take($deeplyNested)
    ->flatten() // First level
    ->flatten() // Second level
    ->toList();
// Result: [1, 2, 3, 4]

// For unknown depth, you can use a loop
$veryDeep = [[[[[[1]]]]], [[[2]]], [[3, [4]]]];
$pipeline = take($veryDeep);

// Flatten until no more nesting (be careful with infinite structures!)
while (true) {
    $before = $pipeline->toList();
    $pipeline = take($before)->flatten();
    $after = $pipeline->toList();
    
    // If nothing changed, we're done
    if ($before === $after) {
        break;
    }
}
// Result: [1, 2, 3, 4]
```

For structures with unknown or variable depth, see the [Recursive Flattening](../advanced/complex-pipelines.md#hierarchical-data-processing) pattern in the Complex Pipelines section for a more elegant recursive solution.

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