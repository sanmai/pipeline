# Statistical Methods

These methods perform statistical analysis on numeric pipeline data. Both build on the `RunningVariance` helper, which computes statistics in a single pass using [Welford's online algorithm](https://en.wikipedia.org/wiki/Algorithms_for_calculating_variance#Welford's_online_algorithm)—numerically stable and able to handle any number of data points in constant memory.

## `finalVariance()`

Consumes the pipeline and calculates a comprehensive set of statistics.

**Signature**: `finalVariance(?callable $castFunc = null, ?RunningVariance $variance = null): RunningVariance`

- `$castFunc`: A callback converting each value to `?float`. Defaults to `floatval`. Return `null` to exclude a value from the statistics.
- `$variance`: An optional, pre-initialized `RunningVariance` to continue counting into.

**Behavior**:

- This is a terminal operation returning a `RunningVariance` object.
- Values for which `$castFunc` returns `null` are not counted.

**Examples**:

```php
// Basic statistics
$stats = take([1, 2, 3, 4, 5])->finalVariance();
$stats->getCount();              // 5
$stats->getMean();               // 3.0
$stats->getVariance();           // 2.5
$stats->getStandardDeviation();  // ~1.58
$stats->getMin();                // 1.0
$stats->getMax();                // 5.0

// Statistics for a specific field
$stats = take($users)->finalVariance(fn($user) => $user['age']);

// Mixed data: skip non-numeric values
$stats = take(['1', 'abc', 2, null, 3.5])
    ->finalVariance(fn($x) => is_numeric($x) ? (float) $x : null);
$stats->getCount(); // 3

// Continue from existing statistics
$initialStats = take($firstBatch)->finalVariance();
$combinedStats = take($secondBatch)->finalVariance(null, $initialStats);
```

## `runningVariance()`

Observes values as they pass through, updating statistics without consuming the pipeline.

**Signature**: `runningVariance(?RunningVariance &$variance, ?callable $castFunc = null): self`

- `&$variance`: A reference to a `RunningVariance`; created for you when `null`.
- `$castFunc`: Same as in `finalVariance()`.

**Behavior**:

- Non-terminal: statistics accumulate lazily as elements flow through, and can be inspected at any point.
- Several `runningVariance()` stages can observe different aspects of the same stream, each with its own cast callback; values for which the callback returns `null` are excluded from that particular computation.

**Examples**:

```php
$stats = null;
$processedData = take([1, 2, 3, 4, 5])
    ->runningVariance($stats)
    ->map(fn($x) => $x * 2)
    ->toList();

$stats->getMean(); // 3.0

// Two independent computations over one stream
take($orders)
    ->runningVariance($shipped, fn($order) => $order->isShipped() ? $order->getTotal() : null)
    ->runningVariance($paid, fn($order) => $order->isPaid() ? $order->getTotal() : null)
    ->each($processOrder);
```

## The `RunningVariance` Helper Class

`Pipeline\Helper\RunningVariance` holds the accumulated statistics:

- `getCount(): int`: The number of observed values.
- `getMean(): float`: The arithmetic mean.
- `getVariance(): float`: The sample variance (with [Bessel's correction](https://en.wikipedia.org/wiki/Bessel%27s_correction)).
- `getStandardDeviation(): float`: The sample standard deviation.
- `getMin(): float`: The smallest observed value.
- `getMax(): float`: The largest observed value.
- `observe(float $value): float`: Feed in a value directly.

With no observed values, `getMean()`, `getVariance()`, `getMin()`, and `getMax()` return `NAN`; with a single value, the variance is `0.0`.

### Merging Statistics

The constructor merges existing instances, which is useful for parallel processing or combining batches: statistics can be computed independently—even on different machines—and combined afterwards without revisiting the data.

```php
use Pipeline\Helper\RunningVariance;

$stats1 = take($source1)->finalVariance();
$stats2 = take($source2)->finalVariance();

$overall = new RunningVariance($stats1, $stats2);
```
