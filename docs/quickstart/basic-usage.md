# Basic Usage

## Creating Pipelines

### From Arrays

```php
use Pipeline\Standard;
use function Pipeline\take;
use function Pipeline\fromArray;

// Using constructor
$pipeline = new Standard([1, 2, 3, 4, 5]);

// Using take() function
$pipeline = take([1, 2, 3, 4, 5]);

// Using fromArray() function
$pipeline = fromArray([1, 2, 3, 4, 5]);
```

### From Iterables

```php
// From generator
function generateNumbers() {
    for ($i = 1; $i <= 5; $i++) {
        yield $i;
    }
}
$pipeline = take(generateNumbers());

// From Iterator
$iterator = new ArrayIterator([1, 2, 3, 4, 5]);
$pipeline = take($iterator);

// From file
$file = new SplFileObject('data.txt');
$pipeline = take($file);
```

### From Values

```php
use function Pipeline\fromValues;

// Create pipeline from individual values
$pipeline = fromValues(1, 2, 3, 4, 5);

// Equivalent to
$pipeline = take([1, 2, 3, 4, 5]);
```

### Empty Pipeline

```php
// Create empty pipeline
$pipeline = new Standard();

// Add data later
$pipeline->append([1, 2, 3]);
```

## Basic Transformations

### Map - Transform Each Element

```php
// Double each number
$result = take([1, 2, 3, 4, 5])
    ->map(fn($x) => $x * 2)
    ->toList();
// Result: [2, 4, 6, 8, 10]

// Extract property
$users = [
    ['name' => 'Alice', 'age' => 30],
    ['name' => 'Bob', 'age' => 25],
];
$names = take($users)
    ->map(fn($user) => $user['name'])
    ->toList();
// Result: ['Alice', 'Bob']
```

### Filter - Remove Elements

```php
// Keep only even numbers
$result = take([1, 2, 3, 4, 5, 6])
    ->filter(fn($x) => $x % 2 === 0)
    ->toList();
// Result: [2, 4, 6]

// Filter with default behavior (removes falsy values)
$result = take([0, 1, false, 2, null, 3, '', 4])
    ->filter()
    ->toList();
// Result: [1, 2, 3, 4]

// Strict mode - only removes null and false
$result = take([0, 1, false, 2, null, 3, '', 4])
    ->filter(strict: true)
    ->toList();
// Result: [0, 1, 2, 3, '', 4]
```

### Cast - Simple Value Transformation

```php
// Convert to integers
$result = take(['1', '2', '3'])
    ->cast('intval')
    ->toList();
// Result: [1, 2, 3]

// Convert to uppercase
$result = take(['hello', 'world'])
    ->cast('strtoupper')
    ->toList();
// Result: ['HELLO', 'WORLD']
```

## Combining Data Sources

### Append - Add to End

```php
$result = take([1, 2, 3])
    ->append([4, 5, 6])
    ->toList();
// Result: [1, 2, 3, 4, 5, 6]

// Using push() for individual values
$result = take([1, 2, 3])
    ->push(4, 5, 6)
    ->toList();
// Result: [1, 2, 3, 4, 5, 6]
```

### Prepend - Add to Beginning

```php
$result = take([4, 5, 6])
    ->prepend([1, 2, 3])
    ->toList();
// Result: [1, 2, 3, 4, 5, 6]

// Using unshift() for individual values
$result = take([4, 5, 6])
    ->unshift(1, 2, 3)
    ->toList();
// Result: [1, 2, 3, 4, 5, 6]
```

## Retrieving Results

### As List (Indexed Array)

```php
// Get all values with numeric keys
$result = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->map(fn($x) => $x * 2)
    ->toList();
// Result: [2, 4, 6]
```

### As Associative Array

```php
// Preserve keys
$result = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->map(fn($x) => $x * 2)
    ->toAssoc();
// Result: ['a' => 2, 'b' => 4, 'c' => 6]
```

### Reduce to Single Value

```php
// Sum all values (default reducer)
$sum = take([1, 2, 3, 4, 5])->reduce();
// Result: 15

// Custom reducer
$product = take([1, 2, 3, 4, 5])
    ->fold(1, fn($carry, $item) => $carry * $item);
// Result: 120

// String concatenation
$string = take(['Hello', ' ', 'World'])
    ->fold('', fn($carry, $item) => $carry . $item);
// Result: 'Hello World'
```

## Common Patterns

### Processing Collections

```php
// Extract and transform data
$orders = [
    ['id' => 1, 'total' => 100, 'status' => 'paid'],
    ['id' => 2, 'total' => 200, 'status' => 'pending'],
    ['id' => 3, 'total' => 150, 'status' => 'paid'],
];

$paidTotal = take($orders)
    ->filter(fn($order) => $order['status'] === 'paid')
    ->map(fn($order) => $order['total'])
    ->reduce();
// Result: 250
```

### Working with Files

```php
// Count lines containing specific text
$errorCount = take(new SplFileObject('app.log'))
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->count();

// Process CSV file
$data = take(new SplFileObject('data.csv'))  // Starts with a lazy iterator
    ->map('str_getcsv')
    ->filter(fn($row) => count($row) === 3)  // Valid rows only
    ->toList();  // toList() "pulls" each line through the chain to produce the final array
```

### Pagination Pattern

```php
// Get page of results
function getPage(iterable $data, $page, $perPage = 10) {
    $offset = ($page - 1) * $perPage;
    return take($data)
        ->slice($offset, $perPage)
        ->toList();
}

$page2 = getPage(range(1, 100), 2, 10);
// Result: [11, 12, 13, 14, 15, 16, 17, 18, 19, 20]
```

## Method Chaining

```php
// Complex transformation pipeline
$result = take(range(1, 20))
    ->filter(fn($x) => $x % 2 === 0)      // Even numbers
    ->map(fn($x) => $x ** 2)               // Square them
    ->filter(fn($x) => $x < 100)           // Less than 100
    ->map(fn($x) => ['value' => $x])       // Wrap in array
    ->toList();
// Result: [['value' => 4], ['value' => 16], ['value' => 36], ['value' => 64]]
```

## Error Prevention

```php
// Safe processing with defaults
$result = take($possiblyNullData)
    ->filter()                    // Remove any null/false values
    ->map(fn($x) => $x['field'] ?? 'default')
    ->slice(0, 10)               // Limit results
    ->toList();

// Empty pipeline returns empty array
$result = take([])->map(fn($x) => $x * 2)->toList();
// Result: []
```

## Next Steps

- [Examples](examples.md) - More complex examples
- [API Reference](../api/creation.md) - Complete method documentation
