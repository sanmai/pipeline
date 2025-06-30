# Best Practices

Guidelines and recommendations for using the Pipeline library effectively.

## General Principles

### 1. Think in Streams, Not Arrays (The Golden Rule)

This library is fundamentally designed for **streaming, lazy data processing**. Its core strength is handling large datasets with minimal memory.

**Always prefer iterators and generators (`new SplFileObject(...)`, `function() { yield ... }`) as your starting data source.**

The internal array optimizations are a secondary convenience for small-scale tasks. Do not rely on them for serious data processing. To write robust, memory-safe, and scalable code with this library, always think in terms of streams. If you must start with a large array, immediately use `->stream()` to switch to the recommended lazy processing model.

```php
// EXCELLENT: Start with a streaming source
$result = take(new SplFileObject('users.csv'))
    ->map('str_getcsv')
    ->filter(fn($row) => $row[2] === 'active')
    ->map(fn($row) => processUser($row))
    ->toList();

// GOOD: Convert array to stream for safety
$result = take($millionUsers)
    ->stream()  // Convert to generator immediately
    ->filter(fn($user) => $user['active'])
    ->map(fn($user) => enrichUser($user))
    ->toList();

// RISKY: Relying on array optimizations
$result = take($millionUsers)
    ->filter(fn($user) => $user['active'])  // Creates 500k element array!
    ->map(fn($user) => enrichUser($user))    // Another 500k element array!
    ->toList();
```

### 2. Prefer Lazy Evaluation

```php
// GOOD: Process only what's needed
$result = take(new SplFileObject('large.log'))
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->slice(0, 10)  // Only need first 10
    ->toList();

// BAD: Load everything first
$lines = file('large.log');
$result = take($lines)
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->slice(0, 10)
    ->toList();
```

### 3. Chain Operations

```php
// GOOD: Single pipeline chain
$result = take($data)
    ->filter($predicate)
    ->map($transformer)
    ->slice(0, 100)
    ->toList();

// BAD: Breaking the chain unnecessarily
$filtered = take($data)->filter($predicate)->toList();
$mapped = take($filtered)->map($transformer)->toList();
$result = take($mapped)->slice(0, 100)->toList();
```

### 4. Use Appropriate Methods

```php
// GOOD: Use specialized methods
$count = take($data)->count();
$min = take($values)->min();
$stats = take($numbers)->finalVariance();

// BAD: Reimplementing built-in functionality
$count = take($data)->reduce(fn($c, $_) => $c + 1, 0);
$min = take($values)->reduce(fn($min, $v) => $v < $min ? $v : $min);
```

### 5. Prefer `fold()` for Aggregations

> **Best Practice: Always Use `fold()` for Aggregations**
> 
> While `reduce()` offers a quick shortcut for summation, **`fold()` is the recommended method for all aggregations.** By requiring an explicit initial value, `fold()` eliminates the "implicit magic" of `reduce()`, making your code more predictable, easier to debug, and less prone to type-related errors.

```php
// AVOID: Implicit initial value with reduce()
$sum = take($numbers)->reduce(); // What's the starting value?
$data = take($items)->reduce($buildArray); // Initial value is unclear

// PREFER: Explicit initial value with fold()
$sum = take($numbers)->fold(0); // Clear: starts from 0
$data = take($items)->fold([], $buildArray); // Clear: starts with empty array

// Type-safe aggregation with fold()
$report = take($transactions)->fold(
    ['total' => 0.0, 'count' => 0, 'by_type' => []],
    function($report, $transaction) {
        $report['total'] += $transaction['amount'];
        $report['count']++;
        $report['by_type'][$transaction['type']] = 
            ($report['by_type'][$transaction['type']] ?? 0) + 1;
        return $report;
    }
);
```

### 6. Use Strict Filtering for Data Cleaning

