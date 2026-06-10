# Pipeline Cookbook

This cookbook provides practical, ready-to-use solutions for common data processing challenges.

## Data Cleaning

### Safe and Predictable Data Cleaning

**Problem**: You need to remove `null` or `false` values from a dataset without accidentally removing valid data like `0` or empty strings.

**Solution**: Use `select()`, which by default removes only `null` and `false`.

```php
$cleanedData = take($rawData)
    ->select()
    ->toList();
```

Use plain `filter()` only when you genuinely want to drop every falsy value, like `array_filter()` does.

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
        Database::bulkInsert($batch);
    });
```

Only one batch is in memory at a time. For ramp-up scenarios—a small trial batch first, then full-size batches—see [`chunkBy()`](../api/transformation.md#chunkby).

## Real-Time Analysis

### Anomaly Detection

**Problem**: You have a live stream of data and need to identify outliers in real time.

**Solution**: Use `runningVariance()` to maintain live statistics and flag data points that fall outside a normal range.

```php
$stats = null;
take($liveStream)
    ->runningVariance($stats)
    ->each(function ($value) use ($stats) {
        if ($stats->getCount() < 30) {
            return; // Not enough data yet
        }

        if (abs($value - $stats->getMean()) > 3 * $stats->getStandardDeviation()) {
            AlertSystem::notify("Anomaly detected: $value");
        }
    });
```

### Sliding Window Calculations

**Problem**: You need a moving average (or any windowed calculation) over a stream.

**Solution**: Keep a small rolling buffer in a stateful `map()` callback, yielding once the window is full.

```php
$window = [];
$movingAverages = take($measurements)
    ->map(function ($value) use (&$window) {
        $window[] = $value;
        if (count($window) > 5) {
            array_shift($window);
        }
        if (count($window) === 5) {
            yield array_sum($window) / 5;
        }
    })
    ->toList();
```

## Distributed Data

### Parallel Statistics Aggregation

**Problem**: Your data is distributed across multiple sources, and you need overall statistics without centralizing the data.

**Solution**: Calculate `finalVariance()` for each source independently, then merge the resulting `RunningVariance` objects.

```php
use Pipeline\Helper\RunningVariance;

$stats1 = take($source1)->finalVariance();
$stats2 = take($source2)->finalVariance();

$overallStats = new RunningVariance($stats1, $stats2);
```

## File Processing

### CSV Processing

**Problem**: You need to process a large CSV file efficiently.

**Solution**: Stream the file line by line; only one row is in memory at a time.

```php
$data = take(new SplFileObject('data.csv'))
    ->map(str_getcsv(...))
    ->filter(fn($row) => count($row) === 3)
    ->toList();
```

### Log File Analysis

**Problem**: You need counts and a sample of matching lines from a huge log file, in a single pass.

**Solution**: Combine `runningCount()` with the filtering chain; use `skipWhile()` to ignore a preamble.

```php
$total = 0;
$errors = take(new SplFileObject('app.log'))
    ->skipWhile(fn($line) => !str_contains($line, 'STARTUP COMPLETE'))
    ->runningCount($total)
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->slice(0, 100)
    ->toList();

// $errors holds the first 100 errors; $total counts all lines seen
```

## Infinite Sequences

### Generating and Consuming Endless Streams

**Problem**: You need to process a sequence with no natural end—generated data, polling results, an event stream.

**Solution**: Seed the pipeline with an infinite generator; laziness guarantees only the consumed portion is ever computed. Bound the consumption with `slice()` or by breaking out of a `foreach`.

```php
use function Pipeline\map;

$fibonacci = map(function () {
    yield 0;

    $prev = 0;
    $current = 1;

    while (true) {
        yield $current;
        [$prev, $current] = [$current, $prev + $current];
    }
});

// Statistics for the second hundred Fibonacci numbers
$variance = $fibonacci->slice(101, 100)->finalVariance();
$variance->getCount(); // 100
```

## Error Handling

### Safe Processing

**Problem**: Some items may cause errors, but you want to continue processing the rest.

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

### Logging Rejected Items

**Problem**: You filter out invalid records but still need to know what was dropped and why.

**Solution**: Use `select()` with its `onReject` callback.

```php
$valid = take($records)
    ->select(
        fn($record) => $record->isValid(),
        onReject: fn($record, $key) => $logger->warning("Dropped record $key"),
    )
    ->toList();
```

## Deduplication

### Memory-Efficient Deduplication

**Problem**: You need to remove duplicate values from a large stream without loading it all into memory.

**Solution**: Track seen keys in a set; memory grows with the number of *unique* items only.

```php
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

For an array-backed pipeline of scalar values there is also a shortcut: `flip()->flip()` deduplicates via `array_flip()`, just as it would with plain arrays.
