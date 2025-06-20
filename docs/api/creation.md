# Pipeline Creation Methods

Methods for creating and initializing pipeline instances. All methods return a `Pipeline\Standard` instance that can be chained with other operations.

## Class Constructor

### `new Standard(?iterable $input = null)`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Constructor |
> | **Terminal?** | No |
> | **When to Use** | To create a pipeline instance directly |
> | **Key Behavior** | Accepts any iterable or null for empty pipeline |

Creates a new pipeline instance with optional initial data.

**Parameters:**
- `$input` (?iterable): Optional initial data source (array, Iterator, IteratorAggregate, or Generator)

**Returns:** Pipeline\Standard instance

**Examples:**

```php
use Pipeline\Standard;

// Empty pipeline
$pipeline = new Standard();

// From array
$pipeline = new Standard([1, 2, 3, 4, 5]);

// From generator
function gen() {
    yield 1;
    yield 2;
    yield 3;
}
$pipeline = new Standard(gen());

// From iterator
$pipeline = new Standard(new ArrayIterator(['a', 'b', 'c']));

// From file
$pipeline = new Standard(new SplFileObject('data.txt'));
```

## Helper Functions

### `take(?iterable $input = null, iterable ...$inputs)`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Creation helper |
> | **Terminal?** | No |
> | **When to Use** | Preferred way to create pipelines, supports multiple sources |
> | **Key Behavior** | Concatenates multiple iterables into single pipeline |

Creates a pipeline from one or more iterables. Additional inputs are appended in sequence.

**Parameters:**
- `$input` (?iterable): Primary data source
- `...$inputs` (iterable): Additional data sources to append

**Returns:** Pipeline\Standard instance

**Examples:**

```php
use function Pipeline\take;

// Single source
$pipeline = take([1, 2, 3]);

// Multiple sources (concatenated)
$pipeline = take([1, 2], [3, 4], [5, 6]);
// Equivalent to [1, 2, 3, 4, 5, 6]

// From generator with additional arrays
$pipeline = take(
    generateData(),
    [100, 200],
    moreData()
);

// Empty pipeline
$pipeline = take();
```

### `fromArray(array $input)`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Creation helper |
> | **Terminal?** | No |
> | **When to Use** | When you have an array and want type safety |
> | **Key Behavior** | Only accepts arrays, preserves keys |

Creates a pipeline specifically from an array. Provides type safety when array input is required.

**Parameters:**
- `$input` (array): Source array

**Returns:** Pipeline\Standard instance

**Examples:**

```php
use function Pipeline\fromArray;

// Indexed array
$pipeline = fromArray([1, 2, 3, 4, 5]);

// Associative array
$pipeline = fromArray([
    'first' => 1,
    'second' => 2,
    'third' => 3
]);

// Multidimensional array
$pipeline = fromArray([
    ['id' => 1, 'name' => 'Alice'],
    ['id' => 2, 'name' => 'Bob']
]);
```

### `fromValues(...$values)`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Creation helper |
> | **Terminal?** | No |
> | **When to Use** | To create a pipeline from individual values |
> | **Key Behavior** | Each argument becomes a pipeline element |

Creates a pipeline from individual values provided as arguments.

**Parameters:**
- `...$values` (mixed): Individual values to include in pipeline

**Returns:** Pipeline\Standard instance

**Examples:**

```php
use function Pipeline\fromValues;

// From scalar values
$pipeline = fromValues(1, 2, 3, 4, 5);

// From mixed types
$pipeline = fromValues('a', 1, true, null, ['x' => 'y']);

// From objects
$obj1 = new stdClass();
$obj2 = new DateTime();
$pipeline = fromValues($obj1, $obj2);

// Single value
$pipeline = fromValues(42);
```

### `map(?callable $func = null)`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Creation helper / Transformation |
> | **Terminal?** | No |
> | **When to Use** | To create pipelines from generators or transform existing ones |
> | **Key Behavior** | Dual purpose: creation and transformation |

Creates a pipeline with an optional generator callback. When called without arguments, returns an empty pipeline. When called with a generator function, initializes the pipeline with the generator's output.

**Parameters:**
- `$func` (?callable): Optional generator function or value-returning function

**Returns:** Pipeline\Standard instance

**Examples:**

```php
use function Pipeline\map;

// Empty pipeline
$pipeline = map();

// From generator function
$pipeline = map(function() {
    for ($i = 1; $i <= 5; $i++) {
        yield $i * $i;
    }
});
// Yields: 1, 4, 9, 16, 25

// From value-returning function (creates single-element pipeline)
$pipeline = map(fn() => 'hello');
// Contains: ['hello']

// Infinite sequence generator
$pipeline = map(function() {
    $n = 1;
    while (true) {
        yield $n++;
    }
})->slice(0, 10);  // Take first 10
```

### `zip(iterable $base, iterable ...$inputs)`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Creation helper |
> | **Terminal?** | No |
> | **When to Use** | To combine parallel arrays into tuples |
> | **Key Behavior** | Creates arrays from corresponding elements, pads with null |

