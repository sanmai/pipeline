# Performance Optimization

Guidelines and techniques for optimizing pipeline performance and memory usage.

## Understanding Pipeline Performance

### Lazy vs Eager Evaluation

```php
// Lazy evaluation (memory efficient)
$result = take(new SplFileObject('10gb-file.txt'))
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->map(fn($line) => parseLogLine($line))
    ->slice(0, 100)
    ->toList();
// Only processes lines until 100 matches found

// Eager evaluation (uses more memory)
$lines = file('10gb-file.txt'); // Loads entire file!
$result = take($lines)
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->slice(0, 100)
    ->toList();
```

### Array vs Generator Performance

```php
// Array operations are optimized
$result = take($array)
    ->filter($predicate)  // Uses array_filter internally
    ->map($transformer)   // Uses array_map for cast()
    ->toList();

// Force generator for memory efficiency
$result = take($array)
    ->stream()  // Convert to generator
    ->filter($predicate)
    ->map($transformer)
    ->toList();
```

### Batch vs Stream Processing on Arrays

The pipeline library employs two distinct processing models for arrays, and choosing the right one is key to managing memory.

* **Batch Processing (Default for Arrays):** By default, operations on arrays are optimized to run in batches using native PHP functions like `array_map` (for `cast()`) and `array_filter` (for `filter()`). This is very fast for small-to-medium arrays. However, each step creates a new intermediate array in memory.

* **Stream Processing (After `stream()`):** Calling the `stream()` method converts the array into a `Generator`. This switches the processing model so that each element travels through the entire pipeline chain individually. This completely avoids the creation of large intermediate arrays, drastically reducing peak memory usage at the cost of a small performance overhead for the generator itself.

**When to switch to `stream()`:**

You should explicitly call `->stream()` on an array pipeline if you are performing memory-intensive transformations (like loading data for each item) or if the intermediate arrays created by `map()` or `filter()` would be too large for your available memory.

```php
// Example: Each user ID loads a full user object with related data
$userIds = range(1, 10000);

// WITHOUT stream() - Creates 10,000 user objects in memory at once
$activeUsers = take($userIds)
    ->map(fn($id) => User::loadWithRelations($id))  // 10,000 objects created here!
    ->filter(fn($user) => $user->isActive())        // Then filtered
    ->toList();

// WITH stream() - Loads and processes one user at a time
$activeUsers = take($userIds)
    ->stream()  // Switch to element-by-element processing
    ->map(fn($id) => User::loadWithRelations($id))  // Only 1 user in memory at a time
    ->filter(fn($user) => $user->isActive())        // Filtered immediately
    ->toList();
```

## Lazy vs Eager Showdown

Understanding the difference between lazy and eager evaluation is crucial for performance. Here's a concrete demonstration:

### Finding Errors in a Large Log File

Consider the task of finding the first 5 "ERROR" lines in a 10 GB log file.

**The Wrong Way (Eager Evaluation):**
```php
// WARNING: Do not run this on a large file!
// This approach will likely exhaust your server's memory

$lines = file('huge-10GB.log'); // Loads the ENTIRE file into memory (10GB!)
$errors = take($lines)
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->slice(0, 5)
    ->toList();

// Problems:
// 1. file() loads entire 10GB into memory immediately
// 2. Creates a massive array of all lines
// 3. May crash with: "Allowed memory size exhausted"
// 4. Processes entire file even though we only need 5 errors
```

**The Right Way (Lazy Evaluation):**
```php
// This is memory-safe and efficient
$errors = take(new SplFileObject('huge-10GB.log'))
    // SplFileObject reads one line at a time (lazy)
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    // Filter processes lines as they're read
    ->slice(0, 5)
    // slice() ensures we stop after finding 5 matches
    ->toList();
    // toList() triggers execution and collects results

// Benefits:
// 1. Memory usage stays constant (~1MB) regardless of file size
// 2. Stops reading after finding 5 errors (could be on line 100 of 10 million)
// 3. Can process files larger than available RAM
// 4. Typically 100-1000x faster for this use case
```

### Real Performance Comparison

```php
// Test setup: 1GB log file with errors on lines 50, 150, 250, 350, 450

// Eager approach
$start = microtime(true);
$memory = memory_get_usage();

$lines = file('1GB-test.log');  // ~8 seconds, ~1GB RAM
$errors = take($lines)
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->slice(0, 5)
    ->toList();

echo "Eager: " . round(microtime(true) - $start, 2) . "s, ";
echo "Memory: " . round((memory_get_peak_usage() - $memory) / 1024 / 1024) . "MB\n";
// Output: "Eager: 8.5s, Memory: 1024MB"

// Lazy approach
$start = microtime(true);
$memory = memory_get_usage();

$errors = take(new SplFileObject('1GB-test.log'))
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->slice(0, 5)
    ->toList();

echo "Lazy: " . round(microtime(true) - $start, 2) . "s, ";
echo "Memory: " . round((memory_get_peak_usage() - $memory) / 1024 / 1024) . "MB\n";
// Output: "Lazy: 0.003s, Memory: 0.5MB"
```

