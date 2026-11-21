# Statistical Methods

These methods compute statistics for numeric pipeline data. Both methods use the `RunningVariance` helper. This helper computes statistics in a single pass with [Welford's online algorithm](https://en.wikipedia.org/wiki/Algorithms_for_calculating_variance#Welford's_online_algorithm). The algorithm is numerically stable and processes any number of data points in constant memory.

## `finalVariance()`

This method consumes the pipeline and computes its statistics.

**Signature**: `finalVariance(?callable $castFunc = null, ?RunningVariance $variance = null): RunningVariance`

- `$castFunc`: A callback converting each value to `?float`. Defaults to `floatval`. Return `null` to exclude a value from the statistics.
- `$variance`: An optional existing `RunningVariance`. The method adds new values to its statistics.

**Behavior**:

- This is a terminal operation. It returns a `RunningVariance` object.
- The method ignores values when `$castFunc` returns `null` for them.

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

This method updates statistics with each value in the stream. It does not consume the pipeline.

**Signature**: `runningVariance(?RunningVariance &$variance, ?callable $castFunc = null): self`

- `&$variance`: A reference to a `RunningVariance`. When it is `null`, the method creates a new instance.
- `$castFunc`: Same as in `finalVariance()`.

**Behavior**:

- This is a non-terminal operation. Statistics are updated lazily during iteration. You can read them at any point.
- Several `runningVariance()` stages can compute statistics for different parts of the same stream. Each stage uses a separate cast callback. When a callback returns `null` for a value, only the statistics of that stage ignore the value.

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

`Pipeline\Helper\RunningVariance` stores the accumulated statistics:

- `getCount(): int`: The number of observed values.
- `getMean(): float`: The arithmetic mean.
- `getVariance(): float`: The sample variance (with [Bessel's correction](https://en.wikipedia.org/wiki/Bessel%27s_correction)).
- `getStandardDeviation(): float`: The sample standard deviation.
- `getMin(): float`: The smallest observed value.
- `getMax(): float`: The largest observed value.
- `observe(float $value): float`: Adds a value directly and returns it.

With no observed values, `getMean()`, `getVariance()`, `getMin()`, and `getMax()` return `NAN`. With a single value, the variance is `0.0`.

### Merging Statistics

The constructor merges existing instances. Use it for parallel processing or to combine batches. You can compute statistics independently, also on different machines, and merge them later without a second pass over the data.

```php
use Pipeline\Helper\RunningVariance;

$stats1 = take($source1)->finalVariance();
$stats2 = take($source2)->finalVariance();

$overall = new RunningVariance($stats1, $stats2);
```
