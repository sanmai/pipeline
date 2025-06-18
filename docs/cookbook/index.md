# Pipeline Cookbook: Common Patterns

This cookbook provides solution-oriented patterns for common data processing tasks. Each recipe shows you how to solve a specific problem using the Pipeline library.

## Recipe Categories

### Data Processing
- [Batch Processing](#batch-processing) - Process large datasets efficiently
- [Safe Data Transformation](#safe-data-transformation) - Handle errors without halting
- [Efficient Deduplication](#efficient-deduplication) - Remove duplicates with optimal performance

### Real-Time Analytics
- [Real-Time Anomaly Detection](#real-time-anomaly-detection) - Monitor data streams for outliers
- [Stateful Transformation](#stateful-transformation) - Track state across pipeline elements
- [Sliding Window Analysis](#sliding-window-analysis) - Analyze data in moving windows

### Data Aggregation
- [Parallel Statistics Aggregation](#parallel-statistics-aggregation) - Combine statistics from distributed sources
- [Building Lookup Tables](#building-lookup-tables) - Create efficient key-value mappings
- [Multi-Level Grouping](#multi-level-grouping) - Group and aggregate by multiple criteria

## Batch Processing

**Problem:** You need to process a large dataset (e.g., for database inserts) without exhausting memory.

**Solution:** Use `chunk()` with `each()` to process data in manageable batches.

```php
use function Pipeline\take;

// Process 1 million records in batches of 1000
take($millionRecords)
    // Split into chunks of 1000 records each
    ->chunk(1000)
    // Process each batch
    ->each(function($batch) use ($database) {
        // Start transaction for this batch
        $database->beginTransaction();
        
        try {
            // Bulk insert is much faster than individual inserts
            $database->bulkInsert('users', $batch);
            $database->commit();
            
            // Log progress
            echo "Processed batch of " . count($batch) . " records\n";
            
            // Free memory after each batch
            gc_collect_cycles();
        } catch (\Exception $e) {
            $database->rollback();
            logError("Batch failed: " . $e->getMessage());
        }
    });
```

**Why This Works:**
- Memory usage stays constant regardless of total dataset size
- Database transactions are optimized for bulk operations
- Failed batches don't affect other batches
- Progress tracking helps monitor long-running processes

## Real-Time Anomaly Detection

**Problem:** You need to monitor a continuous data stream and detect anomalous values in real-time.

**Solution:** Use `runningVariance()` to maintain statistics and flag outliers.

```php
use Pipeline\Helper\RunningVariance;

// Monitor sensor readings for anomalies
$anomalies = [];
$stats = null;

take($sensorReadings)
    // Track statistics as data flows through
    ->runningVariance($stats)
    ->each(function($reading) use ($stats, &$anomalies) {
        // Need at least 30 readings for reliable statistics
        if ($stats->getCount() < 30) {
            return;
        }
        
        // Calculate how many standard deviations away from mean
        $mean = $stats->getMean();
        $stdDev = $stats->getStandardDeviation();
        $zScore = abs(($reading['value'] - $mean) / $stdDev);
        
        // Flag readings more than 3 standard deviations from mean
        if ($zScore > 3) {
            $anomalies[] = [
                'timestamp' => $reading['timestamp'],
                'value' => $reading['value'],
                'z_score' => $zScore,
                'expected_range' => [
                    'min' => $mean - 3 * $stdDev,
                    'max' => $mean + 3 * $stdDev
                ]
            ];
            
            // Trigger alert
            alertAnomaly($reading);
        }
    });
```

**Why This Works:**
- Statistics update in real-time without storing historical data
- Anomaly detection adapts as the data distribution changes
- Memory usage remains constant regardless of data volume
- Z-score provides a standardized measure of deviation

## Parallel Statistics Aggregation

**Problem:** You have statistics from multiple distributed sources (e.g., different servers) and need to combine them.

**Solution:** Use `RunningVariance` constructor's merge capability.

```php
use Pipeline\Helper\RunningVariance;

// Scenario: Analyzing response times from multiple servers
$serverStats = [];

// Step 1: Collect statistics from each server independently
foreach ($servers as $server) {
    $serverStats[$server->name] = take($server->getLogs())
        ->map(fn($log) => $log['response_time'])
        ->finalVariance();
}

// Step 2: Merge all statistics into a combined view
$combinedStats = new RunningVariance(...array_values($serverStats));

// Step 3: Analyze combined results
echo "Across all " . count($servers) . " servers:\n";
echo "Total requests: " . $combinedStats->getCount() . "\n";
echo "Average response time: " . round($combinedStats->getMean(), 2) . "ms\n";
echo "Standard deviation: " . round($combinedStats->getStandardDeviation(), 2) . "ms\n";
echo "Fastest response: " . round($combinedStats->getMin(), 2) . "ms\n";
echo "Slowest response: " . round($combinedStats->getMax(), 2) . "ms\n";

// Step 4: Compare individual servers to the aggregate
foreach ($serverStats as $serverName => $stats) {
    $deviation = abs($stats->getMean() - $combinedStats->getMean());
    if ($deviation > $combinedStats->getStandardDeviation()) {
        echo "WARNING: $serverName deviates significantly from cluster average\n";
    }
}
```

**Why This Works:**
- Each server calculates statistics independently (parallelizable)
- Merging is mathematically correct using Welford's parallel algorithm
- No need to re-process raw data from all sources
- Can incrementally add new sources without recalculating everything

## Safe Data Transformation

**Problem:** You need to transform data where some operations might fail, but you don't want to halt the entire pipeline.

**Solution:** Wrap transformations in try-catch blocks and track errors separately.

```php
// Transform API responses, handling failures gracefully
$errors = [];
$successCount = 0;

$results = take($apiResponses)
    ->map(function($response) use (&$errors) {
        try {
            // Potentially failing transformations
            $parsed = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
            
            // Validate required fields
            if (!isset($parsed['id'], $parsed['data'])) {
                throw new \InvalidArgumentException('Missing required fields');
            }
            
            // Complex transformation that might fail
            return [
                'id' => $parsed['id'],
                'processed_data' => processComplexData($parsed['data']),
                'timestamp' => time()
            ];
            
        } catch (\Exception $e) {
            // Log error with context
            $errors[] = [
                'error' => $e->getMessage(),
                'response_id' => $response['id'] ?? 'unknown',
                'raw_body' => substr($response['body'], 0, 100) . '...'
            ];
            
            // Return null to filter out later
            return null;
        }
    })
    // Remove failed transformations
    ->filter()
    // Count successes
    ->runningCount($successCount)
    ->toList();

// Report results
echo "Successfully processed: $successCount\n";
echo "Failed: " . count($errors) . "\n";
if (count($errors) > 0) {
    echo "First few errors:\n";
    foreach (array_slice($errors, 0, 5) as $error) {
        echo "  - {$error['error']} (ID: {$error['response_id']})\n";
    }
}
```

**Why This Works:**
- Failed items don't crash the pipeline
- Errors are collected with context for debugging
- Can process partial results even with failures
- Easy to implement retry logic for failed items

## Stateful Transformation

**Problem:** You need to track state across pipeline elements (e.g., calculating changes from previous values).

**Solution:** Use closures or classes to maintain state during transformation.

```php
// Track changes in stock prices
class ChangeTracker {
    private ?float $previous = null;
    
    public function trackChange(array $record): array {
        $current = $record['price'];
        
        // First record has no change
        if ($this->previous === null) {
            $this->previous = $current;
            return [
                ...$record,
                'change' => 0,
                'percent_change' => 0,
                'direction' => 'none'
            ];
        }
        
        // Calculate changes
        $change = $current - $this->previous;
        $percentChange = ($change / $this->previous) * 100;
        
        // Update state
        $this->previous = $current;
        
        return [
            ...$record,
            'change' => round($change, 2),
            'percent_change' => round($percentChange, 2),
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'unchanged')
        ];
    }
}

$tracker = new ChangeTracker();
$pricesWithChanges = take($stockPrices)
    ->map([$tracker, 'trackChange'])
    ->filter(fn($record) => abs($record['percent_change']) > 5)  // Significant changes only
    ->toList();

// Using closure for simpler state
$runningTotal = 0;
$cumulativeSales = take($dailySales)
    ->map(function($day) use (&$runningTotal) {
        $runningTotal += $day['amount'];
        return [
            ...$day,
            'cumulative_total' => $runningTotal,
            'average_to_date' => $runningTotal / $day['day_number']
        ];
    })
    ->toList();
```

**Why This Works:**
- State persists across pipeline iterations
- Can implement complex stateful logic cleanly
- Classes provide better organization for complex state
- Closures work well for simple state tracking

## Efficient Deduplication

**Problem:** You need to remove duplicate values from a dataset efficiently.

**Solution:** Use the `flip()->flip()` pattern for O(n) deduplication.

```php
// Basic deduplication
$unique = take([1, 2, 2, 3, 3, 3, 4, 4, 4, 4])
    // Step 1: Use values as keys (keys must be unique)
    // [1, 2, 2, 3, 3, 3, 4, 4, 4, 4] becomes [1=>0, 2=>2, 3=>5, 4=>9]
    ->flip()
    // Step 2: Flip back to restore original values
    // [1=>0, 2=>2, 3=>5, 4=>9] becomes [0=>1, 2=>2, 5=>3, 9=>4]
    ->flip()
    // Step 3: Reset to sequential numeric keys
    // [0=>1, 2=>2, 5=>3, 9=>4] becomes [0=>1, 1=>2, 2=>3, 3=>4]
    ->values()
    ->toList();
// Result: [1, 2, 3, 4]

// Deduplication by object property
$uniqueUsers = take($users)
    // Create temporary keys from email addresses
    ->map(fn($user) => [$user['email'] => $user])
    // Flatten to key-value pairs (last duplicate wins)
    ->flatten()
    // Convert back to indexed array
    ->values()
    ->toList();

// Complex deduplication with priority
$prioritizedUnique = take($records)
    // Sort by priority first (higher priority first)
    ->pipe(fn($p) => take($p->toList())->pipe(function($p) {
        $data = $p->toList();
        usort($data, fn($a, $b) => $b['priority'] <=> $a['priority']);
        return take($data);
    }))
    // Now deduplicate - first occurrence (highest priority) wins
    ->reduce(function($unique, $record) {
        $key = $record['id'];
        if (!isset($unique[$key])) {
            $unique[$key] = $record;
        }
        return $unique;
    }, []);
```

**Why This Works:**
- `flip()` deduplication is O(n) vs O(n²) for array_unique with objects
- Preserves first occurrence by default
- Can be adapted for complex deduplication logic
- Memory efficient for large datasets

## Building Lookup Tables

**Problem:** You need to create an efficient key-value mapping from your data for quick lookups.

**Solution:** Use `reduce()` or strategic transformations to build associative arrays.

```php
// Build user ID to user object lookup
$userLookup = take($users)
    ->reduce(function($lookup, $user) {
        $lookup[$user['id']] = $user;
        return $lookup;
    }, []);

// Now O(1) lookup by ID
$user = $userLookup[$userId] ?? null;

// Build multi-key lookup table
$productLookup = take($products)
    ->reduce(function($lookup, $product) {
        // Index by ID
        $lookup['by_id'][$product['id']] = $product;
        
        // Index by SKU
        $lookup['by_sku'][$product['sku']] = $product;
        
        // Index by category (multiple products per category)
        $lookup['by_category'][$product['category']][] = $product;
        
        return $lookup;
    }, ['by_id' => [], 'by_sku' => [], 'by_category' => []]);

// Multiple ways to look up products
$product = $productLookup['by_sku']['ABC123'];
$categoryProducts = $productLookup['by_category']['electronics'];

// Build reverse lookup table
$emailToUserId = take($users)
    ->map(fn($user) => [$user['email'] => $user['id']])
    ->flatten()
    ->toAssoc();

// Build grouped lookup with aggregation
$salesByRegion = take($sales)
    ->reduce(function($lookup, $sale) {
        $region = $sale['region'];
        if (!isset($lookup[$region])) {
            $lookup[$region] = [
                'total' => 0,
                'count' => 0,
                'sales' => []
            ];
        }
        
        $lookup[$region]['total'] += $sale['amount'];
        $lookup[$region]['count']++;
        $lookup[$region]['sales'][] = $sale;
        
        return $lookup;
    }, []);
```

**Why This Works:**
- Transforms O(n) searches into O(1) lookups
- Can build multiple indexes in a single pass
- Flexible structure for complex lookup needs
- Memory trade-off for significant speed gains

## Next Steps

- Review the [API Reference](../api/creation.md) for detailed method documentation
- Explore [Performance Optimization](../advanced/performance.md) techniques
- Check out [Best Practices](../advanced/best-practices.md) for general guidelines

Each recipe in this cookbook demonstrates the Pipeline library's philosophy: **compose simple operations into powerful solutions**. The key is understanding which methods to combine and how they work together to solve your specific problem efficiently.