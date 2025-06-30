# Helper Functions

Top-level functions in the `Pipeline` namespace that provide convenient ways to create and initialize pipelines.

## Function Reference

### `map(?callable $func = null): Standard`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Pipeline creation |
> | **Terminal?** | No |
> | **When to Use** | To create a pipeline from a generator function |
> | **Key Behavior** | Empty if no callback; single value if callback doesn't yield |

Creates a pipeline with an optional generator callback.

**Parameters:**
- `$func` (?callable): Optional generator function or value-returning function

**Returns:** Pipeline\Standard instance

**Examples:**

```php
use function Pipeline\map;

// Empty pipeline
$pipeline = map();

// From generator
$pipeline = map(function() {
    for ($i = 1; $i <= 5; $i++) {
        yield $i;
    }
});

// Infinite sequence
$fibonacci = map(function() {
    $a = 0;
    $b = 1;
    while (true) {
        yield $a;
        [$a, $b] = [$b, $a + $b];
    }
});

// From single value function
$pipeline = map(fn() => 'hello');
// Contains: ['hello']

// Reading file lazily
$lines = map(function() {
    $file = fopen('data.txt', 'r');
    while (($line = fgets($file)) !== false) {
        yield trim($line);
    }
    fclose($file);
});

// Random number generator
$random = map(function() {
    while (true) {
        yield random_int(1, 100);
    }
})->slice(0, 10);  // Take 10 random numbers
```

### `take(?iterable $input = null, iterable ...$inputs): Standard`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Pipeline creation |
> | **Terminal?** | No |
> | **When to Use** | To create a pipeline from existing data (arrays, iterators, etc.) |
> | **Key Behavior** | Concatenates multiple inputs sequentially |

Creates a pipeline from one or more iterables.

**Parameters:**
- `$input` (?iterable): Primary data source
- `...$inputs` (iterable): Additional data sources to append

**Returns:** Pipeline\Standard instance

**Examples:**

```php
use function Pipeline\take;

// Single source
$pipeline = take([1, 2, 3, 4, 5]);

// Multiple sources
$pipeline = take(
    [1, 2, 3],
    [4, 5, 6],
    [7, 8, 9]
);
// Contains: 1, 2, 3, 4, 5, 6, 7, 8, 9

// Mixed iterables
$pipeline = take(
    range(1, 3),
    new ArrayIterator([4, 5, 6]),
    generateMore()  // Generator function
);

// Empty pipeline
$pipeline = take();

// Conditional sources
$pipeline = take($primaryData);
if ($includeArchived) {
    $pipeline = take($pipeline, $archivedData);
}

// From file objects
$combined = take(
    new SplFileObject('file1.txt'),
    new SplFileObject('file2.txt')
);
```

### `fromArray(array $input): Standard`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Pipeline creation |
> | **Terminal?** | No |
> | **When to Use** | When you have an array and want type safety |
> | **Key Behavior** | Equivalent to take() but requires array type |

Creates a pipeline specifically from an array.

**Parameters:**
- `$input` (array): Source array

**Returns:** Pipeline\Standard instance

**Examples:**

```php
use function Pipeline\fromArray;

// Simple array
$pipeline = fromArray([1, 2, 3, 4, 5]);

// Associative array
$pipeline = fromArray([
    'name' => 'Alice',
    'age' => 30,
    'city' => 'NYC'
]);

// Nested arrays
$pipeline = fromArray([
    ['id' => 1, 'value' => 'A'],
    ['id' => 2, 'value' => 'B'],
    ['id' => 3, 'value' => 'C']
]);

// Empty array
$pipeline = fromArray([]);

// Type safety
function processArray(array $data) {
    return fromArray($data)
        ->map(fn($x) => $x * 2)
        ->filter(fn($x) => $x > 10)
        ->toList();
}
```

### `fromValues(...$values): Standard`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Pipeline creation |
> | **Terminal?** | No |
> | **When to Use** | To create a pipeline from individual arguments |
> | **Key Behavior** | Each argument becomes a pipeline element |

Creates a pipeline from individual values.

**Parameters:**
- `...$values` (mixed): Individual values

**Returns:** Pipeline\Standard instance

**Examples:**

```php
use function Pipeline\fromValues;

// Multiple values
$pipeline = fromValues(1, 2, 3, 4, 5);

// Mixed types
$pipeline = fromValues('hello', 42, true, null, [1, 2, 3]);

// Single value
$pipeline = fromValues('solo');

// Objects
$pipeline = fromValues(
    new DateTime('2024-01-01'),
    new DateTime('2024-01-02'),
    new DateTime('2024-01-03')
);

// Building from variables
$a = 10;
$b = 20;
$c = 30;
$pipeline = fromValues($a, $b, $c);

// Dynamic construction
function buildPipeline(...$args) {
    return fromValues(...$args)
        ->filter()  // Remove falsy
        ->map('strval')  // Convert to strings
        ->toList();
}
```

