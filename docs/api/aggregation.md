# Aggregation Methods

Methods that reduce pipeline elements to single values. These are terminal operations that consume the pipeline.

## Core Reduction Methods

### `reduce(?callable $func = null, $initial = null)`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Aggregation |
> | **Terminal?** | **Yes** |
> | **When to Use** | Quick summation only (prefer `fold()` for everything else) |
> | **Key Behavior** | Implicit initial value can cause surprises |

Reduces elements to a single value, acting as a convenient shortcut for `array_reduce`. It defaults to summation when no callback is provided.

> **Note:** For greater clarity and type safety in your aggregations, it is highly recommended to use `fold()` instead, as it requires an explicit initial value.

**Parameters:**
- `$func` (?callable): Reduction function. If null, sums numeric values.
- `$initial` (mixed): Initial value for the accumulator (default: 0 if func is null)

**Returns:** mixed - The final reduced value

**Callback signature:** `function(mixed $carry, mixed $item): mixed`

**When to Use:**
`reduce()` should be seen as a convenience shortcut, particularly for quick summation. Its implicit default initial value can make it less predictable than `fold()`. Use it only for simple cases where the implicit behavior is acceptable. For all other aggregations, use `fold()` for better clarity and type safety.

**Performance Notes:**
- Terminal operation - consumes the entire pipeline
- Uses `array_reduce()` for arrays (optimized)
- Memory efficient for large datasets (processes one element at a time)

**Common Patterns:**
- Building lookup tables: `reduce(fn($acc, $x) => [...$acc, $x['id'] => $x], [])`
- Counting occurrences: `reduce(fn($counts, $x) => [...$counts, $x => ($counts[$x] ?? 0) + 1], [])`
- Finding max with metadata: `reduce(fn($best, $x) => $x['score'] > ($best['score'] ?? 0) ? $x : $best)`

**Examples:**

```php
// Default summation
$sum = take([1, 2, 3, 4, 5])->reduce();
// Result: 15

// With initial value
$sum = take([1, 2, 3])->reduce(null, 10);
// Result: 16 (10 + 1 + 2 + 3)

// Product calculation
$product = take([2, 3, 4])
    ->reduce(fn($carry, $item) => $carry * $item, 1);
// Result: 24

// String concatenation
$string = take(['Hello', ' ', 'World'])
    ->reduce(fn($carry, $item) => $carry . $item, '');
// Result: 'Hello World'

// Building an array
$indexed = take(['a', 'b', 'c'])
    ->reduce(function($carry, $item) {
        $carry[$item] = strlen($item);
        return $carry;
    }, []);
// Result: ['a' => 1, 'b' => 1, 'c' => 1]

// Count occurrences
$counts = take(['a', 'b', 'a', 'c', 'b', 'a'])
    ->reduce(function($counts, $item) {
        $counts[$item] = ($counts[$item] ?? 0) + 1;
        return $counts;
    }, []);
// Result: ['a' => 3, 'b' => 2, 'c' => 1]

// Find maximum with details
$data = [
    ['name' => 'Alice', 'score' => 85],
    ['name' => 'Bob', 'score' => 92],
    ['name' => 'Charlie', 'score' => 88]
];
$highest = take($data)
    ->reduce(fn($best, $item) => 
        $item['score'] > ($best['score'] ?? 0) ? $item : $best
    , []);
// Result: ['name' => 'Bob', 'score' => 92]
```

### `fold($initial, ?callable $func = null)`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Aggregation |
> | **Terminal?** | **Yes** |
> | **When to Use** | **Recommended for all aggregations** due to type safety |
> | **Key Behavior** | Requires explicit initial value, making it predictable |

Like `reduce()` but requires an initial value. More predictable for type safety.

**Parameters:**
- `$initial` (mixed): Required initial value for the accumulator
- `$func` (?callable): Reduction function. If null, sums numeric values.

**Returns:** mixed - The final reduced value (same type as $initial if consistent)

**Callback signature:** `function(mixed $carry, mixed $item): mixed`

## Choosing Between `fold()` and `reduce()`

While both methods perform aggregations, they represent different design philosophies.

**In general, prefer `fold()` over `reduce()` for maximum clarity and type safety.**

### `fold($initial, ?callable $func = null)` (Recommended)

`fold()` is the more robust and explicit of the two methods. It **requires** you to provide an `$initial` value. This removes ambiguity and prevents a class of subtle bugs, especially when the final aggregated type is different from the items in the pipeline (e.g., building an array from a stream of numbers). Because the starting value is always explicit, the behavior is predictable and type-safe.

### `reduce(?callable $func = null, $initial = null)`

`reduce()` should be seen as a convenience shortcut for PHP's `array_reduce`, particularly for the most common aggregation: summation. When called with no arguments, it defaults to summing numeric values. However, its use of an implicit, "magic" starting value (`null`) can make it less predictable than `fold()` in complex scenarios. Use it when you need a quick sum and the data is simple. For all other cases, `fold()` is the safer choice.