The lazy approach is **2,800x faster** and uses **2,000x less memory** because it:
1. Only reads lines until it finds 5 errors
2. Never loads the entire file into memory
3. Processes data in a streaming fashion

## Memory Optimization

### Processing Large Files

```php
// DON'T: Load entire file
$data = json_decode(file_get_contents('large.json'), true);
$result = take($data)->map($processor)->toList();

// DO: Stream line by line
$result = take(new SplFileObject('large.jsonl'))
    ->map('json_decode')
    ->filter()  // Skip invalid JSON
    ->map($processor)
    ->toList();

// DO: Use generators for custom reading
function readLargeJson($filename) {
    $handle = fopen($filename, 'r');
    $buffer = '';
    
    while (!feof($handle)) {
        $chunk = fread($handle, 8192);
        $buffer .= $chunk;
        
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);
            
            if ($json = json_decode($line, true)) {
                yield $json;
            }
        }
    }
    
    fclose($handle);
}

$result = take(readLargeJson('large.jsonl'))
    ->filter($predicate)
    ->toList();
```

### Chunking for Memory Management

```php
// Process in manageable chunks
take(new SplFileObject('huge.csv'))
    ->map('str_getcsv')
    ->chunk(1000)  // Process 1000 rows at a time
    ->each(function($chunk) {
        // Process chunk (e.g., bulk database insert)
        $this->repository->bulkInsert($chunk);
        
        // Free memory after each chunk
        gc_collect_cycles();
    });

// Sliding window processing
function slidingWindow($data, $windowSize) {
    $window = [];
    
    return take($data)
        ->map(function($item) use (&$window, $windowSize) {
            $window[] = $item;
            if (count($window) > $windowSize) {
                array_shift($window);
            }
            return ['current' => $item, 'window' => $window];
        });
}
```

## Operation Optimization

### Efficient Filtering

```php
// Combine filters for efficiency
// DON'T: Multiple filter passes
$result = take($data)
    ->filter(fn($x) => $x > 0)
    ->filter(fn($x) => $x < 100)
    ->filter(fn($x) => $x % 2 === 0)
    ->toList();

// DO: Single combined filter
$result = take($data)
    ->filter(fn($x) => $x > 0 && $x < 100 && $x % 2 === 0)
    ->toList();

// Early termination with skipWhile
$result = take($sortedData)
    ->skipWhile(fn($x) => $x < $threshold)
    ->filter(fn($x) => $x < $upperLimit)
    ->toList();
```

### Transformation Optimization

```php
// Use cast() for simple transformations on arrays
$result = take($array)
    ->cast('intval')  // Optimized with array_map
    ->toList();

// Avoid creating intermediate arrays
// DON'T
$result = take($data)
    ->map(fn($x) => processStep1($x))
    ->toList();  // Intermediate array
$result = take($result)
    ->map(fn($x) => processStep2($x))
    ->toList();

// DO
$result = take($data)
    ->map(fn($x) => processStep1($x))
    ->map(fn($x) => processStep2($x))
    ->toList();
```

### Aggregation Optimization

```php
// Use specialized methods
// DON'T: Count by converting to array
$count = count(take($generator)->toList());

// DO: Use count() directly
$count = take($generator)->count();

// Running calculations without collecting
$sum = 0;
$count = 0;
take($hugeDataset)
    ->runningCount($count)
    ->each(function($value) use (&$sum) {
        $sum += $value;
    });
$average = $sum / $count;

// Efficient min/max for sorted data
$min = take($sortedData)->slice(0, 1)->toList()[0] ?? null;
$max = take($sortedData)->slice(-1, 1)->toList()[0] ?? null;
```

## Database and I/O Optimization

### Batch Database Operations

```php
class BatchInserter {
    private $batchSize;
    private $pdo;
    
    public function __construct(PDO $pdo, int $batchSize = 1000) {
        $this->pdo = $pdo;
        $this->batchSize = $batchSize;
    }
    
    public function insertBatch($records) {
        take($records)
            ->chunk($this->batchSize)
            ->each(function($chunk) {
                $this->pdo->beginTransaction();
                try {
                    $stmt = $this->pdo->prepare(
                        "INSERT INTO records (id, data) VALUES (?, ?)"
                    );
                    
                    foreach ($chunk as $record) {
                        $stmt->execute([$record['id'], $record['data']]);
                    }
                    
                    $this->pdo->commit();
                } catch (\Exception $e) {
                    $this->pdo->rollBack();
                    throw $e;
                }
            });
    }
}
```

### Parallel-Like Processing

