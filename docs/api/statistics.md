# Statistical Analysis

Methods and classes for performing statistical calculations on pipeline data.

## RunningVariance Class

The `Pipeline\Helper\RunningVariance` class implements Welford's online algorithm for calculating variance and other statistics in a single pass.

### Class Methods

#### `__construct(RunningVariance ...$spiesToMerge)`

Creates a new RunningVariance instance, optionally merging existing instances.

**Parameters:**
- `...$spiesToMerge` (RunningVariance): Existing instances to merge

**Examples:**

```php
use Pipeline\Helper\RunningVariance;

// Empty instance
$variance = new RunningVariance();

// Merge existing statistics
$combined = new RunningVariance($stats1, $stats2, $stats3);
```

#### `observe(float $value): float`

Records a new observation and updates statistics.

**Parameters:**
- `$value` (float): Value to observe

**Returns:** float - The observed value

**Examples:**

```php
$variance = new RunningVariance();
$variance->observe(10.5);
$variance->observe(12.3);
$variance->observe(11.8);
```

#### Statistical Getters

- `getCount(): int` - Number of observations
- `getMean(): float` - Arithmetic mean (returns NAN if no observations)
- `getVariance(): float` - Sample variance with Bessel's correction
- `getStandardDeviation(): float` - Sample standard deviation
- `getMin(): float` - Minimum observed value
- `getMax(): float` - Maximum observed value

**Examples:**

```php
$variance = new RunningVariance();
foreach ([1, 2, 3, 4, 5] as $value) {
    $variance->observe($value);
}

echo "Count: " . $variance->getCount() . "\n";              // 5
echo "Mean: " . $variance->getMean() . "\n";                // 3.0
echo "Variance: " . $variance->getVariance() . "\n";        // 2.5
echo "Std Dev: " . $variance->getStandardDeviation() . "\n"; // ~1.58
echo "Min: " . $variance->getMin() . "\n";                  // 1.0
echo "Max: " . $variance->getMax() . "\n";                  // 5.0
```

## Pipeline Statistical Methods

### `runningVariance(?RunningVariance &$variance, ?callable $castFunc = null)`

Observes values as they pass through the pipeline without consuming it.

**Parameters:**
- `&$variance` (?RunningVariance): Reference to variance instance (created if null)
- `$castFunc` (?callable): Function to convert values to float (default: floatval)

**Returns:** $this (Pipeline\Standard instance)

**Examples:**

```php
// Basic usage
$stats = null;
$processed = take([1, 2, 3, 4, 5])
    ->runningVariance($stats)
    ->map(fn($x) => $x * 2)
    ->toList();

echo "Original mean: " . $stats->getMean(); // 3.0
// $processed contains: [2, 4, 6, 8, 10]

// Custom value extraction
$orderStats = null;
$orders = take($orders)
    ->runningVariance($orderStats, fn($order) => $order['total'])
    ->filter(fn($order) => $order['status'] === 'completed')
    ->toList();

echo "Average order value: $" . $orderStats->getMean();

// Selective statistics
$validStats = null;
take($measurements)
    ->runningVariance($validStats, function($value) {
        // Only include positive values
        return $value > 0 ? (float)$value : null;
    })
    ->each(fn($x) => processValue($x));

// Multiple statistics
$priceStats = null;
$quantityStats = null;
$items = take($products)
    ->runningVariance($priceStats, fn($p) => $p['price'])
    ->runningVariance($quantityStats, fn($p) => $p['quantity'])
    ->toList();
```

### `finalVariance(?callable $castFunc = null, ?RunningVariance $variance = null)`

Calculates complete statistics for the pipeline. Terminal operation.

**Parameters:**
- `$castFunc` (?callable): Function to convert values to float (default: floatval)
- `$variance` (?RunningVariance): Optional pre-initialized variance instance

**Returns:** RunningVariance - Instance containing all statistics

**Examples:**

```php
// Simple statistics
$stats = take([10, 20, 30, 40, 50])->finalVariance();
echo "Mean: " . $stats->getMean() . "\n";        // 30.0
echo "StdDev: " . $stats->getStandardDeviation(); // ~15.81

// With filtering
$stats = take($data)
    ->filter(fn($x) => $x > 0)
    ->finalVariance();

// Custom extraction
$stats = take($records)
    ->finalVariance(fn($record) => $record['score']);

// Ignore invalid values
$stats = take(['1', '2', 'invalid', '3', null, '4'])
    ->finalVariance(function($value) {
        return is_numeric($value) ? (float)$value : null;
    });
echo $stats->getCount(); // 4 (only numeric values)

// Continue existing statistics
$phase1Stats = take($dataset1)->finalVariance();
$combinedStats = take($dataset2)->finalVariance(null, $phase1Stats);
// $combinedStats now includes both datasets
```

## Statistical Patterns

### Outlier Detection