```php
// Quick summation with reduce() - acceptable for simple cases
$sum = take([1, 2, 3])->reduce(); // Implicit initial value of 0

// Better: Explicit initial value with fold()
$sum = take([1, 2, 3])->fold(0); // Clear: we start from 0
$product = take([1, 2, 3])->fold(1, fn($acc, $x) => $acc * $x); // Clear: we start from 1

// Complex aggregations - always use fold()
$stats = take($numbers)->fold(
    ['sum' => 0, 'count' => 0, 'min' => INF, 'max' => -INF],
    function($acc, $num) {
        $acc['sum'] += $num;
        $acc['count']++;
        $acc['min'] = min($acc['min'], $num);
        $acc['max'] = max($acc['max'], $num);
        return $acc;
    }
);
```

**Example: Type Safety with fold()**

```php
// reduce() with implicit initial value - less type-safe
$sum = take([1, 2, 3])->reduce(); // Initial value is 0 (inferred)

// fold() with explicit initial value - more type-safe
$sum = take([1, 2, 3])->fold(0); // Explicitly start with integer 0
$product = take([1, 2, 3])->fold(1, fn($acc, $x) => $acc * $x); // Start with 1

// Building different type - fold() is clearer
$csv = take($records)
    ->fold('', fn($csv, $record) => $csv . implode(',', $record) . "\n");
// Initial value '' makes it clear we're building a string

// Complex accumulator - fold() ensures correct structure
$stats = take($numbers)
    ->fold(['sum' => 0, 'count' => 0], function($acc, $num) {
        $acc['sum'] += $num;
        $acc['count']++;
        return $acc;
    });
```

**Examples:**

```php
// Basic fold with sum
$sum = take([1, 2, 3])->fold(0);
// Result: 6

// Fold with custom function
$result = take([1, 2, 3])
    ->fold([], fn($arr, $item) => [...$arr, $item * 2]);
// Result: [2, 4, 6]

// Type-safe accumulation
$total = take($orders)
    ->fold(0.0, fn($sum, $order) => $sum + $order['amount']);
// Always returns float

// Building complex structure
$grouped = take($items)
    ->fold([], function($groups, $item) {
        $key = $item['category'];
        $groups[$key][] = $item;
        return $groups;
    });

// Safe string building
$csv = take($records)
    ->fold('', fn($csv, $record) => 
        $csv . implode(',', $record) . "\n"
    );

// Nested data extraction
$allTags = take($posts)
    ->fold([], fn($tags, $post) => 
        array_merge($tags, $post['tags'] ?? [])
    );
```

## Statistical Aggregations

### `min()`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Terminal operation |
> | **Terminal?** | Yes |
> | **When to Use** | To find the smallest value in a collection |
> | **Key Behavior** | Returns null for empty pipelines, uses PHP comparison rules |

Finds the minimum value using standard PHP comparison.

**Returns:** mixed|null - Minimum value or null for empty pipeline

**Examples:**

```php
// Numeric minimum
$min = take([5, 2, 8, 1, 9])->min();
// Result: 1

// String minimum (alphabetical)
$min = take(['banana', 'apple', 'cherry'])->min();
// Result: 'apple'

// Empty pipeline
$min = take([])->min();
// Result: null

// With transformation
$min = take($products)
    ->map(fn($p) => $p['price'])
    ->min();

// Date minimum
$dates = ['2024-01-15', '2024-01-01', '2024-01-30'];
$earliest = take($dates)->min();
// Result: '2024-01-01'

// Object comparison (using __toString or comparison operators)
$minObject = take($comparableObjects)->min();
```

### `max()`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Terminal operation |
> | **Terminal?** | Yes |
> | **When to Use** | To find the largest value in a collection |
> | **Key Behavior** | Returns null for empty pipelines, uses PHP comparison rules |

Finds the maximum value using standard PHP comparison.

**Returns:** mixed|null - Maximum value or null for empty pipeline

**Examples:**

```php
// Numeric maximum
$max = take([5, 2, 8, 1, 9])->max();
// Result: 9

// String maximum
$max = take(['banana', 'apple', 'cherry'])->max();
// Result: 'cherry'

// With transformation
$highest = take($scores)
    ->map(fn($s) => $s['value'])
    ->max();

// Complex comparison
$latest = take($events)
    ->map(fn($e) => $e['timestamp'])
    ->max();

// Mixed types (PHP comparison rules apply)
$max = take([1, '2', 3.0])->max();
// Result: 3.0
```

### `count()`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Terminal operation |
> | **Terminal?** | Yes |
> | **When to Use** | To get the total number of elements |
> | **Key Behavior** | Consumes entire pipeline, implements Countable interface |

Counts the number of elements in the pipeline. Terminal operation.

**Returns:** int - Number of elements

**Examples:**

```php
// Basic count
$count = take([1, 2, 3, 4, 5])->count();
// Result: 5

// Count after filtering
$count = take(range(1, 100))
    ->filter(fn($x) => $x % 2 === 0)
    ->count();
// Result: 50

// Count with generators
$count = take(function() {
    for ($i = 0; $i < 1000000; $i++) {
        yield $i;
    }
})->filter(fn($x) => $x < 1000)->count();
// Result: 1000

// Empty pipeline
$count = take([])->count();
// Result: 0

// Count unique values
$unique = take([1, 2, 2, 3, 3, 3])
    ->flip()->flip()  // Deduplicate
    ->count();
// Result: 3
```

