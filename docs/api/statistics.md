# Statistical Methods

These methods are used for performing statistical analysis on pipeline data.

## `finalVariance()`

Calculates a comprehensive set of statistics for numeric data in the pipeline.

**Signature**: `finalVariance(?callable $castFunc = null, ?RunningVariance $variance = null): RunningVariance`

-   `$castFunc`: A function to convert pipeline values to floats. Defaults to `floatval`. Return `null` to skip non-numeric values.
-   `$variance`: An optional, pre-initialized `RunningVariance` object to continue calculations from.

**Behavior**:

-   This is a terminal operation that returns a `RunningVariance` object.
-   The `RunningVariance` object contains methods to get the mean, variance, standard deviation, min, max, and count.
-   Values that the `$castFunc` returns as `null` are not included in the statistics.

**Examples**:

```php
use Pipeline\Helper\RunningVariance;

// Basic statistics
$stats = take([1, 2, 3, 4, 5])->finalVariance();
echo $stats->getCount();              // 5
echo $stats->getMean();               // 3.0
echo $stats->getVariance();           // 2.5
echo $stats->getStandardDeviation();  // ~1.58
echo $stats->getMin();                // 1.0
echo $stats->getMax();                // 5.0

// Statistics for a specific field
$stats = take($users)->finalVariance(fn($user) => $user['age']);

// Handling mixed data (skip non-numeric values)
$stats = take(['1', 'abc', 2, null, 3.5])
    ->finalVariance(fn($x) => is_numeric($x) ? (float)$x : null);
echo $stats->getCount(); // 3 (only numeric values counted)

// Continuing from existing statistics
$initialStats = take($firstBatch)->finalVariance();
$combinedStats = take($secondBatch)->finalVariance(null, $initialStats);
```

## `runningVariance()`

Observes values as they pass through the pipeline, calculating statistics without consuming the pipeline.

**Signature**: `runningVariance(?RunningVariance &$variance, ?callable $castFunc = null): self`

-   `&$variance`: A reference to a `RunningVariance` object, which will be updated with the statistics. It will be created if `null`.
-   `$castFunc`: A function to convert pipeline values to floats.

**Behavior**:

-   This is a non-terminal operation that allows you to inspect statistics at a point in the chain.

**Examples**:

```php
$stats = null;
$processedData = take([1, 2, 3, 4, 5])
    ->runningVariance($stats)
    ->map(fn($x) => $x * 2)
    ->toList();

echo $stats->getMean(); // 3.0
```

## `RunningVariance` Helper Class

The `Pipeline\Helper\RunningVariance` class provides a powerful way to work with statistics. It uses Welford's online algorithm to calculate variance and other metrics in a single pass, which is highly efficient.

### Key `RunningVariance` Methods

-   `getCount(): int`: The number of observations.
-   `getMean(): float`: The arithmetic mean.
-   `getVariance(): float`: The sample variance.
-   `getStandardDeviation(): float`: The sample standard deviation.
-   `getMin(): float`: The minimum value.
-   `getMax(): float`: The maximum value.

### Merging Statistics

You can merge `RunningVariance` instances, which is useful for parallel processing or combining historical and current data.

```php
// Merge stats from two different sources
$stats1 = take($source1)->finalVariance();
$stats2 = take($source2)->finalVariance();

$combinedStats = new RunningVariance($stats1, $stats2);
```