> **Best Practice: Use `strict: true` for Predictable Data Cleaning**
> 
> While `->filter()` is a convenient shortcut for removing all falsy values, this "magic" behavior can lead to unintended data loss by removing valid values like `'0'` (string zero).
> 
> For predictable and safe data cleaning, the recommended approach is to use **`->filter(strict: true)`**. This makes your pipeline's behavior explicit, as it will *only* target `null` and `false` values for removal, preserving all other data.

```php
// AVOID: Aggressive filtering that may lose valid data
$cleaned = take($data)->filter(); // Removes 0, '', '0', [], null, false

// PREFER: Explicit strict filtering
$cleaned = take($data)->filter(strict: true); // Only removes null and false

// Real-world example: Processing form data
$formData = [
    'name' => 'John Doe',
    'age' => 0,              // Valid: newborn baby
    'email' => '',           // Valid: user opted out
    'phone' => null,         // Should be removed
    'subscribe' => false,    // Should be removed
    'tags' => [],           // Valid: no tags selected
];

// Safe cleaning preserves all valid data
$cleaned = take($formData)
    ->filter(strict: true)
    ->toList();
// Result keeps age:0, email:'', and tags:[] intact
```

## Data Source Best Practices

### Working with Arrays

```php
// Array-optimized methods run eagerly for speed
$result = take($array)
    ->filter($predicate)  // EAGER: Uses array_filter
    ->cast($caster)       // EAGER: Uses array_map  
    ->toList();

// But map() is always lazy, even with arrays
$result = take($array)
    ->filter($predicate)  // EAGER: Creates intermediate array
    ->map($transformer)   // LAZY: Waits for terminal operation
    ->toList();

// Force ALL operations to be lazy with stream()
$result = take($array)
    ->stream()  // Convert to generator
    ->filter($predicate)  // Now lazy
    ->map($transformer)   // Still lazy
    ->toList();
```

### Working with Files

```php
// GOOD: Stream large files
function processLogFile($filename) {
    return take(new SplFileObject($filename))
        ->filter(fn($line) => trim($line) !== '')
        ->map(fn($line) => parseLogLine($line))
        ->filter(fn($entry) => $entry !== null);
}

// GOOD: Handle file errors gracefully
function safeFileProcessor($filename) {
    if (!file_exists($filename)) {
        return take([]);  // Empty pipeline
    }
    
    try {
        return take(new SplFileObject($filename));
    } catch (\Exception $e) {
        logError($e);
        return take([]);
    }
}
```

### Working with Generators

```php
// GOOD: Generators maintain state efficiently
function fibonacci() {
    $a = 0;
    $b = 1;
    while (true) {
        yield $a;
        [$a, $b] = [$b, $a + $b];
    }
}

$first10Fib = take(fibonacci())
    ->slice(0, 10)
    ->toList();

// GOOD: Clean up resources in generators
function readDatabase($query) {
    $connection = new PDO($dsn);
    $statement = $connection->query($query);
    
    try {
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
    } finally {
        $statement->closeCursor();
        $connection = null;
    }
}
```

## Transformation Best Practices

### Use map() vs cast()

```php
// Use map() when yielding multiple values
$result = take(['hello world', 'foo bar'])
    ->map(function($phrase) {
        foreach (explode(' ', $phrase) as $word) {
            yield $word;
        }
    })
    ->toList();
// Result: ['hello', 'world', 'foo', 'bar']

// Use cast() for simple transformations
$result = take(['1', '2', '3'])
    ->cast('intval')  // Simple, efficient
    ->toList();
// Result: [1, 2, 3]

// Use cast() when you don't want generator expansion
$result = take([generatorFunc()])
    ->cast(fn($gen) => iterator_to_array($gen))
    ->toList();
// Result: [[...array from generator...]]
```

### Filtering Strategies

```php
// Default filter removes all falsy values
$result = take([0, 1, false, 2, null, 3, '', 4, []])
    ->filter()
    ->toList();
// Result: [1, 2, 3, 4]

// Strict mode only removes null and false
$result = take([0, '', [], null, false])
    ->filter(strict: true)
    ->toList();
// Result: [0, '', []]

// Combine conditions in single filter
$result = take($users)
    ->filter(fn($user) => 
        $user['active'] && 
        $user['age'] >= 18 && 
        in_array($user['role'], ['admin', 'moderator'])
    )
    ->toList();
```