## Statistical Analysis

### `finalVariance(?callable $castFunc = null, ?RunningVariance $variance = null)`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Terminal operation |
> | **Terminal?** | Yes |
> | **When to Use** | To get comprehensive statistics (mean, variance, min/max) |
> | **Key Behavior** | Returns RunningVariance object with all statistics |

Calculates complete statistical information for numeric data.

**Parameters:**
- `$castFunc` (?callable): Function to convert values to float (default: floatval)
- `$variance` (?RunningVariance): Optional pre-initialized variance calculator

**Returns:** RunningVariance - Object containing statistical data

**Examples:**

```php
use Pipeline\Helper\RunningVariance;

// Basic statistics
$stats = take([1, 2, 3, 4, 5])->finalVariance();
echo $stats->getCount();           // 5
echo $stats->getMean();            // 3.0
echo $stats->getVariance();        // 2.5
echo $stats->getStandardDeviation(); // ~1.58
echo $stats->getMin();             // 1.0
echo $stats->getMax();             // 5.0

// With custom casting
$stats = take(['1.5', '2.5', '3.5'])
    ->finalVariance(fn($x) => (float)$x);
echo $stats->getMean(); // 2.5

// Ignore non-numeric values
$stats = take([1, 'text', 2, null, 3])
    ->finalVariance(fn($x) => is_numeric($x) ? (float)$x : null);
echo $stats->getCount(); // 3 (only numeric values)

// Calculate statistics for specific field
$scores = [
    ['name' => 'Alice', 'score' => 85],
    ['name' => 'Bob', 'score' => 92],
    ['name' => 'Charlie', 'score' => 88]
];
$stats = take($scores)
    ->finalVariance(fn($x) => $x['score']);
echo $stats->getMean(); // 88.33...

// Continuing from existing statistics
$variance1 = take($dataset1)->finalVariance();
$combined = take($dataset2)->finalVariance(null, $variance1);
// $combined now has statistics for both datasets
```

## Advanced Aggregation Patterns

### Custom Aggregations

```php
// Multiple aggregations in one pass
$result = take($sales)
    ->reduce(function($acc, $sale) {
        $acc['total'] += $sale['amount'];
        $acc['count']++;
        $acc['byCategory'][$sale['category']] = 
            ($acc['byCategory'][$sale['category']] ?? 0) + $sale['amount'];
        return $acc;
    }, ['total' => 0, 'count' => 0, 'byCategory' => []]);

// Percentile calculation
function percentile($data, $p) {
    $sorted = take($data)->toList();
    sort($sorted);
    $index = ($p / 100) * (count($sorted) - 1);
    $lower = floor($index);
    $upper = ceil($index);
    $weight = $index - $lower;
    return $sorted[$lower] * (1 - $weight) + $sorted[$upper] * $weight;
}

// Running aggregations with side effects
$totals = [];
take($transactions)
    ->reduce(function($balance, $transaction) use (&$totals) {
        $balance += $transaction['amount'];
        $totals[] = ['date' => $transaction['date'], 'balance' => $balance];
        return $balance;
    }, 0);
```

### Grouping and Aggregating

```php
// Group by key and sum
$grouped = take($items)
    ->reduce(function($groups, $item) {
        $key = $item['category'];
        $groups[$key] = ($groups[$key] ?? 0) + $item['value'];
        return $groups;
    }, []);

// Group with multiple aggregations
$summary = take($orders)
    ->reduce(function($acc, $order) {
        $customer = $order['customer_id'];
        if (!isset($acc[$customer])) {
            $acc[$customer] = [
                'count' => 0,
                'total' => 0,
                'items' => []
            ];
        }
        $acc[$customer]['count']++;
        $acc[$customer]['total'] += $order['amount'];
        $acc[$customer]['items'][] = $order['item_id'];
        return $acc;
    }, []);
```

### Early Termination Patterns

```php
// Find first matching element
$found = take($largeDataset)
    ->reduce(function($found, $item) {
        if ($found !== null) return $found;
        return matchesCriteria($item) ? $item : null;
    }, null);

// Check if any/all match
$hasError = take($results)
    ->reduce(fn($has, $item) => $has || $item['status'] === 'error', false);

$allValid = take($inputs)
    ->reduce(fn($valid, $item) => $valid && validate($item), true);
```

## Performance Considerations

1. **Terminal operations consume the pipeline** - Cannot reuse after aggregation
2. **Use `runningCount()` for counting** during transformation chains
3. **Array optimization** - `count()` uses native `count()` for arrays
4. **Memory efficiency** - Aggregations process lazily, consuming one element at a time
5. **Type consistency** - Use `fold()` when type safety is important

## Next Steps

- [Collection Methods](collection.md) - Converting pipelines to arrays
- [Utility Methods](utility.md) - Helper methods for common operations