# Pipeline Cookbook

This cookbook provides practical, ready-to-use solutions for common data processing challenges.

## Data Cleaning

### Safe and Predictable Data Cleaning

**Problem**: You need to remove `null` or `false` values from a dataset without accidentally removing valid data like `0` or empty strings.

**Solution**: Use `filter(strict: true)` to ensure only `null` and `false` are removed.

```php
$cleanedData = take($rawData)
    ->filter(strict: true)
    ->toList();
```

## Batch Processing

### Batching for Databases and APIs

**Problem**: You need to process a large number of records and send them to a database or API in manageable batches.

**Solution**: Use `chunk()` to group items and `each()` to process each batch.

```php
// Process a large dataset in batches of 1000
take(new SplFileObject('large-dataset.csv'))
    ->map(str_getcsv(...))
    ->chunk(1000)
    ->each(function ($batch) {
        // Insert the batch into the database
        Database::bulkInsert($batch);
    });
```

## Real-Time Analysis

### Anomaly Detection

**Problem**: You have a live stream of data and need to identify outliers in real-time.

**Solution**: Use `runningVariance()` to maintain live statistics and identify data points that fall outside a normal range.

```php
$stats = null;
take($liveStream)
    ->runningVariance($stats)
    ->each(function ($value) use ($stats) {
        if ($stats->getCount() > 30) {
            $mean = $stats->getMean();
            $stdDev = $stats->getStandardDeviation();
            if (abs($value - $mean) > (3 * $stdDev)) {
                // Trigger an alert
                AlertSystem::notify("Anomaly detected: $value");
            }
        }
    });
```

## Distributed Data

### Parallel Statistics Aggregation

**Problem**: Your data is distributed across multiple sources, and you need to calculate overall statistics without centralizing the data.

**Solution**: Calculate `finalVariance()` for each source independently, then merge the resulting `RunningVariance` objects.

```php
use Pipeline\Helper\RunningVariance;

$stats1 = take($source1)->finalVariance();
$stats2 = take($source2)->finalVariance();

$overallStats = new RunningVariance($stats1, $stats2);
```

## File Processing

### CSV and JSON Processing

**Problem**: You need to process large CSV or JSON files efficiently.

**Solution**: Stream the file line by line and use `map()` and `filter()` to process the data.

```php
// Process a large CSV file
$data = take(new SplFileObject('data.csv'))
    ->map(str_getcsv(...))
    ->filter(fn($row) => count($row) === 3)
    ->toList();
```

## Error Handling

### Safe Processing

**Problem**: You need to process data where some items may cause errors, but you want to continue processing the rest.

**Solution**: Wrap the risky operation in a `try-catch` block within a `map()` transformation.

```php
$results = take($inputs)
    ->map(function ($item) {
        try {
            return ['success' => true, 'data' => process($item)];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    })
    ->toList();
```

## Deduplication

### Memory-Efficient Deduplication

**Problem**: You need to remove duplicate values from a large dataset without loading it all into memory.

**Solution**: For simple values, use the `flip()` method twice. For complex data, use a "seen" set to track unique items.

```php
// Simple deduplication
$unique = take($values)->flip()->flip()->values()->toList();

// Complex deduplication
$seen = [];
$unique = take($users)
    ->filter(function ($user) use (&$seen) {
        $key = $user['email'];
        if (isset($seen[$key])) {
            return false;
        }
        $seen[$key] = true;
        return true;
    })
    ->toList();
```