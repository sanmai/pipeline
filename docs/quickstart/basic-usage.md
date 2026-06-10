# Basic Usage

## Creating a Pipeline

### From an Array

You can initialize a pipeline from an array using the `Pipeline\Standard` constructor or the `take()` and `fromArray()` helper functions.

```php
use Pipeline\Standard;
use function Pipeline\take;
use function Pipeline\fromArray;

// Using the constructor
$pipeline = new Standard([1, 2, 3]);

// Using the take() helper
$pipeline = take([1, 2, 3]);

// Using the fromArray() helper
$pipeline = fromArray([1, 2, 3]);
```

### From an Iterable

The recommended way to use the library is with iterables, which allows for lazy processing of data.

```php
// From a generator
function generateNumbers() {
    for ($i = 1; $i <= 5; $i++) {
        yield $i;
    }
}
$pipeline = take(generateNumbers());

// From an iterator
$iterator = new ArrayIterator([1, 2, 3]);
$pipeline = take($iterator);

// From a file, line by line
$file = new SplFileObject('data.txt');
$pipeline = take($file);
```

### From Individual Values

The `fromValues()` helper function creates a pipeline from a sequence of arguments.

```php
use function Pipeline\fromValues;

$pipeline = fromValues(1, 2, 3, 4, 5);
```

### Empty Pipeline

You can also create an empty pipeline and add data to it later.

```php
$pipeline = new Standard();
$pipeline->append([1, 2, 3]);
```

## Transformations

### `map()` - Transform Each Element

The `map()` method applies a callback to each element in the pipeline. A callback that uses `yield` can produce any number of elements from a single input.

```php
use function Pipeline\take;

// Double each number
$result = take([1, 2, 3])
    ->map(fn($x) => $x * 2)
    ->toList(); // [2, 4, 6]

// Extract a property from an array of records
$users = [
    ['name' => 'Alice', 'age' => 30],
    ['name' => 'Bob', 'age' => 25],
];
$names = take($users)
    ->map(fn($user) => $user['name'])
    ->toList(); // ['Alice', 'Bob']

// Produce several elements from one with yield
$result = take([1, 2, 3])
    ->map(function ($x) {
        yield $x;
        yield -$x;
    })
    ->toList(); // [1, -1, 2, -2, 3, -3]
```

### `cast()` - One-to-One Transformations

The `cast()` method applies a simple one-to-one callback. Unlike `map()`, it never expands generators, so what the callback returns is exactly what ends up in the pipeline.

```php
// Convert strings to integers
$result = take(['1', '2', '3'])
    ->cast(intval(...))
    ->toList(); // [1, 2, 3]

// Convert strings to uppercase
$result = take(['hello', 'world'])
    ->cast(strtoupper(...))
    ->toList(); // ['HELLO', 'WORLD']
```

## Filtering

### `filter()` - Remove Falsy Elements

The `filter()` method removes elements that do not pass a given test. Without a callback it removes all falsy values, exactly like `array_filter()`.

```php
// Keep only even numbers
$result = take([1, 2, 3, 4, 5, 6])
    ->filter(fn($x) => $x % 2 === 0)
    ->toList(); // [2, 4, 6]

// With no arguments, filter() removes all falsy values
$result = take([0, 1, false, 2, null, 3, '', 4])
    ->filter()
    ->toList(); // [1, 2, 3, 4]
```

### `select()` - Predictable Filtering

The `select()` method is the stricter sibling of `filter()`: without a callback it removes only `null` and `false`, keeping valid falsy data such as `0` and empty strings.

```php
$result = take([0, 1, false, 2, null, 3, '', 4])
    ->select()
    ->toList(); // [0, 1, 2, 3, '', 4]
```

## Combining Pipelines

### `append()` and `push()`

Use `append()` to add the contents of an iterable to the end of the pipeline, or `push()` to add individual elements.

```php
$result = take([1, 2, 3])
    ->append([4, 5, 6])
    ->toList(); // [1, 2, 3, 4, 5, 6]

$result = take([1, 2, 3])
    ->push(4, 5, 6)
    ->toList(); // [1, 2, 3, 4, 5, 6]
```

### `prepend()` and `unshift()`

Use `prepend()` to add the contents of an iterable to the beginning of the pipeline, or `unshift()` to add individual elements.

```php
$result = take([4, 5, 6])
    ->prepend([1, 2, 3])
    ->toList(); // [1, 2, 3, 4, 5, 6]

$result = take([4, 5, 6])
    ->unshift(1, 2, 3)
    ->toList(); // [1, 2, 3, 4, 5, 6]
```

## Retrieving Results

### Iterating with `foreach`

A pipeline is iterable; processing happens as you iterate, and you can stop at any time.

```php
foreach (take($hugeDataSet)->map($transform) as $value) {
    if (isDone($value)) {
        break; // No further elements are processed
    }
}
```

### `toList()` - Indexed Array

The `toList()` method consumes the pipeline and returns a new array with sequential numeric keys.

```php
$result = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->map(fn($x) => $x * 2)
    ->toList(); // [2, 4, 6]
```

### `toAssoc()` - Associative Array

The `toAssoc()` method preserves the original keys.

```php
$result = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->map(fn($x) => $x * 2)
    ->toAssoc(); // ['a' => 2, 'b' => 4, 'c' => 6]
```

### `reduce()` and `fold()` - Single Value

Use `reduce()` or `fold()` to reduce the pipeline to a single value. Both default to summation.

```php
// Sum all values
$sum = take([1, 2, 3, 4, 5])->reduce(); // 15

// Calculate a product
$product = take([1, 2, 3, 4, 5])
    ->fold(1, fn($carry, $item) => $carry * $item); // 120
```

## Common Patterns

### Processing a Collection

```php
$orders = [
    ['id' => 1, 'total' => 100, 'status' => 'paid'],
    ['id' => 2, 'total' => 200, 'status' => 'pending'],
    ['id' => 3, 'total' => 150, 'status' => 'paid'],
];

$paidTotal = take($orders)
    ->filter(fn($order) => $order['status'] === 'paid')
    ->map(fn($order) => $order['total'])
    ->reduce(); // 250
```

### Working with Files

```php
// Count error lines in a log file
$errorCount = take(new SplFileObject('app.log'))
    ->filter(fn($line) => str_contains($line, 'ERROR'))
    ->count();

// Process a CSV file
$data = take(new SplFileObject('data.csv'))
    ->map(str_getcsv(...))
    ->filter(fn($row) => count($row) === 3)
    ->toList();
```

### Pagination

```php
function getPage(iterable $data, int $page, int $perPage = 10): array
{
    $offset = ($page - 1) * $perPage;

    return take($data)
        ->slice($offset, $perPage)
        ->toList();
}

$page2 = getPage(range(1, 100), 2, 10);
// [11, 12, 13, 14, 15, 16, 17, 18, 19, 20]
```

## Next Steps

- [Walkthrough](walkthrough.md)
- [Cookbook](../cookbook/index.md)
- [API Reference](../api/creation.md)