```php
// Simulate parallel processing with proc_open
function parallelMap($items, $command, $workers = 4) {
    $chunks = take($items)
        ->chunk(ceil(count($items) / $workers))
        ->toList();
    
    $processes = [];
    $results = [];
    
    // Start processes
    foreach ($chunks as $i => $chunk) {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];
        
        $process = proc_open($command, $descriptors, $pipes);
        fwrite($pipes[0], json_encode($chunk));
        fclose($pipes[0]);
        
        $processes[$i] = [
            'process' => $process,
            'stdout' => $pipes[1],
            'stderr' => $pipes[2]
        ];
    }
    
    // Collect results
    foreach ($processes as $i => $proc) {
        $output = stream_get_contents($proc['stdout']);
        fclose($proc['stdout']);
        fclose($proc['stderr']);
        proc_close($proc['process']);
        
        $results[$i] = json_decode($output, true);
    }
    
    return take($results)->flatten()->toList();
}
```

## Profiling and Measurement

### Performance Monitoring

```php
class PerformanceMonitor {
    private array $metrics = [];
    
    public function measure(string $operation, callable $callback) {
        $start = microtime(true);
        $memStart = memory_get_usage();
        
        $result = $callback();
        
        $duration = microtime(true) - $start;
        $memUsed = memory_get_usage() - $memStart;
        
        $this->metrics[$operation] = [
            'duration' => $duration,
            'memory' => $memUsed,
            'peak_memory' => memory_get_peak_usage()
        ];
        
        return $result;
    }
    
    public function getReport(): array {
        return $this->metrics;
    }
}

$monitor = new PerformanceMonitor();

$result = $monitor->measure('processing', function() use ($data) {
    return take($data)
        ->filter($predicate)
        ->map($transformer)
        ->toList();
});

print_r($monitor->getReport());
```

### Benchmarking Pipelines

```php
function benchmarkPipeline($name, $data, $pipeline) {
    $iterations = 10;
    $times = [];
    
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        $pipeline($data);
        $times[] = microtime(true) - $start;
    }
    
    $avg = array_sum($times) / count($times);
    $min = min($times);
    $max = max($times);
    
    echo "$name:\n";
    echo "  Average: " . round($avg * 1000, 2) . "ms\n";
    echo "  Min: " . round($min * 1000, 2) . "ms\n";
    echo "  Max: " . round($max * 1000, 2) . "ms\n\n";
}

// Compare approaches
benchmarkPipeline('Array-based', $data, fn($d) =>
    take($d)->filter($pred)->map($trans)->toList()
);

benchmarkPipeline('Generator-based', $data, fn($d) =>
    take($d)->stream()->filter($pred)->map($trans)->toList()
);
```

## Optimization Checklist

### Before Optimization
1. **Profile first** - Identify actual bottlenecks
2. **Measure baseline** - Know your starting point
3. **Set targets** - Define performance goals

### Memory Optimization
- ✓ Use generators for large datasets
- ✓ Process in chunks for batch operations
- ✓ Avoid intermediate array creation
- ✓ Use `stream()` to force lazy evaluation
- ✓ Free resources explicitly when needed

### Speed Optimization
- ✓ Combine multiple filters into one
- ✓ Use `cast()` for simple transformations
- ✓ Leverage array optimizations when possible
- ✓ Minimize function calls in hot paths
- ✓ Use early termination patterns

### I/O Optimization
- ✓ Batch database operations
- ✓ Use prepared statements
- ✓ Stream file reading
- ✓ Implement connection pooling
- ✓ Consider async/parallel patterns

## Common Performance Pitfalls

### Pitfall 1: Unnecessary Array Conversion

```php
// BAD: Forces array creation
$exists = in_array($needle, take($generator)->toList());

// GOOD: Short-circuit on first match
$exists = take($generator)
    ->filter(fn($x) => $x === $needle)
    ->slice(0, 1)
    ->count() > 0;
```

### Pitfall 2: Repeated Pipeline Creation

```php
// BAD: Recreating pipeline
foreach ($items as $item) {
    $result = take($data)
        ->filter(fn($x) => $x['id'] === $item['ref_id'])
        ->toList();
}

// GOOD: Create lookup once
$lookup = take($data)
    ->fold([], fn($acc, $x) => [...$acc, $x['id'] => $x]);
foreach ($items as $item) {
    $result = $lookup[$item['ref_id']] ?? null;
}
```

### Pitfall 3: Inefficient Deduplication

```php
// BAD: O(n²) deduplication
$unique = [];
take($items)->each(function($item) use (&$unique) {
    if (!in_array($item, $unique)) {
        $unique[] = $item;
    }
});

// GOOD: O(n) using keys
$unique = take($items)
    ->flip()->flip()
    ->values()
    ->toList();
```

## Next Steps

- [Best Practices](best-practices.md) - General guidelines
- [Method Index](../reference/method-index.md) - Quick method reference