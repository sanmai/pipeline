# Creation Methods

These methods and functions create and initialize pipeline instances. All helper functions live in the `Pipeline` namespace and always return a pipeline instance.

## Constructor

### `new Standard()`

The `Standard` class is the main entry point for creating a pipeline.

**Signature**: `new Standard(?iterable $input = null)`

- `$input`: An optional initial data source: an array, iterator, or generator.

**Examples**:

```php
use Pipeline\Standard;

// Create an empty pipeline
$pipeline = new Standard();

// Create a pipeline from an array
$pipeline = new Standard([1, 2, 3]);

// Create a pipeline from an iterator
$pipeline = new Standard(new ArrayIterator([1, 2, 3]));
```

The constructor accepts iterables only. To seed a pipeline from a generator *function*, use the `map()` helper function below.

## Helper Functions

### `take()`

Creates a pipeline from one or more iterables, joined in succession.

**Signature**: `take(?iterable $input = null, iterable ...$inputs): Standard`

- `$input`: The primary data source.
- `...$inputs`: Additional data sources to append.

**Examples**:

```php
use function Pipeline\take;

// From a single source
$pipeline = take([1, 2, 3]);

// From multiple sources, in order
$pipeline = take([1, 2], [3, 4]); // [1, 2, 3, 4]

// From any iterable
$pipeline = take(new SplFileObject('data.txt'));
```

### `map()`

Creates a pipeline from a generator function or any other callback. The callback must not require any arguments.

**Signature**: `map(?callable $func = null): Standard`

- `$func`: An optional initial callback.

**Behavior**:

- If the callback returns a generator (that is, it uses `yield`), the pipeline is populated lazily with the yielded values.
- If the callback returns any other value, the pipeline will contain that single value.
- With no callback, an empty pipeline is created.

**Examples**:

```php
use function Pipeline\map;

// From a generator function: lazy, can even be infinite
$pipeline = map(function () {
    yield 1;
    yield 2;
    yield 3;
});

// From a single value
$pipeline = map(fn() => 'Hello'); // Contains ['Hello']
```

### `fromArray()`

Creates a pipeline from an array. Compared to `take()`, the narrower parameter type gives static analyzers more to work with when you specifically expect an array.

**Signature**: `fromArray(array $input): Standard`

**Examples**:

```php
use function Pipeline\fromArray;

$pipeline = fromArray(['a' => 1, 'b' => 2]);
```

### `fromValues()`

Creates a pipeline from individually listed values.

**Signature**: `fromValues(...$values): Standard`

**Examples**:

```php
use function Pipeline\fromValues;

$pipeline = fromValues(1, 'a', true);
```

### `zip()`

Combines multiple iterables into a single pipeline of tuples. See also the [`zip()` method](utility.md#zip).

**Signature**: `zip(iterable $base, iterable ...$inputs): Standard`

**Behavior**:

- Creates a pipeline where each element is an array containing the corresponding elements from the input iterables.
- If the iterables have different lengths, missing elements are filled with `null`.

**Examples**:

```php
use function Pipeline\zip;

$names = ['Alice', 'Bob'];
$ages = [30, 25];

$result = zip($names, $ages)->toList();
// [['Alice', 30], ['Bob', 25]]
```

## Adding Data to a Pipeline

These instance methods extend an existing pipeline with more data. On an empty pipeline they simply set the initial contents.

### `append()`

Adds elements from an iterable to the end of the pipeline.

**Signature**: `append(?iterable $values = null): self`

**Examples**:

```php
$pipeline = take([1, 2])->append([3, 4]); // [1, 2, 3, 4]
```

### `push()`

Appends individually listed values to the end of the pipeline.

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

Prepends individually listed values to the beginning of the pipeline.

**Signature**: `unshift(...$vector): self`

**Examples**:

```php
$pipeline = take([3, 4])->unshift(1, 2); // [1, 2, 3, 4]
```

## Working with Callables

Methods like `map()`, `filter()`, and `cast()` accept any callable. PHP's first-class callable syntax is the recommended way to pass them: it is concise and gives the best IDE and static analysis support.

```php
// Built-in functions
$pipeline = take(['1', '2', '3'])->cast(intval(...));

// Object methods
$helper = new DataProcessor();
$pipeline = take($data)
    ->filter($helper->isValid(...))
    ->map($helper->transform(...));

// Static methods
$pipeline = take($users)
    ->filter(User::isActive(...))
    ->map(User::normalize(...));
```

The older callable styles work as well:

```php
// String callables
$pipeline->cast('intval');

// Array callables
$pipeline->map([$object, 'method']);
$pipeline->filter(['ClassName', 'staticMethod']);
```
