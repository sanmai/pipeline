# Pipeline Cookbook: Practical Recipes

This section provides ready-to-use solutions for common data processing challenges. Each recipe includes a clear problem statement, an annotated solution, and practical tips.

## Table of Contents

1. [Safe and Predictable Data Cleaning](#safe-and-predictable-data-cleaning)
2. [Batch Processing (Database/API)](#batch-processing-databaseapi)
3. [Real-Time Anomaly Detection](#real-time-anomaly-detection)
4. [Parallel Statistics Aggregation](#parallel-statistics-aggregation)
5. [CSV/JSON Processing](#csvjson-processing)
6. [Safe Processing with Error Handling](#safe-processing-with-error-handling)
7. [Memory-Efficient Deduplication](#memory-efficient-deduplication)
8. [Streaming File Search](#streaming-file-search)
9. [Working with Infinite Sequences](#working-with-infinite-sequences)
10. [Log File Analysis](#log-file-analysis)
11. [Sliding Window Calculations](#sliding-window-calculations)

---

## Safe and Predictable Data Cleaning

**Problem:** You need to clean a dataset by removing `null` or `false` values, but you must ensure that valid data like the number `0` or an intentionally empty string `''` are not accidentally removed. The default `->filter()` is often too aggressive for this.

**Recipe:** Use `filter()` with its `strict` parameter set to `true`. This guarantees that only `null` and `false` values are removed, preserving all other data.

```php
$rawData = [
    1,
    'hello',
    0,          // A valid number, should not be removed
    '',         // An empty string, might be valid data
    '0',        // A string zero
    true,
    false,      // Should be removed
    null,       // Should be removed
    [],         // An empty array
];

// --- The Aggressive Way (can lead to data loss) ---
$aggressivelyCleaned = take($rawData)
    ->filter() // Default behavior removes ALL falsy values
    ->toList();
// Result: [1, 'hello', true] - DANGEROUS! Lost 0, '', '0', and []

// --- The Safe & Predictable Way (Recommended) ---
$safelyCleaned = take($rawData)
    ->filter(null, strict: true) // Only removes `null` and `false`
    ->toList();
// Result: [1, 'hello', 0, '', '0', true, []] - Correct and safe
```

**Tips:**
- The `strict: true` mode is the recommended approach for any data cleaning task where you need to preserve all values except literal `null` and `false`
- This aligns with the library's philosophy of preferring explicit, predictable operations over implicit ones. See also the ["Prefer `fold()` for Aggregations"](../advanced/best-practices.md#4-prefer-fold-for-aggregations) best practice
- If you *explicitly* want to remove all falsy values, the default `->filter()` is still the correct tool for the job
- For more complex cleaning scenarios, combine with explicit predicates: `->filter(fn($v) => $v !== null && $v !== false && $v !== '')`
- Related methods: [`filter()`](../api/filtering.md#filtercallable-func--null-bool-strict--false), [`toList()`](../api/consumption.md#tolist)

---

## Batch Processing (Database/API)

**Problem:** You need to process a large number of records (e.g., from a file or query) and insert them into a database or send them to an API in manageable batches to avoid overwhelming the target system.

**Recipe:** Use `chunk()` to group items, then `each()` to process each batch.

```php
// Process a large dataset in batches of 1000
take(new SplFileObject('large-dataset.csv'))
    ->map('str_getcsv')  // Convert each line to an array
    ->chunk(1000)        // Group records into chunks of 1000
    ->each(function($batch) {
        // $batch is an array of 1000 records
        // Insert the entire batch in a single database transaction
        Database::beginTransaction();
        try {
            foreach ($batch as $record) {
                Database::insert($record);
            }
            Database::commit();
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    });
```

**Tips:**
- This pattern is memory-efficient and significantly faster than single-item inserts
- Adjust batch size based on your database's capabilities and memory limits
- For APIs, respect rate limits by adding delays between batches
- Related methods: [`chunk()`](../api/chunking.md#chunkint-size), [`each()`](../api/iteration.md#eachcallable-func)

---

## Real-Time Anomaly Detection

**Problem:** You have a stream of live data (e.g., sensor readings, transaction values) and need to identify unusual values or outliers in real-time.

**Recipe:** Use `runningVariance()` to maintain live statistics and identify points that fall outside a normal range (e.g., 3 standard deviations from the mean).

```php
$stats = null;
$outliers = [];

take($liveSensorStream)
    ->runningVariance($stats)
    ->each(function($value) use ($stats, &$outliers) {
        // Wait for a stable baseline of 30+ readings
        if ($stats->getCount() < 30) {
            return;
        }

        $mean = $stats->getMean();
        $stdDev = $stats->getStandardDeviation();

        // Identify values more than 3 standard deviations from the mean
        if (abs($value - $mean) > (3 * $stdDev)) {
            $outliers[] = [
                'value' => $value,
                'deviation' => ($value - $mean) / $stdDev,
                'timestamp' => time()
            ];
            
            // Trigger alert
            AlertSystem::notify("Anomaly detected: $value");
        }
    });
```

**Tips:**
- This allows for memory-safe monitoring of infinite data streams
- Adjust the threshold (3 σ) based on your sensitivity requirements
- Consider using a sliding window for non-stationary data
- Related methods: [`runningVariance()`](../api/statistics.md#runningvariancerunningvariance-variance--null), [`each()`](../api/iteration.md#eachcallable-func)

---

## Parallel Statistics Aggregation

**Problem:** Your data is distributed across multiple sources (e.g., logs from different servers). You need to calculate the overall statistics for all data combined without sending it all to one place first.

**Recipe:** Calculate `finalVariance()` for each source independently, then merge the resulting `RunningVariance` objects.

```php
use Pipeline\Helper\RunningVariance;

// Process statistics from multiple servers in parallel
$server1Stats = take(readServerLog('server1.log'))
    ->map(fn($line) => parseMetric($line))
    ->finalVariance();

$server2Stats = take(readServerLog('server2.log'))
    ->map(fn($line) => parseMetric($line))
    ->finalVariance();

$server3Stats = take(readServerLog('server3.log'))
    ->map(fn($line) => parseMetric($line))
    ->finalVariance();

// Merge all statistics using the constructor, which accepts multiple instances
$overallStats = new RunningVariance(
    $server1Stats,
    $server2Stats,
    $server3Stats
);

// Or using argument unpacking if you have an array of stats objects
// $allStats = [$server1Stats, $server2Stats, $server3Stats];
// $overallStats = new RunningVariance(...$allStats);

echo "Overall Mean: " . $overallStats->getMean() . "\n";
echo "Overall StdDev: " . $overallStats->getStandardDeviation() . "\n";
echo "Total Count: " . $overallStats->getCount() . "\n";
```

**Tips:**
- This uses a mathematically sound parallel algorithm to combine statistics
- Each server can process its data independently (even on different machines)
- The merge operation is O(1) regardless of dataset size
- Related methods: [`finalVariance()`](../api/statistics.md#finalvariancebool-converttofloat--true), [`map()`](../api/transformation.md#mapcallable-func)

---

## CSV/JSON Processing

**Problem:** You need to process large CSV or JSON files efficiently, handling malformed data gracefully.

**Recipe:** Stream the file line-by-line and filter out invalid records.

### CSV Processing
```php
// Process a large CSV with error handling
$processed = 0;
$errors = [];

take(new SplFileObject('data.csv'))
    ->map('str_getcsv')
    ->filter(function($row) use (&$errors) {
        // Skip invalid rows
        if (!is_array($row) || count($row) < 3) {
            $errors[] = "Invalid row: " . json_encode($row);
            return false;
        }
        return true;
    })
    ->map(function($row) {
        // Transform CSV row to structured data
        return [
            'id' => $row[0],
            'name' => $row[1],
            'value' => (float)$row[2],
            'processed_at' => date('Y-m-d H:i:s')
        ];
    })
    ->chunk(500)  // Process in batches
    ->each(function($batch) use (&$processed) {
        saveToDatabase($batch);
        $processed += count($batch);
        echo "Processed: $processed records\n";
    });
```

### JSON Lines Processing
```php
// Process newline-delimited JSON (JSONL)
take(new SplFileObject('events.jsonl'))
    ->map(function($line) {
        $json = json_decode(trim($line), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            logError("Invalid JSON: $line");
            return null;
        }
        return $json;
    })
    ->filter()  // Remove nulls (invalid JSON)
    ->filter(fn($event) => $event['type'] === 'purchase')
    ->map(fn($event) => [
        'user_id' => $event['user_id'],
        'amount' => $event['data']['amount'],
        'timestamp' => $event['timestamp']
    ])
    ->toList();
```

**Tips:**
- Use JSONL format for streaming large JSON datasets
- Always validate/sanitize data before processing
- Log errors for debugging but don't let them stop the pipeline
- Related methods: [`filter()`](../api/filtering.md#filtercallable-func--null-bool-strict--false), [`map()`](../api/transformation.md#mapcallable-func), [`chunk()`](../api/chunking.md#chunkint-size)

---

## Safe Processing with Error Handling

**Problem:** You need to process data where individual items might fail, but you want to continue processing the rest.

**Recipe:** Wrap potentially failing operations in try-catch blocks within your transformation functions.

```php
// Define a safe wrapper for risky operations
function safeProcess($pipeline, $processor, $errorHandler = null) {
    return $pipeline->map(function($item) use ($processor, $errorHandler) {
        try {
            return ['success' => true, 'data' => $processor($item)];
        } catch (Exception $e) {
            if ($errorHandler) {
                $errorHandler($item, $e);
            }
            return ['success' => false, 'error' => $e->getMessage(), 'item' => $item];
        }
    });
}

// Use the safe wrapper
$results = safeProcess(
    take($urls),
    fn($url) => fetchAndParse($url),  // This might throw
    function($url, $error) {
        logError("Failed to fetch $url: " . $error->getMessage());
    }
);

// Separate successes and failures
$successes = take($results)
    ->filter(fn($r) => $r['success'])
    ->map(fn($r) => $r['data'])
    ->toList();

$failures = take($results)
    ->filter(fn($r) => !$r['success'])
    ->toList();

echo "Processed: " . count($successes) . " successes, " . count($failures) . " failures\n";
```

**Tips:**
- This pattern allows the pipeline to continue even when individual items fail
- Collect both successes and failures for comprehensive reporting
- Consider retry logic for transient failures
- Related methods: [`map()`](../api/transformation.md#mapcallable-func), [`filter()`](../api/filtering.md#filtercallable-func--null-bool-strict--false), [`toList()`](../api/consumption.md#tolist)

---

## Memory-Efficient Deduplication

**Problem:** You need to remove duplicates from a large dataset without loading everything into memory.

**Recipe:** Use `flip()` for simple values or maintain a seen-set for complex deduplication.

### Simple Deduplication
```php
// Remove duplicate values (works for scalars)
$unique = take($values)
    ->flip()   // Values become keys (duplicates overwrite)
    ->flip()   // Keys become values again
    ->values() // Reset array keys to sequential
    ->toList();
```

### Complex Deduplication
```php
// Deduplicate by a specific field
$seen = [];
$unique = take($users)
    ->filter(function($user) use (&$seen) {
        $key = $user['email'];  // Deduplicate by email
        if (isset($seen[$key])) {
            return false;  // Already seen this email
        }
        $seen[$key] = true;
        return true;
    })
    ->toList();

// Memory-efficient deduplication for huge datasets
function streamingDeduplicate($source, $keyFunc, $maxMemory = 10000) {
    $seen = [];
    $count = 0;
    
    return take($source)
        ->filter(function($item) use (&$seen, &$count, $keyFunc, $maxMemory) {
            $key = $keyFunc($item);
            
            // Use a rotating buffer to limit memory
            if ($count >= $maxMemory) {
                $seen = array_slice($seen, -($maxMemory / 2), null, true);
                $count = count($seen);
            }
            
            if (isset($seen[$key])) {
                return false;
            }
            
            $seen[$key] = true;
            $count++;
            return true;
        });
}
```

**Tips:**
- The `flip()` technique is fast but only works for scalar values
- For objects/arrays, extract a unique key to check against
- Consider using a Bloom filter for very large datasets
- Related methods: [`flip()`](../api/reorganization.md#flip), [`values()`](../api/reorganization.md#values), [`filter()`](../api/filtering.md#filtercallable-func--null-bool-strict--false)

---

## Streaming File Search

**Problem:** You need to search through multiple large files for specific patterns without loading them entirely into memory.

**Recipe:** Combine file streaming with pattern matching and early termination.

```php
// Search for errors across multiple log files
function searchLogs($pattern, $logFiles, $maxResults = 100) {
    $results = [];
    $found = 0;
    
    take($logFiles)
        ->map(function($file) use ($pattern, &$results, &$found, $maxResults) {
            if ($found >= $maxResults) {
                return;  // Stop processing more files
            }
            
            take(new SplFileObject($file))
                ->map(fn($line, $lineNo) => [
                    'file' => $file,
                    'line' => $lineNo + 1,
                    'content' => trim($line)
                ])
                ->filter(fn($item) => preg_match($pattern, $item['content']))
                ->each(function($match) use (&$results, &$found, $maxResults) {
                    if ($found >= $maxResults) {
                        return;  // Early termination
                    }
                    $results[] = $match;
                    $found++;
                });
        });
    
    return $results;
}

// Usage
$errorLogs = searchLogs(
    '/ERROR|CRITICAL|FATAL/i',
    glob('/var/log/*.log'),
    50  // Find first 50 matches
);

foreach ($errorLogs as $match) {
    echo "{$match['file']}:{$match['line']} - {$match['content']}\n";
}
```

**Tips:**
- This pattern efficiently searches gigabytes of logs using minimal memory
- Early termination prevents unnecessary processing
- Can be extended with more complex pattern matching or ML-based classification
- Related methods: [`map()`](../api/transformation.md#mapcallable-func), [`filter()`](../api/filtering.md#filtercallable-func--null-bool-strict--false), [`each()`](../api/iteration.md#eachcallable-func)

---

## Working with Infinite Sequences

**Problem:** You need to generate and process infinite sequences (like mathematical series) lazily, taking only what you need without exhausting memory.

**Recipe:** Use generators to create infinite sequences and pipeline methods to control consumption.

```php
use function Pipeline\map;

// Create an infinite Fibonacci sequence
$fibonacci = map(function() {
    $a = 0;
    $b = 1;
    while (true) {
        yield $a;
        [$a, $b] = [$b, $a + $b];
    }
});

// Take only what you need
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
// Result: [0, 1, 1, 2, 3, 5, 8, 13, 21, 34, 55, 89, 144, 233, 377, 610, 987]

// Create other infinite sequences
$primes = map(function() {
    $candidate = 2;
    $primes = [];
    while (true) {
        $isPrime = true;
        foreach ($primes as $prime) {
            if ($prime * $prime > $candidate) break;
            if ($candidate % $prime === 0) {
                $isPrime = false;
                break;
            }
        }
        if ($isPrime) {
            $primes[] = $candidate;
            yield $candidate;
        }
        $candidate++;
    }
});

// Get first 10 prime numbers
$firstPrimes = $primes->slice(0, 10)->toList();
// Result: [2, 3, 5, 7, 11, 13, 17, 19, 23, 29]
```

**Tips:**
- Generators maintain state between yields, making them perfect for sequences
- Always use limiting methods like `slice()` or `take()` with infinite sequences
- Consider memory implications when storing results from infinite sequences
- Related methods: [`map()`](../api/creation.md#mapcallable-func), [`slice()`](../api/selection.md#sliceint-offset-int-length--null), [`toList()`](../api/consumption.md#tolist)

---

## Log File Analysis

**Problem:** You need to analyze server logs to extract patterns, count occurrences, and identify issues without loading gigabytes of data into memory.

**Recipe:** Stream the log file line by line, parse relevant information, and aggregate results.

```php
// Analyze Apache access logs for top IPs
$topIPs = take(new SplFileObject('access.log'))
    ->map(function($line) {
        // Apache combined log format: IP - - [timestamp] "request" status size
        if (preg_match('/^(\S+)/', $line, $matches)) {
            return $matches[1];
        }
        return null;
    })
    ->filter()  // Remove failed matches
    ->fold([], function($counts, $ip) {
        $counts[$ip] = ($counts[$ip] ?? 0) + 1;
        return $counts;
    });

// Get top 10 IPs by request count
arsort($topIPs);
$top10IPs = array_slice($topIPs, 0, 10, true);

// Analyze error patterns
$errorPatterns = take(new SplFileObject('error.log'))
    ->filter(fn($line) => preg_match('/\[(error|crit|alert|emerg)\]/i', $line))
    ->map(function($line) {
        // Extract error type and message
        if (preg_match('/\[(\w+)\].*?:\s*(.+)$/', $line, $matches)) {
            return [
                'level' => strtolower($matches[1]),
                'message' => $matches[2],
                'time' => date('Y-m-d H:i:s')
            ];
        }
        return null;
    })
    ->filter()
    ->fold(['by_level' => [], 'messages' => []], function($report, $error) {
        $report['by_level'][$error['level']] = 
            ($report['by_level'][$error['level']] ?? 0) + 1;
        $report['messages'][] = $error;
        return $report;
    });

// Time-based analysis
$requestsByHour = take(new SplFileObject('access.log'))
    ->map(function($line) {
        if (preg_match('/\[([^:]+):(\d{2})/', $line, $matches)) {
            return $matches[2]; // Extract hour
        }
        return null;
    })
    ->filter()
    ->fold(array_fill(0, 24, 0), function($hours, $hour) {
        $hours[(int)$hour]++;
        return $hours;
    });
```

**Tips:**
- Use regular expressions to parse structured log formats
- Aggregate data using `fold()` with structured initial values
- Consider using [`runningCount()`](../api/aggregation.md#runningcountint-count--null) for real-time counting
- For very large files, process in chunks with [`chunk()`](../api/chunking.md#chunkint-size)
- Related methods: [`fold()`](../api/aggregation.md#foldmixed-initial-callable-func--null), [`filter()`](../api/filtering.md#filtercallable-func--null-bool-strict--false), [`map()`](../api/transformation.md#mapcallable-func)

---

## Sliding Window Calculations

**Problem:** You need to calculate metrics over a sliding window of data points, such as moving averages, rolling sums, or trend detection.

**Recipe:** Use `tuples()` to add indices, then calculate values based on surrounding elements.

```php
// Calculate 3-day moving average
$temperatures = [20, 22, 25, 23, 21, 19, 18, 20, 22, 24];
$windowSize = 3;

$movingAverage = take($temperatures)
    ->tuples()  // Convert to [index, value] pairs
    ->map(function($tuple) use ($temperatures, $windowSize) {
        [$index, $value] = $tuple;
        
        // Get window of values
        $start = max(0, $index - $windowSize + 1);
        $window = array_slice($temperatures, $start, min($windowSize, $index + 1));
        
        return [
            'day' => $index + 1,
            'temperature' => $value,
            'moving_avg' => round(array_sum($window) / count($window), 1)
        ];
    })
    ->toList();

// Detect trends using sliding window
function detectTrends($data, $windowSize = 5) {
    return take($data)
        ->tuples()
        ->map(function($tuple) use ($data, $windowSize) {
            [$index, $value] = $tuple;
            
            if ($index < $windowSize - 1) {
                return ['index' => $index, 'value' => $value, 'trend' => 'insufficient_data'];
            }
            
            $window = array_slice($data, $index - $windowSize + 1, $windowSize);
            $firstHalf = array_sum(array_slice($window, 0, intval($windowSize / 2)));
            $secondHalf = array_sum(array_slice($window, -intval($windowSize / 2)));
            
            $trend = $secondHalf > $firstHalf ? 'increasing' : 
                    ($secondHalf < $firstHalf ? 'decreasing' : 'stable');
            
            return [
                'index' => $index,
                'value' => $value,
                'trend' => $trend,
                'strength' => abs($secondHalf - $firstHalf)
            ];
        })
        ->toList();
}

// Calculate rolling statistics
$rollingStats = take($measurements)
    ->tuples()
    ->map(function($tuple) use ($measurements, $windowSize) {
        [$index, $value] = $tuple;
        $window = array_slice($measurements, max(0, $index - $windowSize + 1), $windowSize);
        
        return [
            'value' => $value,
            'window_min' => min($window),
            'window_max' => max($window),
            'window_avg' => array_sum($window) / count($window),
            'window_std' => sqrt(array_sum(array_map(
                fn($x) => pow($x - array_sum($window) / count($window), 2), 
                $window
            )) / count($window))
        ];
    })
    ->toList();
```

**Tips:**
- [`tuples()`](../api/transformation.md#tuples) is perfect for position-aware calculations
- Adjust window size based on your data's characteristics
- Consider using [`runningVariance()`](../api/statistics.md#runningvariancerunningvariance-variance--null) for streaming statistics
- For time-series data, consider grouping by time periods first
- Related methods: [`tuples()`](../api/transformation.md#tuples), [`map()`](../api/transformation.md#mapcallable-func), [`toList()`](../api/consumption.md#tolist)

---

## Next Steps

- Explore the [API Reference](../api/creation.md) for detailed method documentation
- Check [Advanced Usage](../advanced/complex-pipelines.md) for more complex patterns
- See [Performance](../advanced/performance.md) for optimization techniques