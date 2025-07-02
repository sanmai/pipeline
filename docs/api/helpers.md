# Helper Functions

Helper functions provide a convenient, fluent syntax for creating pipeline instances.

## `map()`

Creates a pipeline from a generator function or a single value.

**Signature**: `map(?callable $func = null): Standard`

-   `$func`: An optional generator function.

**Behavior**:

-   If `$func` is a generator, the pipeline is populated with the yielded values.
-   If `$func` returns a single value, the pipeline will contain that one element.
-   If no function is provided, an empty pipeline is created.

**Examples**:

```php
use function Pipeline\map;

// From a generator
$pipeline = map(function () {
    for ($i = 1; $i <= 3; $i++) {
        yield $i;
    }
});

// From a single value
$pipeline = map(fn() => 'Hello'); // Contains ['Hello']
```

## `take()`

Creates a pipeline from one or more iterables.

**Signature**: `take(?iterable $input = null, iterable ...$inputs): Standard`

-   `$input`: The primary data source.
-   `...$inputs`: Additional data sources to append.

**Examples**:

```php
use function Pipeline\take;

// From a single array
$pipeline = take([1, 2, 3]);

// From multiple sources
$pipeline = take([1, 2], new ArrayIterator([3, 4]));
```

## `fromArray()`

Creates a pipeline from an array, offering better type safety if you specifically need to handle an array.

**Signature**: `fromArray(array $input): Standard`

**Examples**:

```php
use function Pipeline\fromArray;

$pipeline = fromArray(['a' => 1, 'b' => 2]);
```

## `fromValues()`

Creates a pipeline from a sequence of individual values.

**Signature**: `fromValues(...$values): Standard`

**Examples**:

```php
use function Pipeline\fromValues;

$pipeline = fromValues(1, 'a', true);
```

## `zip()`

Combines multiple iterables into a single pipeline of tuples.

**Signature**: `zip(iterable $base, iterable ...$inputs): Standard`

**Behavior**:

-   Creates a new pipeline where each element is an array of corresponding elements from the input iterables.
-   Shorter iterables are padded with `null`.

**Examples**:

```php
use function Pipeline\zip;

$names = ['Alice', 'Bob'];
$ages = [30, 25];

$result = zip($names, $ages)->toList();
// [['Alice', 30], ['Bob', 25]]
```
