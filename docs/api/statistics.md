# Statistical Methods

These methods perform statistical analysis on pipeline data.

## `finalVariance()`

Calculates a comprehensive set of statistics for numeric data in the pipeline. This is a **terminal operation**.

**Signature**: `finalVariance(?callable $castFunc = null, ?RunningVariance $variance = null): RunningVariance`

-   `$castFunc`: A function to convert pipeline values to floats. Return `null` to skip non-numeric values. Defaults to `floatval`.
-   `$variance`: An optional, pre-initialized `RunningVariance` object to continue calculations.

The method returns a `RunningVariance` object containing the results.

**Examples**:

```php
use Pipeline\Helper\RunningVariance;

// Basic statistics
$stats = take([1, 2, 3, 4, 5])->finalVariance();
echo $stats->getMean(); // 3.0
echo $stats->getStandardDeviation();  // ~1.58

// Statistics for a specific field, skipping non-numeric values
$stats = take($data)
    ->finalVariance(fn($item) => is_numeric($item['value']) ? (float)$item['value'] : null);
```

## `runningVariance()`

Calculates statistics on values as they pass through the pipeline without consuming it. This is a **non-terminal operation**, useful for monitoring.

**Signature**: `runningVariance(?RunningVariance &$variance, ?callable $castFunc = null): self`

-   `&$variance`: A reference to a `RunningVariance` object, which will be updated. It will be created if `null`.
-   `$castFunc`: A function to convert pipeline values to floats.

**Example**:

```php
$stats = null;
$processedData = take([1, 2, 3, 4, 5])
    ->runningVariance($stats)
    ->map(fn($x) => $x * 2)
    ->toList();

echo $stats->getMean(); // 3.0
```

## `RunningVariance` Helper

The `Pipeline\Helper\RunningVariance` class holds the statistical results. It uses Welford's online algorithm for high efficiency.

### Key Methods

-   `getCount(): int`: Number of values.
-   `getMean(): float`: Arithmetic mean.
-   `getVariance(): float`: Sample variance.
-   `getStandardDeviation(): float`: Sample standard deviation.
-   `getMin(): float`: Minimum value.
-   `getMax(): float`: Maximum value.

### Merging Statistics

You can merge `RunningVariance` instances, useful for combining results from different sources.

```php
$stats1 = take($source1)->finalVariance();
$stats2 = take($source2)->finalVariance();

$combinedStats = new RunningVariance($stats1, $stats2);
```