### `zip(iterable $base, iterable ...$inputs): Standard`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Pipeline creation |
> | **Terminal?** | No |
> | **When to Use** | To combine parallel arrays/iterables into tuples |
> | **Key Behavior** | Shorter iterables are padded with null |

Creates a pipeline by combining multiple iterables element by element.

**Parameters:**
- `$base` (iterable): Base iterable
- `...$inputs` (iterable): Additional iterables to combine

**Returns:** Pipeline\Standard instance

**Examples:**

```php
use function Pipeline\zip;

// Basic zip
$result = zip(
    ['a', 'b', 'c'],
    [1, 2, 3]
)->toList();
// Result: [['a', 1], ['b', 2], ['c', 3]]

// Multiple iterables
$colors = zip(
    ['red', 'green', 'blue'],
    [255, 0, 0],
    [0, 255, 0],
    [0, 0, 255]
)->map(fn($data) => [
    'name' => $data[0],
    'r' => $data[1],
    'g' => $data[2],
    'b' => $data[3]
])->toList();

// Uneven lengths
$result = zip(
    [1, 2, 3, 4, 5],
    ['a', 'b', 'c']
)->toList();
// Result: [[1, 'a'], [2, 'b'], [3, 'c'], [4, null], [5, null]]

// Create associations
$keys = ['name', 'age', 'city'];
$values = ['Alice', 30, 'NYC'];
$record = zip($keys, $values)
    ->fold([], fn($acc, $pair) => [...$acc, $pair[0] => $pair[1]]);
// Result: ['name' => 'Alice', 'age' => 30, 'city' => 'NYC']

// Parallel iteration
$prices = [10.99, 25.50, 15.75];
$quantities = [2, 1, 3];
$totals = zip($prices, $quantities)
    ->map(fn($pair) => $pair[0] * $pair[1])
    ->toList();
// Result: [21.98, 25.50, 47.25]
```

## Usage Patterns

### Choosing the Right Function

```php
// Use map() for generators
$generated = map(function() {
    yield from generateData();
});

// Use take() for existing data
$processed = take($existingData)
    ->filter($predicate)
    ->map($transformer);

// Use fromArray() when type matters
function processUserArray(array $users) {
    return fromArray($users)
        ->filter(fn($u) => $u['active'])
        ->toList();
}

// Use fromValues() for literals
$constants = fromValues(
    PHP_INT_MAX,
    PHP_FLOAT_MAX,
    PHP_VERSION
);

// Use zip() for parallel data
$results = zip($inputs, $outputs, $expected)
    ->map(fn($test) => [
        'input' => $test[0],
        'output' => $test[1],
        'expected' => $test[2],
        'passed' => $test[1] === $test[2]
    ]);
```

### Combining Functions

```php
// Start with generator, add more data
$pipeline = map(function() {
    yield from range(1, 5);
})->append(take([6, 7, 8]));

// Zip then process
$processed = zip($names, $scores)
    ->map(fn($pair) => ['name' => $pair[0], 'score' => $pair[1]])
    ->filter(fn($x) => $x['score'] >= 80)
    ->toList();

// Multiple data sources
$all = take(
    fromValues(1, 2, 3),
    fromArray([4, 5, 6]),
    map(fn() => yield from [7, 8, 9])
);
```

### Function Composition

```php
// Pipeline factory
function createPipeline($source) {
    return match(true) {
        is_array($source) => fromArray($source),
        is_iterable($source) => take($source),
        is_callable($source) => map($source),
        default => fromValues($source)
    };
}

// Reusable transformers
function integers() {
    return map(function() {
        $i = 1;
        while (true) {
            yield $i++;
        }
    });
}

function evens() {
    return integers()
        ->filter(fn($x) => $x % 2 === 0);
}

function squares() {
    return integers()
        ->map(fn($x) => $x * $x);
}
```

### Memory-Efficient Patterns

```php
// Process large dataset in chunks
function processInBatches($filename, $batchSize = 1000) {
    return map(function() use ($filename) {
        $handle = fopen($filename, 'r');
        while (($line = fgets($handle)) !== false) {
            yield json_decode($line, true);
        }
        fclose($handle);
    })
    ->chunk($batchSize)
    ->each(fn($batch) => processBatch($batch));
}

// Infinite stream with limit
$sample = map(function() {
    while (true) {
        yield generateComplexData();
    }
})
->slice(0, 100)  // Only take what's needed
->toList();
```

## Best Practices

1. **Use `map()` for generators** - When creating data on-the-fly
2. **Use `take()` for existing iterables** - When you have data ready
3. **Use `fromArray()` for type safety** - When function expects array
4. **Use `fromValues()` for known values** - When creating from literals
5. **Use `zip()` for parallel processing** - When combining related data

## Next Steps

- [Statistics](statistics.md) - Statistical analysis with RunningVariance
- [Advanced Usage](../advanced/complex-pipelines.md) - Complex pipeline patterns