# Utility Methods

Helper methods for specialized operations and data manipulation.

## Sampling Methods

### `reservoir(int $size, ?callable $weightFunc = null)`

Performs reservoir sampling to randomly select elements. Implements Algorithm R for uniform sampling or Algorithm A-Chao for weighted sampling.

**Parameters:**
- `$size` (int): Number of elements to sample
- `$weightFunc` (?callable): Optional function to calculate element weights

**Returns:** array - Array containing sampled elements

**Examples:**

```php
// Uniform random sampling
$sample = take(range(1, 1000))
    ->reservoir(10);
// Result: 10 random numbers from 1-1000

// Sample from large file
$randomLines = take(new SplFileObject('large-file.txt'))
    ->reservoir(100);
// Efficiently samples 100 lines without loading entire file

// Weighted sampling
$items = [
    ['id' => 1, 'weight' => 1.0],
    ['id' => 2, 'weight' => 2.0],  // 2x more likely
    ['id' => 3, 'weight' => 3.0],  // 3x more likely
    ['id' => 4, 'weight' => 0.5],  // Half as likely
];
$sample = take($items)
    ->reservoir(2, fn($item) => $item['weight']);

// Priority sampling
$tasks = take($taskQueue)
    ->reservoir(5, fn($task) => $task['priority']);

// Sample with filtering
$sample = take(range(1, 100))
    ->filter(fn($x) => $x % 2 === 0)  // Even numbers only
    ->reservoir(5);

// Empty or small datasets
$sample = take([1, 2, 3])->reservoir(10);
// Result: [1, 2, 3] (all elements when size > count)
```

## Combining Methods

### `zip(iterable ...$inputs)`

Combines multiple iterables element by element (transposition).

**Parameters:**
- `...$inputs` (iterable): Additional iterables to combine with

**Returns:** $this (Pipeline\Standard instance)

**Examples:**

```php
// Basic zip
$result = take(['a', 'b', 'c'])
    ->zip([1, 2, 3])
    ->toList();
// Result: [['a', 1], ['b', 2], ['c', 3]]

// Multiple iterables
$result = take(['red', 'green', 'blue'])
    ->zip([255, 0, 0], [0, 255, 0], [0, 0, 255])
    ->toList();
// Result: [['red', 255, 0, 0], ['green', 0, 255, 0], ['blue', 0, 0, 255]]

// Uneven lengths (shorter filled with null)
$result = take([1, 2, 3, 4, 5])
    ->zip(['a', 'b', 'c'])
    ->toList();
// Result: [[1, 'a'], [2, 'b'], [3, 'c'], [4, null], [5, null]]

// Create records from parallel arrays
$names = ['Alice', 'Bob', 'Charlie'];
$ages = [30, 25, 35];
$cities = ['NYC', 'LA', 'Chicago'];

$people = take($names)
    ->zip($ages, $cities)
    ->map(fn($data) => [
        'name' => $data[0],
        'age' => $data[1],
        'city' => $data[2]
    ])
    ->toList();

// Enumerate pattern
$indexed = take(['apple', 'banana', 'cherry'])
    ->zip(range(1, PHP_INT_MAX))
    ->map(fn($pair) => ['index' => $pair[1], 'value' => $pair[0]])
    ->toList();
```

## Running Operations

### `runningCount(?int &$count)`

Counts elements as they pass through the pipeline without consuming it.

**Parameters:**
- `&$count` (?int): Reference to counter variable (initialized to 0 if null)

**Returns:** $this (Pipeline\Standard instance)

**Examples:**

```php
// Count while processing
$count = null;
$result = take(range(1, 100))
    ->filter(fn($x) => $x % 2 === 0)
    ->runningCount($count)
    ->map(fn($x) => $x * 2)
    ->slice(0, 10)
    ->toList();
echo "Processed $count even numbers"; // 10

// Multiple counters
$total = null;
$filtered = null;
$result = take($items)
    ->runningCount($total)
    ->filter($predicate)
    ->runningCount($filtered)
    ->toList();
echo "Filtered $filtered out of $total items";

// Count with side effects
$errorCount = 0;
take($logs)
    ->filter(fn($log) => str_contains($log, 'ERROR'))
    ->runningCount($errorCount)
    ->each(fn($log) => processError($log));
echo "Found $errorCount errors";

// Progress tracking
$processed = 0;
take($largeDataset)
    ->runningCount($processed)
    ->each(function($item) use (&$processed) {
        if ($processed % 1000 === 0) {
            echo "Processed $processed items...\n";
        }
        processItem($item);
    });
```