## Error Handling

### Defensive Programming

```php
// Handle missing array keys
$result = take($users)
    ->map(fn($user) => [
        'id' => $user['id'] ?? null,
        'name' => $user['name'] ?? 'Unknown',
        'email' => $user['email'] ?? '',
        'verified' => $user['verified'] ?? false
    ])
    ->filter(fn($user) => $user['id'] !== null)
    ->toList();

// Validate before processing
$result = take($inputs)
    ->filter(fn($input) => $this->validator->isValid($input))
    ->map(fn($input) => $this->processor->process($input))
    ->toList();

// Separate invalid items
$valid = [];
$invalid = [];

take($items)
    ->each(function($item) use (&$valid, &$invalid) {
        if (isValid($item)) {
            $valid[] = processItem($item);
        } else {
            $invalid[] = $item;
        }
    });
```

### Exception Handling

```php
// Wrap operations that might fail
class SafePipeline {
    public static function tryMap($pipeline, callable $callback, $default = null) {
        return $pipeline->map(function($item) use ($callback, $default) {
            try {
                return $callback($item);
            } catch (\Exception $e) {
                logException($e, ['item' => $item]);
                return $default;
            }
        });
    }
}

$result = SafePipeline::tryMap(
    take($urls),
    fn($url) => fetchUrl($url),
    null
)->filter()->toList();  // Remove failed fetches
```

## Performance Patterns

### Memory Management

```php
// Process large datasets in chunks
function processLargeDataset($source, $processor, $chunkSize = 1000) {
    $processed = 0;
    
    take($source)
        ->chunk($chunkSize)
        ->each(function($chunk) use ($processor, &$processed) {
            foreach ($chunk as $item) {
                $processor->process($item);
                $processed++;
                
                if ($processed % 10000 === 0) {
                    echo "Processed: $processed\n";
                    gc_collect_cycles();  // Force garbage collection
                }
            }
        });
    
    return $processed;
}
```

### Efficient Aggregation

```php
// Use running methods for statistics during processing
$count = null;
$stats = null;

$processed = take($measurements)
    ->runningCount($count)
    ->runningVariance($stats)
    ->filter(function($value) use ($stats) {
        // Filter outliers based on running statistics
        if ($stats->getCount() < 10) return true;
        
        $mean = $stats->getMean();
        $stdDev = $stats->getStandardDeviation();
        return abs($value - $mean) <= 3 * $stdDev;
    })
    ->toList();

echo "Processed $count items\n";
echo "Mean: " . $stats->getMean() . "\n";
```

## Testing Pipelines

### Unit Testing

```php
class PipelineTest extends PHPUnit\Framework\TestCase {
    public function testFilteringPipeline() {
        $input = [1, 2, 3, 4, 5, 6];
        
        $result = take($input)
            ->filter(fn($x) => $x % 2 === 0)
            ->map(fn($x) => $x * 2)
            ->toList();
        
        $this->assertEquals([4, 8, 12], $result);
    }
    
    public function testEmptyPipeline() {
        $result = take([])
            ->filter(fn($x) => true)
            ->map(fn($x) => $x * 2)
            ->toList();
        
        $this->assertEquals([], $result);
    }
    
    public function testGeneratorPipeline() {
        $generator = function() {
            yield 1;
            yield 2;
            yield 3;
        };
        
        $result = take($generator())
            ->map(fn($x) => $x ** 2)
            ->toList();
        
        $this->assertEquals([1, 4, 9], $result);
    }
}
```

### Integration Testing

```php
// Test with real data sources
public function testFileProcessing() {
    $testFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($testFile, "line1\nline2\nline3\n");
    
    try {
        $result = take(new SplFileObject($testFile))
            ->map('trim')
            ->filter()
            ->toList();
        
        $this->assertEquals(['line1', 'line2', 'line3'], $result);
    } finally {
        unlink($testFile);
    }
}
```

