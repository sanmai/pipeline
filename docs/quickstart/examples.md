# Examples

## Data Processing Examples

### CSV Processing Pipeline

```php
use function Pipeline\take;

// Process CSV with header row
$csv = <<<CSV
name,age,city
Alice,30,New York
Bob,25,Los Angeles
Charlie,35,Chicago
CSV;

$users = take(explode("\n", $csv))
    ->map('str_getcsv')
    ->slice(1)  // Skip header
    ->map(fn($row) => [
        'name' => $row[0],
        'age' => (int)$row[1],
        'city' => $row[2]
    ])
    ->filter(fn($user) => $user['age'] >= 30)
    ->toList();

// Result: [
//   ['name' => 'Alice', 'age' => 30, 'city' => 'New York'],
//   ['name' => 'Charlie', 'age' => 35, 'city' => 'Chicago']
// ]
```

### JSON Lines Processing

```php
// Process JSONL file (JSON Lines format)
$stats = take(new SplFileObject('events.jsonl'))
    ->map('json_decode')
    ->filter()  // Remove invalid JSON lines
    ->map(fn($event) => $event->type ?? 'unknown')
    ->reduce(function($counts, $type) {
        $counts[$type] = ($counts[$type] ?? 0) + 1;
        return $counts;
    }, []);

// Result: ['click' => 150, 'view' => 500, 'purchase' => 23]
```

### Log Analysis

```php
// Analyze Apache access logs
$topIPs = take(new SplFileObject('access.log'))
    ->map(fn($line) => explode(' ', $line)[0])  // Extract IP
    ->filter(fn($ip) => filter_var($ip, FILTER_VALIDATE_IP))
    ->reduce(function($counts, $ip) {
        $counts[$ip] = ($counts[$ip] ?? 0) + 1;
        return $counts;
    }, []);

// Sort by count and take top 10
$top10 = take($topIPs)
    ->flip()  // Make counts as keys
    ->keys()  // Get just the counts
    ->toList();
rsort($top10);
$top10 = array_slice($top10, 0, 10);
```

## Generator Examples

### Infinite Sequences

```php
use function Pipeline\map;

// Fibonacci generator
$fibonacci = map(function() {
    $a = 0;
    $b = 1;
    while (true) {
        yield $a;
        [$a, $b] = [$b, $a + $b];
    }
});

// Take first 10 Fibonacci numbers
$first10 = $fibonacci->slice(0, 10)->toList();
// Result: [0, 1, 1, 2, 3, 5, 8, 13, 21, 34]

// Find Fibonacci numbers less than 1000
$lessThan1000 = map(function() {
    $a = 0;
    $b = 1;
    while ($a < 1000) {
        yield $a;
        [$a, $b] = [$b, $a + $b];
    }
})->toList();
```

### Reading Large Files Efficiently

```php
// Process multi-GB file without loading into memory
$processedLines = 0;
take(new SplFileObject('huge-file.txt'))
    ->filter(fn($line) => strlen($line) > 0)
    ->chunk(1000)  // Process in batches
    ->each(function($batch) use (&$processedLines) {
        // Process batch (e.g., insert into database)
        $processedLines += count($batch);
        echo "Processed $processedLines lines\n";
    });
```

## Transformation Examples

### Flattening Nested Structures

```php
// Flatten nested arrays
$nested = [
    ['A', 'B'],
    ['C', 'D', 'E'],
    ['F']
];

$flat = take($nested)->flatten()->toList();
// Result: ['A', 'B', 'C', 'D', 'E', 'F']

// Flatten and deduplicate
$result = take([
    [1, 2, 3],
    [3, 4, 5],
    [5, 6, 7]
])
->flatten()
->flip()  // Use values as keys (deduplicates)
->flip()  // Flip back
->values()  // Reset keys
->toList();
// Result: [1, 2, 3, 4, 5, 6, 7]
```

### Unpacking Arguments

```php
// Apply function with array elements as arguments
$coordinates = [
    [10, 20],
    [30, 40],
    [50, 60]
];

$distances = take($coordinates)
    ->unpack(fn($x, $y) => sqrt($x**2 + $y**2))
    ->map(fn($d) => round($d, 2))
    ->toList();
// Result: [22.36, 50.0, 78.1]
```

### Complex Object Transformation

```php
// Transform and aggregate objects
class Order {
    public function __construct(
        public int $id,
        public string $customer,
        public array $items,
        public string $status
    ) {}
}

$orders = [
    new Order(1, 'Alice', [['price' => 10], ['price' => 20]], 'completed'),
    new Order(2, 'Bob', [['price' => 15]], 'pending'),
    new Order(3, 'Alice', [['price' => 25], ['price' => 30]], 'completed'),
];

$customerTotals = take($orders)
    ->filter(fn($order) => $order->status === 'completed')
    ->map(fn($order) => [
        'customer' => $order->customer,
        'total' => take($order->items)
            ->map(fn($item) => $item['price'])
            ->reduce()
    ])
    ->reduce(function($totals, $order) {
        $customer = $order['customer'];
        $totals[$customer] = ($totals[$customer] ?? 0) + $order['total'];
        return $totals;
    }, []);
// Result: ['Alice' => 85]
```