```php
// Z-score based outlier detection
$stats = null;
$outliers = [];

take($data)
    ->runningVariance($stats)
    ->each(function($value) use ($stats, &$outliers) {
        if ($stats->getCount() > 30) {  // Need sufficient data
            $mean = $stats->getMean();
            $stdDev = $stats->getStandardDeviation();
            $zScore = abs(($value - $mean) / $stdDev);
            
            if ($zScore > 3) {  // 3 standard deviations
                $outliers[] = [
                    'value' => $value,
                    'z_score' => $zScore
                ];
            }
        }
    });

echo "Found " . count($outliers) . " outliers\n";
```

### Quality Control

```php
// Control chart monitoring
$stats = new RunningVariance();
$violations = [];

take($measurements)
    ->each(function($measurement) use ($stats, &$violations) {
        $stats->observe($measurement);
        
        if ($stats->getCount() >= 20) {
            $mean = $stats->getMean();
            $stdDev = $stats->getStandardDeviation();
            
            // Control limits
            $ucl = $mean + 3 * $stdDev;  // Upper control limit
            $lcl = $mean - 3 * $stdDev;  // Lower control limit
            
            if ($measurement > $ucl || $measurement < $lcl) {
                $violations[] = [
                    'value' => $measurement,
                    'type' => $measurement > $ucl ? 'above_ucl' : 'below_lcl'
                ];
            }
        }
    });
```

### Comparative Statistics

```php
// Compare groups
$groupStats = [];

take($data)
    ->each(function($item) use (&$groupStats) {
        $group = $item['group'];
        if (!isset($groupStats[$group])) {
            $groupStats[$group] = new RunningVariance();
        }
        $groupStats[$group]->observe($item['value']);
    });

// Display comparison
foreach ($groupStats as $group => $stats) {
    echo "Group $group:\n";
    echo "  Mean: " . $stats->getMean() . "\n";
    echo "  StdDev: " . $stats->getStandardDeviation() . "\n";
    echo "  Range: " . $stats->getMin() . " - " . $stats->getMax() . "\n";
}
```

### Time Series Analysis

```php
// Moving statistics window
class MovingStats {
    private array $window = [];
    private int $size;
    
    public function __construct(int $windowSize) {
        $this->size = $windowSize;
    }
    
    public function observe($value): RunningVariance {
        $this->window[] = $value;
        if (count($this->window) > $this->size) {
            array_shift($this->window);
        }
        
        $stats = new RunningVariance();
        foreach ($this->window as $v) {
            $stats->observe($v);
        }
        return $stats;
    }
}

$movingStats = new MovingStats(20);
$anomalies = [];

take($timeSeries)
    ->each(function($point) use ($movingStats, &$anomalies) {
        $stats = $movingStats->observe($point['value']);
        
        if ($stats->getCount() === 20) {
            $mean = $stats->getMean();
            $stdDev = $stats->getStandardDeviation();
            
            if (abs($point['value'] - $mean) > 2 * $stdDev) {
                $anomalies[] = $point;
            }
        }
    });
```

### Performance Metrics

```php
// Response time analysis
$stats = take($apiLogs)
    ->map(fn($log) => $log['response_time'])
    ->finalVariance();

$p50 = calculatePercentile($apiLogs, 50);  // Median
$p95 = calculatePercentile($apiLogs, 95);
$p99 = calculatePercentile($apiLogs, 99);

echo "Response Time Analysis:\n";
echo "Mean: " . round($stats->getMean(), 2) . "ms\n";
echo "StdDev: " . round($stats->getStandardDeviation(), 2) . "ms\n";
echo "P50: {$p50}ms\n";
echo "P95: {$p95}ms\n";
echo "P99: {$p99}ms\n";
echo "Max: " . round($stats->getMax(), 2) . "ms\n";
```

### Data Distribution

```php
// Coefficient of variation
$stats = take($values)->finalVariance();
$cv = ($stats->getStandardDeviation() / $stats->getMean()) * 100;
echo "Coefficient of Variation: " . round($cv, 2) . "%\n";

// Relative standard deviation
$rsd = $cv;  // Same as CV, different name
echo "Relative Standard Deviation: " . round($rsd, 2) . "%\n";

// Range analysis
$range = $stats->getMax() - $stats->getMin();
$relativeRange = ($range / $stats->getMean()) * 100;
echo "Range: $range (${relativeRange}% of mean)\n";
```

## Best Practices

1. **Use `runningVariance()` for monitoring** - Observe statistics without consuming
2. **Use `finalVariance()` for analysis** - When you need complete statistics
3. **Handle null values explicitly** - Return null from castFunc to skip
4. **Check count before using statistics** - Some metrics need minimum observations
5. **Consider memory for large datasets** - RunningVariance is memory-efficient

## Next Steps

- [Advanced Usage](../advanced/complex-pipelines.md) - Complex pipeline patterns
- [Performance Tips](../advanced/performance.md) - Optimization strategies