## Code Organization

### Creating Reusable Pipelines

```php
namespace App\Pipelines;

class UserPipeline {
    public static function activeUsers($users) {
        return take($users)
            ->filter(fn($user) => $user['active'] ?? false)
            ->filter(fn($user) => !($user['deleted_at'] ?? false));
    }
    
    public static function withRole($users, string $role) {
        return take($users)
            ->filter(fn($user) => ($user['role'] ?? '') === $role);
    }
    
    public static function sortByCreatedAt($users, bool $desc = true) {
        $data = take($users)->toList();
        usort($data, fn($a, $b) => $desc
            ? $b['created_at'] <=> $a['created_at']
            : $a['created_at'] <=> $b['created_at']
        );
        return take($data);
    }
}

// Usage - compose by chaining method results
$pipeline = UserPipeline::activeUsers($users);
$pipeline = UserPipeline::withRole($pipeline, 'admin');
$pipeline = UserPipeline::sortByCreatedAt($pipeline);
$admins = $pipeline->toList();

// Or more concisely using temporary variables
$admins = UserPipeline::sortByCreatedAt(
    UserPipeline::withRole(
        UserPipeline::activeUsers($users), 
        'admin'
    )
)->toList();
```

### Pipeline Factories

```php
class PipelineFactory {
    private array $config;
    
    public function __construct(array $config) {
        $this->config = $config;
    }
    
    public function createDataProcessor(): callable {
        return function($data) {
            $pipeline = take($data);
            
            // Apply filters from config
            foreach ($this->config['filters'] ?? [] as $filter) {
                $pipeline = $pipeline->filter($this->buildFilter($filter));
            }
            
            // Apply transformations
            foreach ($this->config['transformers'] ?? [] as $transformer) {
                $pipeline = $pipeline->map($this->buildTransformer($transformer));
            }
            
            return $pipeline;
        };
    }
    
    private function buildFilter($config): callable {
        return match($config['type']) {
            'range' => fn($x) => $x >= $config['min'] && $x <= $config['max'],
            'equals' => fn($x) => $x === $config['value'],
            'regex' => fn($x) => preg_match($config['pattern'], $x),
            default => fn($x) => true
        };
    }
    
    private function buildTransformer($config): callable {
        return match($config['type']) {
            'multiply' => fn($x) => $x * $config['factor'],
            'round' => fn($x) => round($x, $config['precision']),
            'format' => fn($x) => sprintf($config['format'], $x),
            default => fn($x) => $x
        };
    }
}
```

## Common Antipatterns to Avoid

### 1. Pipeline Reuse After Consumption

```php
// BAD: Trying to reuse consumed generator
$pipeline = take(generatorFunction());
$first = $pipeline->toList();
$second = $pipeline->toList(); // Empty! Generator exhausted

// GOOD: Create fresh pipelines
$first = take(generatorFunction())->toList();
$second = take(generatorFunction())->toList();
```

### 2. Modifying Data During Iteration

```php
// BAD: Modifying source during pipeline
$array = [1, 2, 3];
take($array)
    ->each(function($value) use (&$array) {
        $array[] = $value * 2; // Don't modify source!
    });

// GOOD: Create new data
$array = [1, 2, 3];
$doubled = take($array)
    ->map(fn($x) => $x * 2)
    ->toList();
```

### 3. Overusing Pipelines

```php
// BAD: Pipeline for simple operations
$sum = take($array)->reduce();

// GOOD: Use native PHP when simpler
$sum = array_sum($array);

// Pipelines shine with complex transformations
$result = take($data)
    ->filter($complexPredicate)
    ->map($transformer)
    ->chunk(100)
    ->each($processor);
```

## Next Steps

- [Method Index](../reference/method-index.md) - Quick reference for all methods
- [Examples](../quickstart/examples.md) - More code examples