## Statistical Examples

### Running Statistics

```php
use Pipeline\Helper\RunningVariance;

// Calculate statistics while processing
$variance = null;
$stats = take([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
    ->runningVariance($variance)
    ->map(fn($x) => $x * 2)  // Transform after observing
    ->toList();

echo "Count: " . $variance->getCount() . "\n";        // 10
echo "Mean: " . $variance->getMean() . "\n";          // 5.5
echo "StdDev: " . $variance->getStandardDeviation() . "\n"; // ~3.03
echo "Min: " . $variance->getMin() . "\n";            // 1
echo "Max: " . $variance->getMax() . "\n";            // 10
```

### Weighted Sampling

```php
// Reservoir sampling with weights
$items = [
    ['id' => 1, 'weight' => 1.0],
    ['id' => 2, 'weight' => 2.0],
    ['id' => 3, 'weight' => 3.0],
    ['id' => 4, 'weight' => 0.5],
    ['id' => 5, 'weight' => 1.5],
];

// Sample 3 items with probability proportional to weight
$sample = take($items)->reservoir(
    3,
    fn($item) => $item['weight']
);
// Result varies but higher weight items appear more often
```

## Chunking and Batching

### Batch Processing

```php
// Process data in batches
$batchResults = take(range(1, 100))
    ->chunk(10)
    ->map(function($batch) {
        // Process batch (e.g., API call)
        return [
            'batch_size' => count($batch),
            'sum' => array_sum($batch),
            'avg' => array_sum($batch) / count($batch)
        ];
    })
    ->toList();
// Result: 10 arrays with batch statistics
```

### Sliding Window

```php
// Calculate moving average
$data = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
$windowSize = 3;

$movingAvg = take($data)
    ->tuples()  // Convert to [index, value] pairs
    ->map(function($tuple) use ($data, $windowSize) {
        [$index, $value] = $tuple;
        $window = array_slice($data, max(0, $index - $windowSize + 1), $windowSize);
        return array_sum($window) / count($window);
    })
    ->toList();
// Result: [10, 15, 20, 30, 40, 50, 60, 70, 80, 90]
```

## Zip Operations

### Combining Multiple Sources

```php
use function Pipeline\zip;

// Combine parallel arrays
$names = ['Alice', 'Bob', 'Charlie'];
$ages = [30, 25, 35];
$cities = ['NYC', 'LA', 'Chicago'];

$people = zip($names, $ages, $cities)
    ->map(fn($data) => [
        'name' => $data[0],
        'age' => $data[1],
        'city' => $data[2]
    ])
    ->toList();
// Result: [
//   ['name' => 'Alice', 'age' => 30, 'city' => 'NYC'],
//   ['name' => 'Bob', 'age' => 25, 'city' => 'LA'],
//   ['name' => 'Charlie', 'age' => 35, 'city' => 'Chicago']
// ]
```

### Enumeration Pattern

```php
// Add index to values
$indexed = zip(
    range(0, PHP_INT_MAX),  // Indices
    ['apple', 'banana', 'cherry']  // Values
)->toList();
// Result: [[0, 'apple'], [1, 'banana'], [2, 'cherry']]
```

## Error Handling Patterns

### Safe Navigation

```php
// Handle potentially missing data
$data = [
    ['user' => ['name' => 'Alice', 'email' => 'alice@example.com']],
    ['user' => ['name' => 'Bob']],  // Missing email
    ['user' => null],  // Missing user
    [],  // Missing everything
];

$emails = take($data)
    ->map(fn($item) => $item['user']['email'] ?? null)
    ->filter()  // Remove nulls
    ->toList();
// Result: ['alice@example.com']
```

### Validation Pipeline

```php
// Multi-step validation
$validatedData = take($rawData)
    ->filter(fn($item) => is_array($item))
    ->filter(fn($item) => isset($item['id']))
    ->map(function($item) {
        // Normalize
        $item['status'] = $item['status'] ?? 'pending';
        $item['created_at'] = $item['created_at'] ?? time();
        return $item;
    })
    ->filter(fn($item) => in_array($item['status'], ['pending', 'active', 'completed']))
    ->toList();
```

## Performance Patterns

### Early Termination

```php
// Find first matching element efficiently
$found = false;
$result = null;

take(new SplFileObject('large-file.txt'))
    ->filter(fn($line) => !$found)  // Stop processing after found
    ->each(function($line) use (&$found, &$result) {
        if (str_contains($line, 'search-term')) {
            $found = true;
            $result = $line;
        }
    });
```

### Memory-Efficient Aggregation

```php
// Count occurrences without storing all data
$counts = [];
take(new InfiniteIterator(new ArrayIterator(['A', 'B', 'C'])))
    ->slice(0, 1000000)  // Process 1M items
    ->each(function($item) use (&$counts) {
        $counts[$item] = ($counts[$item] ?? 0) + 1;
    });
// Result: ['A' => 333334, 'B' => 333333, 'C' => 333333]
```

## Next Steps

- [API Reference](../api/creation.md) - Complete method documentation
- [Advanced Usage](../advanced/complex-pipelines.md) - More complex patterns