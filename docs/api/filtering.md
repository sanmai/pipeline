# Filtering Methods

Methods for removing unwanted elements from the pipeline. All filtering methods return `$this` for method chaining.

## Core Filtering

### `filter(?callable $func = null, bool $strict = false)`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Filtering |
> | **Terminal?** | No |
> | **When to Use** | To remove unwanted elements based on any condition |
> | **Key Behavior** | Without callback, removes falsy values (configurable with `strict`) |

Filters elements based on a predicate function. Without a callback, removes falsy values.

**Parameters:**
- `$func` (?callable): Predicate function returning boolean. If null, filters falsy values.
- `$strict` (bool): When true with null callback, only removes `null` and `false`

**Returns:** $this (Pipeline\Standard instance)

**Callback signature:** `function(mixed $value): bool`

**When to Use:**
Use `filter()` to remove unwanted elements. Without a callback, it's perfect for cleaning data by removing empty/null values. With a callback, it's your general-purpose filtering tool.

**Performance Notes:**
- Uses native `array_filter()` when input is an array (highly optimized)
- Uses `CallbackFilterIterator` for iterators (memory efficient)
- Preserves keys during filtering

**Strict Mode Benefits:**

> **Best Practice: Use Strict Mode for Predictable Cleaning**
> 
> For maximum clarity and to avoid accidental data loss (like removing `0` or empty strings), it is highly recommended to use the strict mode when your goal is to clean out only `null` and `false` values.
> 
> - `->filter(null, strict: true)`: **(Recommended for cleaning)** Only removes `null` and `false`
> - `->filter()`: **(Use with caution)** Removes all [falsy values](https://www.php.net/manual/en/language.types.boolean.php#language.types.boolean.casting), which includes `0`, `''`, `[]`, and `'0'`

See the [Safe and Predictable Data Cleaning](../cookbook/index.md#safe-and-predictable-data-cleaning) recipe for a detailed example.

**Examples:**

```php
// Custom predicate
$result = take([1, 2, 3, 4, 5, 6])
    ->filter(fn($x) => $x % 2 === 0)
    ->toList();
// Result: [2, 4, 6]

// Default filter (removes falsy values)
$result = take([0, 1, false, 2, null, 3, '', 4, []])
    ->filter()
    ->toList();
// Result: [1, 2, 3, 4]

// Strict mode (only null and false)
$result = take([0, 1, false, 2, null, 3, '', 4, []])
    ->filter(null, strict: true)
    ->toList();
// Result: [0, 1, 2, 3, '', 4, []]

// Filter with string function
$result = take(['', 'hello', ' ', 'world', '  '])
    ->filter('trim')  // Uses trim as predicate
    ->toList();
// Result: ['hello', 'world']

// Complex filtering
$users = [
    ['name' => 'Alice', 'age' => 30, 'active' => true],
    ['name' => 'Bob', 'age' => 17, 'active' => true],
    ['name' => 'Charlie', 'age' => 25, 'active' => false],
];
$result = take($users)
    ->filter(fn($user) => $user['age'] >= 18 && $user['active'])
    ->toList();
// Result: [['name' => 'Alice', 'age' => 30, 'active' => true]]

// Chained filters
$result = take(range(1, 20))
    ->filter(fn($x) => $x % 2 === 0)  // Even numbers
    ->filter(fn($x) => $x % 3 === 0)  // Divisible by 3
    ->toList();
// Result: [6, 12, 18]

// Filter preserves keys
$data = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];
$result = take($data)
    ->filter(fn($x) => $x > 2)
    ->toAssoc();
// Result: ['c' => 3, 'd' => 4]
```

### `skipWhile(callable $predicate)`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Filtering |
> | **Terminal?** | No |
> | **When to Use** | To skip header/prefix elements until a condition is met |
> | **Key Behavior** | Stops checking after first false, keeps all remaining elements |

Skips elements from the beginning while the predicate returns true. Once false is returned, all remaining elements are kept.

**Parameters:**
- `$predicate` (callable): Function returning boolean

**Returns:** $this (Pipeline\Standard instance)

**Callback signature:** `function(mixed $value): bool`

**Examples:**

```php
// Skip leading zeros
$result = take([0, 0, 1, 0, 2, 0, 3])
    ->skipWhile(fn($x) => $x === 0)
    ->toList();
// Result: [1, 0, 2, 0, 3]

// Skip until threshold
$result = take([1, 2, 3, 4, 5, 4, 3, 2, 1])
    ->skipWhile(fn($x) => $x < 4)
    ->toList();
// Result: [4, 5, 4, 3, 2, 1]

// Skip headers in file
$data = take([
    '# Header 1',
    '# Header 2',
    'Actual data',
    '# Not a header',
    'More data'
])
->skipWhile(fn($line) => str_starts_with($line, '#'))
->toList();
// Result: ['Actual data', '# Not a header', 'More data']

// Process log after timestamp
$logs = [
    '[2024-01-01] Starting',
    '[2024-01-02] Warming up',
    '[2024-01-03] Ready',
    '[2024-01-04] Processing',
];
$result = take($logs)
    ->skipWhile(fn($log) => !str_contains($log, 'Ready'))
    ->toList();
// Result: ['[2024-01-03] Ready', '[2024-01-04] Processing']

// Combined with other operations
$result = take(range(1, 10))
    ->skipWhile(fn($x) => $x < 5)
    ->filter(fn($x) => $x % 2 === 0)
    ->toList();
// Result: [6, 8, 10]
```

## Filtering Patterns

### Removing Duplicates

```php
// Using flip() to deduplicate
$result = take([1, 2, 2, 3, 3, 3, 4])
    ->flip()  // Values become keys (deduplicates)
    ->flip()  // Flip back
    ->values()  // Reset numeric keys
    ->toList();
// Result: [1, 2, 3, 4]

// Deduplicate with custom key
$users = [
    ['id' => 1, 'name' => 'Alice'],
    ['id' => 2, 'name' => 'Bob'],
    ['id' => 1, 'name' => 'Alice'],  // Duplicate
];
$unique = take($users)
    ->fold([], function($carry, $user) {
        $carry[$user['id']] = $user;
        return $carry;
    });
// Result: [1 => ['id' => 1, 'name' => 'Alice'], 2 => ['id' => 2, 'name' => 'Bob']]
```

### Filtering by Type

```php
// Keep only strings
$result = take([1, 'hello', 2.5, 'world', true, null])
    ->filter('is_string')
    ->toList();
// Result: ['hello', 'world']

// Keep only numbers
$result = take(['1', 2, '3.5', 4.5, 'text'])
    ->filter('is_numeric')
    ->toList();
// Result: ['1', 2, '3.5', 4.5]

// Keep only specific class instances
$objects = [
    new DateTime(),
    new stdClass(),
    new DateTime(),
    'not an object'
];
$dates = take($objects)
    ->filter(fn($x) => $x instanceof DateTime)
    ->toList();
// Result: [DateTime, DateTime]
```

### Conditional Filtering

```php
// Filter based on external condition
$minAge = 18;
$includeInactive = false;

$result = take($users)
    ->filter(fn($user) => $user['age'] >= $minAge)
    ->filter(fn($user) => $includeInactive || $user['active'])
    ->toList();

// Dynamic filter construction
function buildFilter($criteria) {
    return function($item) use ($criteria) {
        foreach ($criteria as $key => $value) {
            if (!isset($item[$key]) || $item[$key] !== $value) {
                return false;
            }
        }
        return true;
    };
}

$result = take($products)
    ->filter(buildFilter(['category' => 'electronics', 'inStock' => true]))
    ->toList();
```

### Range Filtering

```php
// Filter within range
$result = take([5, 10, 15, 20, 25, 30])
    ->filter(fn($x) => $x >= 10 && $x <= 25)
    ->toList();
// Result: [10, 15, 20, 25]

// Filter with tolerance
$target = 100;
$tolerance = 10;
$result = take([85, 95, 100, 105, 115])
    ->filter(fn($x) => abs($x - $target) <= $tolerance)
    ->toList();
// Result: [95, 100, 105]
```

### Text Filtering

```php
// Filter by pattern
$emails = [
    'user@example.com',
    'invalid-email',
    'admin@company.org',
    'test@'
];
$valid = take($emails)
    ->filter(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
    ->toList();
// Result: ['user@example.com', 'admin@company.org']

// Case-insensitive search
$searchTerm = 'error';
$logs = take(new SplFileObject('app.log'))
    ->filter(fn($line) => stripos($line, $searchTerm) !== false)
    ->toList();
```

### Null and Empty Handling

```php
// Remove empty strings but keep zeros
$result = take(['', '0', 0, 'hello', null, false, []])
    ->filter(fn($x) => $x !== '' && $x !== null)
    ->toList();
// Result: ['0', 0, 'hello', false, []]

// Remove empty arrays/strings
$result = take(['', [], 'hello', [1, 2], null, ''])
    ->filter(fn($x) => 
        ($x !== '' && $x !== null && $x !== [])
    )
    ->toList();
// Result: ['hello', [1, 2]]
```

### Performance Considerations

```php
// Early termination with skipWhile
$firstValid = null;
take($largeDataset)
    ->skipWhile(fn($x) => !isValid($x))
    ->slice(0, 1)  // Take only first valid
    ->each(fn($x) => $firstValid = $x);

// Combine filters for efficiency
// Less efficient:
$result = take($data)
    ->filter(fn($x) => $x > 0)
    ->filter(fn($x) => $x < 100)
    ->filter(fn($x) => $x % 2 === 0)
    ->toList();

// More efficient:
$result = take($data)
    ->filter(fn($x) => $x > 0 && $x < 100 && $x % 2 === 0)
    ->toList();
```

## Common Use Cases

### Data Validation

```php
// Validate and clean data
$cleanData = take($rawData)
    ->filter(fn($item) => isset($item['id'], $item['name']))
    ->filter(fn($item) => strlen($item['name']) > 0)
    ->filter(fn($item) => is_numeric($item['id']))
    ->map(fn($item) => [
        'id' => (int)$item['id'],
        'name' => trim($item['name'])
    ])
    ->toList();
```

### Log Processing

```php
// Extract errors from last hour
$oneHourAgo = time() - 3600;
$recentErrors = take(new SplFileObject('app.log'))
    ->filter(fn($line) => preg_match('/\[(\d+)\]/', $line, $m) && $m[1] > $oneHourAgo)
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->toList();
```

## Next Steps

- [Aggregation Methods](aggregation.md) - Methods for reducing to single values
- [Collection Methods](collection.md) - Methods for converting to arrays