### `runningVariance(?RunningVariance &$variance, ?callable $castFunc = null)`

Calculates running statistics as elements pass through.

**Parameters:**
- `&$variance` (?RunningVariance): Reference to variance calculator (initialized if null)
- `$castFunc` (?callable): Function to convert values to float (default: floatval)

**Returns:** $this (Pipeline\Standard instance)

**Examples:**

```php
use Pipeline\Helper\RunningVariance;

// Track statistics while processing
$stats = null;
$processed = take($measurements)
    ->runningVariance($stats)
    ->filter(fn($x) => $x > 0)
    ->map(fn($x) => round($x, 2))
    ->toList();

echo "Mean: " . $stats->getMean() . "\n";
echo "StdDev: " . $stats->getStandardDeviation() . "\n";
echo "Count: " . $stats->getCount() . "\n";

// Custom value extraction
$variance = null;
$results = take($orders)
    ->runningVariance($variance, fn($order) => $order['total'])
    ->filter(fn($order) => $order['status'] === 'completed')
    ->toList();

echo "Average order value: $" . $variance->getMean();

// Selective statistics
$stats = null;
take($data)
    ->runningVariance($stats, function($value) {
        // Only include valid measurements
        return is_numeric($value) && $value > 0 ? (float)$value : null;
    })
    ->each(fn($x) => processValue($x));

// Monitor data stream
$variance = new RunningVariance();
take($sensorReadings)
    ->runningVariance($variance)
    ->each(function($reading) use ($variance) {
        if ($variance->getCount() > 10) {
            $mean = $variance->getMean();
            $stdDev = $variance->getStandardDeviation();
            if (abs($reading - $mean) > 3 * $stdDev) {
                alertAnomaly($reading);
            }
        }
    });
```

## Special Operations

### `pipe(callable $operation)`

Applies a complex, multi-step operation to the entire pipeline instance. This is useful for grouping reusable sets of pipeline transformations into a single callable.

**Parameters:**
- `$operation` (callable): A function that receives the pipeline instance as its only argument and must return a pipeline instance.

**Returns:** $this (Pipeline\Standard instance)

**Callback signature:** `function(Pipeline\Standard $pipeline): Pipeline\Standard`

**When to Use:**
Use `pipe()` when you want to:
- Apply a pre-defined set of transformations
- Create reusable pipeline components
- Compose complex operations from simpler ones
- Keep your main pipeline chain readable

**Examples:**

```php
// Define reusable pipeline operations
$normalizeText = function($pipeline) {
    return $pipeline
        ->map('trim')
        ->filter()  // Remove empty strings
        ->map('strtolower');
};

$extractEmails = function($pipeline) {
    return $pipeline
        ->map(function($text) {
            preg_match_all('/[\w\.-]+@[\w\.-]+\.\w+/', $text, $matches);
            yield from $matches[0];
        })
        ->filter(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL));
};

// Apply operations using pipe()
$emails = take($textLines)
    ->pipe($normalizeText)
    ->pipe($extractEmails)
    ->toList();

// Compose multiple operations
$processUsers = function($pipeline) {
    return $pipeline
        ->filter(fn($user) => $user['active'] ?? false)
        ->map(fn($user) => [
            'id' => $user['id'],
            'name' => ucfirst(strtolower($user['name'])),
            'email' => strtolower($user['email'])
        ])
        ->filter(fn($user) => filter_var($user['email'], FILTER_VALIDATE_EMAIL));
};

$validUsers = take($rawUsers)
    ->pipe($processUsers)
    ->toList();

// Pipeline factory pattern
class PipelineTransformers {
    public static function sanitizeHtml(): callable {
        return fn($pipeline) => $pipeline
            ->map('strip_tags')
            ->map('html_entity_decode')
            ->map('trim')
            ->filter();
    }
    
    public static function parseNumbers(): callable {
        return fn($pipeline) => $pipeline
            ->map(fn($text) => preg_replace('/[^0-9.-]/', '', $text))
            ->filter('is_numeric')
            ->map('floatval');
    }
}

// Use with factory methods
$numbers = take($htmlContent)
    ->pipe(PipelineTransformers::sanitizeHtml())
    ->pipe(PipelineTransformers::parseNumbers())
    ->toList();

// Conditional pipeline composition
$processData = function($pipeline) use ($config) {
    if ($config['normalize']) {
        $pipeline = $pipeline->pipe($normalizeText);
    }
    if ($config['validate']) {
        $pipeline = $pipeline->filter($validator);
    }
    if ($config['transform']) {
        $pipeline = $pipeline->map($transformer);
    }
    return $pipeline;
};

$result = take($data)
    ->pipe($processData)
    ->toList();
```

