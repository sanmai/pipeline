# Creation Methods

These methods create and initialize pipeline instances.

## `new Standard()`

The `Standard` class is the main entry point for creating a pipeline.

**Signature**: `new Standard(?iterable $input = null)`

-   `$input`: An optional initial `iterable` (array, iterator, or generator).

**Example**:

```php
use Pipeline\Standard;

// Create an empty pipeline
$pipeline = new Standard();

// Create a pipeline from an array
$pipeline = new Standard([1, 2, 3]);
```

## Helper Functions

These functions create a new pipeline instance.

### `map()`

Creates a pipeline from a generator function.

**Signature**: `map(?callable $func = null): Standard`

-   If `$func` is a generator, the pipeline is populated with the yielded values.
-   If no function is provided, an empty pipeline is created.

**Example**:

```php
use function Pipeline\map;

// From a generator
$pipeline = map(function () {
    for ($i = 1; $i <= 3; $i++) {
        yield $i;
    }
});
```

### `take()`

Creates a pipeline from one or more iterables.

**Signature**: `take(?iterable $input = null, iterable ...$inputs): Standard`

**Example**:

```php
use function Pipeline\take;

// From a single source
$pipeline = take([1, 2, 3]);

// From multiple sources, which are appended
$pipeline = take([1, 2], [3, 4]); // Pipeline for [1, 2, 3, 4]
```

### `fromArray()`

Creates a pipeline from a single array.

**Signature**: `fromArray(array $input): Standard`

**Example**:

```php
use function Pipeline\fromArray;

$pipeline = fromArray(['a' => 1, 'b' => 2]);
```

### `fromValues()`

Creates a pipeline from a sequence of arguments.

**Signature**: `fromValues(...$values): Standard`

**Example**:

```php
use function Pipeline\fromValues;

$pipeline = fromValues(1, 'a', true);
```

### `zip()`

Creates a new pipeline by combining multiple iterables into a single pipeline of tuples (arrays).

**Signature**: `zip(iterable $base, iterable ...$inputs): Standard`

-   If iterables have different lengths, shorter ones are padded with `null`.

**Example**:

```php
use function Pipeline\zip;

$names = ['Alice', 'Bob'];
$ages = [30, 25];

$result = zip($names, $ages)->toList();
// [
//   ['Alice', 30],
//   ['Bob', 25]
// ]
```

## Adding Data to an Existing Pipeline

These methods modify a pipeline instance by adding data.

### `append()` and `push()`

Add elements to the end of the pipeline.

-   `append(iterable $values)`: Adds elements from an iterable.
-   `push(...$values)`: Appends individual values.

**Examples**:

```php
$pipeline = take([1, 2])->append([3, 4]); // [1, 2, 3, 4]
$pipeline = take([1, 2])->push(3, 4);    // [1, 2, 3, 4]
```

### `prepend()` and `unshift()`

Add elements to the beginning of the pipeline.

-   `prepend(iterable $values)`: Adds elements from an iterable.
-   `unshift(...$values)`: Prepends individual values.

**Examples**:

```php
$pipeline = take([3, 4])->prepend([1, 2]); // [1, 2, 3, 4]
$pipeline = take([3, 4])->unshift(1, 2);   // [1, 2, 3, 4]
```

## Working with Callables

Many methods like `map()`, `filter()`, and `cast()` accept callables. PHP 8.1's first-class callable syntax is recommended for cleaner, more readable code.

### First-Class Callable Syntax (PHP 8.1+)

```php
// Modern syntax
$pipeline = take(['1', '2', '3'])->cast(intval(...));

// Works with any function
$pipeline = take(['hello', 'world'])->map(strtoupper(...));

// Works with object methods
$helper = new DataProcessor();
$pipeline = take($data)->map($helper->transform(...));

// Works with static methods
$pipeline = take($users)->filter(User::isActive(...));
```

### Legacy Syntax

The library still supports older callable syntax, like strings (`'intval'`) and arrays (`[$object, 'method']`). However, the first-class callable syntax is preferred for new code due to better IDE support and type safety.
