# Method Index

Quick reference for all Pipeline methods and helper functions.

## Creation Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `new Standard(?iterable $input = null)` | Create new pipeline | Pipeline |
| `take(?iterable $input = null, ...$inputs)` | Create from iterables | Pipeline |
| `fromArray(array $input)` | Create from array | Pipeline |
| `fromValues(...$values)` | Create from values | Pipeline |
| `map(?callable $func = null)` | Create with generator | Pipeline |
| `zip(iterable $base, ...$inputs)` | Combine iterables | Pipeline |

## Data Addition

| Method | Description | Returns |
|--------|-------------|---------|
| `append(?iterable $values = null)` | Add to end | Pipeline |
| `push(...$vector)` | Add values to end | Pipeline |
| `prepend(?iterable $values = null)` | Add to beginning | Pipeline |
| `unshift(...$vector)` | Add values to beginning | Pipeline |

## Transformation

| Method | Description | Returns |
|--------|-------------|---------|
| `map(?callable $func = null)` | Transform elements (handles generators) | Pipeline |
| `cast(?callable $func = null)` | Transform elements (single values) | Pipeline |
| `flatten()` | Flatten nested iterables | Pipeline |
| `unpack(?callable $func = null)` | Unpack arrays as arguments | Pipeline |
| `chunk(int $length, bool $preserve_keys = false)` | Split into chunks | Pipeline |
| `slice(int $offset, ?int $length = null)` | Extract portion | Pipeline |

## Filtering

| Method | Description | Returns |
|--------|-------------|---------|
| `filter(?callable $func = null, bool $strict = false)` | Remove elements | Pipeline |
| `skipWhile(callable $predicate)` | Skip from start while true | Pipeline |

## Collection/Array Operations

| Method | Description | Returns |
|--------|-------------|---------|
| `keys()` | Extract keys only | Pipeline |
| `values()` | Extract values only | Pipeline |
| `flip()` | Swap keys and values | Pipeline |
| `tuples()` | Convert to [key, value] pairs | Pipeline |
| `zip(iterable ...$inputs)` | Combine element by element | Pipeline |

## Terminal Operations - Arrays

| Method | Description | Returns |
|--------|-------------|---------|
| `toList()` | Convert to indexed array | array |
| `toAssoc()` | Convert preserving keys | array |

## Terminal Operations - Aggregation

| Method | Description | Returns |
|--------|-------------|---------|
| `reduce(?callable $func = null, $initial = null)` | Reduce to single value | mixed |
| `fold($initial, ?callable $func = null)` | Reduce with required initial | mixed |
| `count()` | Count elements | int |
| `min()` | Find minimum value | mixed\|null |
| `max()` | Find maximum value | mixed\|null |
| `each(callable $func, bool $discard = true)` | Iterate with callback | void |

## Statistical Operations

| Method | Description | Returns |
|--------|-------------|---------|
| `runningVariance(?RunningVariance &$variance, ?callable $castFunc = null)` | Track statistics | Pipeline |
| `finalVariance(?callable $castFunc = null, ?RunningVariance $variance = null)` | Calculate final statistics | RunningVariance |
| `runningCount(?int &$count)` | Count while processing | Pipeline |

## Utility Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `reservoir(int $size, ?callable $weightFunc = null)` | Random sampling | array |
| `stream()` | Force lazy evaluation | Pipeline |
| `getIterator()` | Get iterator (IteratorAggregate) | Traversable |

## RunningVariance Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `observe(float $value)` | Add observation | float |
| `getCount()` | Number of observations | int |
| `getMean()` | Average value | float |
| `getVariance()` | Sample variance | float |
| `getStandardDeviation()` | Sample standard deviation | float |
| `getMin()` | Minimum value | float |
| `getMax()` | Maximum value | float |

## Usage Quick Reference

### Basic Pipeline
```php
take($data)
    ->filter(fn($x) => $x > 0)
    ->map(fn($x) => $x * 2)
    ->toList();
```

### File Processing
```php
take(new SplFileObject('file.txt'))
    ->map('trim')
    ->filter()
    ->toList();
```

### Statistical Analysis
```php
$stats = take($numbers)->finalVariance();
echo $stats->getMean();
echo $stats->getStandardDeviation();
```

### Chunked Processing
```php
take($largeDataset)
    ->chunk(1000)
    ->each(fn($batch) => processBatch($batch));
```

### Random Sampling
```php
$sample = take($population)
    ->reservoir(100);  // Sample 100 items
```

### Combining Sources
```php
$result = zip(
    ['a', 'b', 'c'],
    [1, 2, 3]
)->toList();
// [[a', 1], ['b', 2], ['c', 3]]
```

## Method Categories

### Non-Terminal Operations
Return Pipeline instance for chaining:
- All creation methods
- All transformation methods
- All filtering methods
- Collection operations (keys, values, flip, tuples)
- Utility methods (stream, runningCount, runningVariance)

### Terminal Operations
Consume pipeline and return final result:
- `toList()`, `toAssoc()`
- `reduce()`, `fold()`
- `count()`, `min()`, `max()`
- `each()`
- `finalVariance()`
- `reservoir()`

### Reference Operations
Modify external variables by reference:
- `runningCount(&$count)`
- `runningVariance(&$variance)`

## Performance Notes

- **Array Optimized**: `filter()`, `cast()`, `chunk()`, `count()` use native array functions when possible
- **Memory Efficient**: `stream()`, generators, and file iterators process lazily
- **Type Safe**: `fromArray()` ensures array input, `fold()` requires initial value

## See Also

- [API Documentation](../api/creation.md) - Detailed method documentation
- [Examples](../quickstart/examples.md) - Code examples
- [Best Practices](../advanced/best-practices.md) - Usage guidelines