Creates a pipeline by combining multiple iterables element by element (transposition).

**Parameters:**
- `$base` (iterable): Base iterable to zip
- `...$inputs` (iterable): Additional iterables to zip with

**Returns:** Pipeline\Standard instance with arrays as elements

**Examples:**

```php
use function Pipeline\zip;

// Basic zip
$names = ['Alice', 'Bob', 'Charlie'];
$ages = [30, 25, 35];
$result = zip($names, $ages)->toList();
// Result: [['Alice', 30], ['Bob', 25], ['Charlie', 35]]

// Multiple iterables
$result = zip(
    ['A', 'B', 'C'],
    [1, 2, 3],
    ['x', 'y', 'z']
)->toList();
// Result: [['A', 1, 'x'], ['B', 2, 'y'], ['C', 3, 'z']]

// Uneven lengths (shorter iterables padded with null)
$result = zip(
    [1, 2, 3, 4, 5],
    ['a', 'b', 'c']
)->toList();
// Result: [[1, 'a'], [2, 'b'], [3, 'c'], [4, null], [5, null]]

// With generators
function letters() {
    foreach (range('a', 'z') as $letter) {
        yield $letter;
    }
}
$result = zip(range(1, 5), letters())->toList();
// Result: [[1, 'a'], [2, 'b'], [3, 'c'], [4, 'd'], [5, 'e']]
```

## Data Addition Methods

### `append(?iterable $values = null)`

Adds elements to the end of the pipeline.

**Parameters:**
- `$values` (?iterable): Values to append

**Returns:** $this (Pipeline\Standard instance)

**Examples:**

```php
$pipeline = take([1, 2, 3])
    ->append([4, 5, 6])
    ->append(generateMore());
// Result: 1, 2, 3, 4, 5, 6, ...generated values...

// Append nothing (no-op)
$pipeline = take([1, 2, 3])->append(null);
// Result: [1, 2, 3]

// Chain multiple appends
$result = take([1])
    ->append([2])
    ->append([3])
    ->toList();
// Result: [1, 2, 3]
```

### `push(...$vector)`

Appends individual values to the end of the pipeline.

**Parameters:**
- `...$vector` (mixed): Individual values to append

**Returns:** $this (Pipeline\Standard instance)

**Examples:**

```php
$result = take([1, 2, 3])
    ->push(4)
    ->push(5, 6)
    ->toList();
// Result: [1, 2, 3, 4, 5, 6]

// Push various types
$result = take(['a'])
    ->push('b', 100, true, null)
    ->toList();
// Result: ['a', 'b', 100, true, null]
```

### `prepend(?iterable $values = null)`

Adds elements to the beginning of the pipeline.

**Parameters:**
- `$values` (?iterable): Values to prepend

**Returns:** $this (Pipeline\Standard instance)

**Examples:**

```php
$result = take([4, 5, 6])
    ->prepend([1, 2, 3])
    ->toList();
// Result: [1, 2, 3, 4, 5, 6]

// Prepend to empty pipeline
$result = take()
    ->prepend([1, 2, 3])
    ->toList();
// Result: [1, 2, 3]

// Chain operations
$result = take([3])
    ->prepend([2])
    ->prepend([1])
    ->toList();
// Result: [1, 2, 3]
```

### `unshift(...$vector)`

Prepends individual values to the beginning of the pipeline.

**Parameters:**
- `...$vector` (mixed): Individual values to prepend

**Returns:** $this (Pipeline\Standard instance)

**Examples:**

```php
$result = take([4, 5, 6])
    ->unshift(1, 2, 3)
    ->toList();
// Result: [1, 2, 3, 4, 5, 6]

// Order matters
$result = take([3])
    ->unshift(2)
    ->unshift(1)
    ->toList();
// Result: [1, 2, 3]
```

## Usage Patterns

### Combining Creation Methods

```php
// Start with array, add more data
$pipeline = fromArray([1, 2, 3])
    ->append(generateMore())
    ->push(100, 200)
    ->prepend([0]);

// Start empty, build up
$pipeline = take()
    ->append(dataSource1())
    ->append(dataSource2())
    ->push('end');
```

### Lazy Evaluation

```php
// Pipeline creation is lazy - nothing executes until consumed
$pipeline = take(expensiveGenerator())
    ->append(anotherExpensiveSource());
// No computation happens yet

// Computation begins here
$result = $pipeline->toList();
```

### Memory Efficiency

```php
// Process large file without loading into memory
$pipeline = take(new SplFileObject('10gb-file.txt'))
    ->append(new SplFileObject('another-large-file.txt'));

// Still memory efficient - processes line by line
$pipeline->each(function($line) {
    processLine($line);
});
```

## Next Steps

- [Transformation Methods](transformation.md) - Methods for transforming pipeline data
- [Filtering Methods](filtering.md) - Methods for filtering elements