### `skipWhile(callable $predicate)`

Skips elements from the beginning while predicate is true.

See [Filtering Methods](filtering.md#skipwhile) for details.

### `stream()`

Forces lazy evaluation using generators.

See [Collection Methods](collection.md#stream) for details.

## Utility Patterns

### Sampling Strategies

```php
// Stratified sampling
function stratifiedSample($data, $strata, $samplesPerStratum) {
    $result = [];
    foreach ($strata as $stratum) {
        $stratumData = take($data)
            ->filter(fn($item) => $item['category'] === $stratum)
            ->reservoir($samplesPerStratum);
        $result = array_merge($result, $stratumData);
    }
    return $result;
}

// Time-based sampling
$recentSample = take($events)
    ->filter(fn($e) => $e['timestamp'] > time() - 3600)
    ->reservoir(100, fn($e) => 1 / (time() - $e['timestamp']));
// More recent events have higher weight
```

### Progress Monitoring

```php
// With percentage
$total = count($items);
$processed = 0;
take($items)
    ->runningCount($processed)
    ->each(function($item) use ($total, &$processed) {
        $percent = round(($processed / $total) * 100);
        echo "\rProgress: $percent%";
        processItem($item);
    });
echo "\nComplete!\n";

// With rate calculation
$startTime = microtime(true);
$count = 0;
take($records)
    ->runningCount($count)
    ->each(function($record) use ($startTime, &$count) {
        if ($count % 1000 === 0 && $count > 0) {
            $elapsed = microtime(true) - $startTime;
            $rate = $count / $elapsed;
            echo "Processing rate: " . round($rate) . " records/sec\n";
        }
        processRecord($record);
    });
```

### Data Quality Monitoring

```php
// Monitor data distribution
$stats = null;
$outliers = [];
$processed = take($dataPoints)
    ->runningVariance($stats)
    ->map(function($point) use ($stats, &$outliers) {
        if ($stats->getCount() > 30) {  // Need enough data
            $mean = $stats->getMean();
            $stdDev = $stats->getStandardDeviation();
            if (abs($point - $mean) > 2 * $stdDev) {
                $outliers[] = $point;
            }
        }
        return $point;
    })
    ->toList();

echo "Found " . count($outliers) . " outliers\n";
echo "Data range: " . $stats->getMin() . " to " . $stats->getMax() . "\n";
```

### Combining Data Sources

```php
// Merge sorted streams
function mergeSorted($stream1, $stream2) {
    return take($stream1)
        ->zip($stream2)
        ->map(function($pair) {
            [$a, $b] = $pair;
            if ($a === null) return $b;
            if ($b === null) return $a;
            return $a <= $b ? $a : $b;
        })
        ->filter()  // Remove nulls
        ->toList();
}

// Parallel processing results
$results = take($urls)
    ->zip(
        take($urls)->map(fn($url) => fetchData($url)),
        take($urls)->map(fn($url) => fetchMetadata($url))
    )
    ->map(fn($data) => [
        'url' => $data[0],
        'content' => $data[1],
        'metadata' => $data[2]
    ])
    ->toList();
```

## Next Steps

- [Helper Functions](helpers.md) - Top-level helper functions
- [Statistics](statistics.md) - Statistical analysis methods