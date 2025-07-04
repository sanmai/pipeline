# Creation Methods

These methods are used to create and initialize pipeline instances.

## Constructor

### `new Standard()`

The `Standard` class is the main entry point for creating a pipeline.

**Signature**: `new Standard(?iterable $input = null)`

-   `$input`: An optional initial data source, which can be an array, iterator, or generator.

**Examples**:

```php
use Pipeline\Standard;

// Create an empty pipeline
$pipeline = new Standard();

// Create a pipeline from an array
$pipeline = new Standard([1, 2, 3]);

// Create a pipeline from a generator
$pipeline = new Standard(function () {
    yield 1;
    yield 2;
});
```

## Helper Functions

### `take()`

Creates a pipeline from one or more iterables.

**Signature**: `take(?iterable $input = null, iterable ...$inputs): Standard`

-   `$input`: The primary data source.
-   `...$inputs`: Additional data sources to append.

**Examples**:

```php
use function Pipeline\take;

// From a single source
$pipeline = take([1, 2, 3]);

// From multiple sources
$pipeline = take([1, 2], [3, 4]); // [1, 2, 3, 4]
```

### `fromArray()`

Creates a pipeline from an array.

**Signature**: `fromArray(array $input): Standard`

**Examples**:

```php
use function Pipeline\fromArray;

$pipeline = fromArray(['a' => 1, 'b' => 2]);
```

### `fromValues()`

Creates a pipeline from a sequence of individual values.

**Signature**: `fromValues(...$values): Standard`

**Examples**:

```php
use function Pipeline\fromValues;

$pipeline = fromValues(1, 'a', true);
```

### `zip()`

Combines multiple iterables into a single pipeline of tuples.

**Signature**: `zip(iterable $base, iterable ...$inputs): Standard`

**Behavior**:

-   Creates a new pipeline where each element is an array containing the corresponding elements from the input iterables.
-   If the iterables have different lengths, the shorter ones are padded with `null`.

**Examples**:

```php
use function Pipeline\zip;

$names = ['Alice', 'Bob'];
$ages = [30, 25];

$result = zip($names, $ages)->toList();
// [['Alice', 30], ['Bob', 25]]
```

## Adding Data to a Pipeline

### `append()`

Adds elements from an iterable to the end of the pipeline.

**Signature**: `append(?iterable $values = null): self`

**Examples**:

```php
$pipeline = take([1, 2])->append([3, 4]); // [1, 2, 3, 4]
```

### `push()`

Appends individual values to the end of the pipeline.

**Signature**: `push(...$vector): self`

**Examples**:

```php
$pipeline = take([1, 2])->push(3, 4); // [1, 2, 3, 4]
```

### `prepend()`

Adds elements from an iterable to the beginning of the pipeline.

**Signature**: `prepend(?iterable $values = null): self`

**Examples**:

```php
$pipeline = take([3, 4])->prepend([1, 2]); // [1, 2, 3, 4]
```

### `unshift()`

Prepends individual values to the beginning of the pipeline.

**Signature**: `unshift(...$vector): self`

**Examples**:

```php
$pipeline = take([3, 4])->unshift(1, 2); // [1, 2, 3, 4]
```

## Working with Callables

The library accepts callables in many methods like `map()`, `filter()`, and `cast()`. Since PHP 8.1, you can use the first-class callable syntax for cleaner, more modern code.

### First-Class Callable Syntax

```php
// Modern syntax (PHP 8.1+)
$pipeline = take(['1', '2', '3'])
    ->cast(intval(...))
    ->toList();

// Works with any function
$pipeline = take(['hello', 'world'])
    ->map(strtoupper(...))
    ->toList();

// Works with object methods
$helper = new DataProcessor();
$pipeline = take($data)
    ->filter($helper->isValid(...))
    ->map($helper->transform(...));

// Works with static methods
$pipeline = take($users)
    ->filter(User::isActive(...))
    ->map(User::normalize(...));
```

### Legacy Syntax (Still Supported)

```php
// String callables (older style)
$pipeline->cast('intval');

// Array callables (older style)
$pipeline->map([$object, 'method']);

// Static method array (older style)
$pipeline->filter(['ClassName', 'staticMethod']);
```

The first-class callable syntax is recommended for new code as it provides better IDE support, type safety, and is more readable.