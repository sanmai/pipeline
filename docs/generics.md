# Type Safety with Generics

Pipeline uses generic types (`@template` annotations in PHPDoc) for type safety. Static analyzers such as PHPStan and Psalm track the types of keys and values through a pipeline and report type errors before the code runs.

## How It Works

The `Pipeline\Standard` class has the annotation `Standard<TKey, TValue>`. Each method defines how it changes these parameters. For example, `map()` and `cast()` replace `TValue` with the callback return type, `filter()` and `select()` keep both types, and `keys()` makes `TKey` the value type. Pipelines are mutable, so the types change with each call. The `@phpstan-self-out` annotations track these changes through chained calls and through separate statements.

## Using Type-Safe Pipelines

### Type Inference

The analyzer infers types from the input:

```php
use function Pipeline\fromArray;
use function Pipeline\fromValues;

$strings = fromValues('hello', 'world');    // Standard<array-key, string>
$numbers = fromArray(['a' => 1, 'b' => 2]); // Standard<array-key, int>
```

### Type Transformations

Callback return types drive the inference. Use typed closures for the best results:

```php
use function Pipeline\take;

class Foo
{
    public function __construct(
        public int $n,
    ) {}

    public function bar(): string
    {
        return "{$this->n}\n";
    }
}

$pipeline = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->map(fn(int $n): int => $n * 2)
    ->cast(fn(int $n): Foo => new Foo($n));

foreach ($pipeline as $value) {
    echo $value->bar(); // The analyzer infers that $value is Foo
}
```

Inference also works with separate statements:

```php
use function Pipeline\take;

$pipeline = take(['a' => 1, 'b' => 2, 'c' => 3]);
$pipeline->map(fn(int $n): int => $n * 2);
$pipeline->cast(fn(int $n): Foo => new Foo($n));
// $pipeline is now Standard<mixed, Foo>
```

If you rename `Foo::bar()`, PHPStan reports the `$value->bar()` call. If you change the constructor to require a string, PHPStan reports the constructor call in `cast()`.

### Extracting Data

Terminal operations return plain PHP arrays with the tracked types:

```php
$list = $pipeline->toList();   // list<Foo>
$assoc = $pipeline->toAssoc(); // array<array-key, Foo>
```

## Important Considerations

- **Untyped callbacks weaken inference**: A closure such as `fn($x) => ...` gives the analyzer little type information. Add parameter and return types to closures.
- **Key-changing operations**: `chunk()`, `flip()`, `keys()`, `values()`, and `tuples()` change the key/value relationship. The annotations model this. For complex compositions, an explicit annotation can help:

    ```php
    use Pipeline\Standard;
    use function Pipeline\fromArray;

    /** @var Standard<int, string> $pipeline */
    $pipeline = fromArray(['a' => 1])->flip();
    ```

- **No runtime cost**: All type information is in PHPDoc comments. Runtime behavior does not change.

## Tool Setup & Tips

- Run PHPStan or Psalm at a high analysis level. PHPStan checks the library source at level `max`.
- Add explicit type annotations when the data source has no static type (for example, decoded JSON).
- Run static analysis in your CI pipeline so that type regressions show during review.
