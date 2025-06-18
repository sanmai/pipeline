# Pipeline Cookbook: Practical Recipes

This section provides ready-to-use solutions for common data processing challenges. Each recipe includes a clear problem statement, an annotated solution, and practical tips.

## Table of Contents

1. [Batch Processing (Database/API)](#batch-processing-databaseapi)
2. [Real-Time Anomaly Detection](#real-time-anomaly-detection)
3. [Parallel Statistics Aggregation](#parallel-statistics-aggregation)
4. [CSV/JSON Processing](#csvjson-processing)
5. [Safe Processing with Error Handling](#safe-processing-with-error-handling)
6. [Memory-Efficient Deduplication](#memory-efficient-deduplication)
7. [Streaming File Search](#streaming-file-search)

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

// Merge all statistics using Welford's parallel algorithm
$overallStats = new RunningVariance();
$overallStats->merge($server1Stats);
$overallStats->merge($server2Stats);
$overallStats->merge($server3Stats);

echo "Overall Mean: " . $overallStats->getMean() . "\n";
echo "Overall StdDev: " . $overallStats->getStandardDeviation() . "\n";
echo "Total Count: " . $overallStats->getCount() . "\n";
```

**Tips:**
- This uses a mathematically sound parallel algorithm to combine statistics
- Each server can process its data independently (even on different machines)
- The merge operation is O(1) regardless of dataset size

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
        // Skip empty rows
        if (empty($row) || count($row) < 3) {
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

---

## Next Steps

- Explore the [API Reference](../api/creation.md) for detailed method documentation
- Check [Advanced Usage](../advanced/complex-pipelines.md) for more complex patterns
- See [Performance](../advanced/performance.md) for optimization techniques