# Type Safety with Generics

Pipeline uses PHP's generic types (`@template` annotations in PHPDocs) to provide robust type safety. Static analyzers such as PHPStan and Psalm can follow the types of keys and values through an entire pipeline, catching mistakes before the code runs.

## How It Works

The `Pipeline\Standard` class is annotated as `Standard<TKey, TValue>`. Every method declares how it changes those parameters: `map()` and `cast()` replace `TValue` with the callback's return type, `filter()` and `select()` keep types intact, `keys()` turns `TKey` into the value type, and so on. Notably, the annotations track these changes through both chained calls *and* separate statements, thanks to `@phpstan-self-out`—pipelines are mutable, and the types mutate along.

## Using Type-Safe Pipelines

### Type Inference

Types are inferred automatically from the input:

```php
use function Pipeline\fromArray;
use function Pipeline\fromValues;

$strings = fromValues('hello', 'world');    // Standard<int, string>
$numbers = fromArray(['a' => 1, 'b' => 2]); // Standard<string, int>
```

### Type Transformations

Callback return types drive the inference, so typed closures give the best results:

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
    echo $value->bar(); // Analyzer knows $value is Foo
}
```

The same inference works without chaining, one statement at a time:

```php
use function Pipeline\take;

$pipeline = take(['a' => 1, 'b' => 2, 'c' => 3]);
$pipeline->map(fn(int $n): int => $n * 2);
$pipeline->cast(fn(int $n): Foo => new Foo($n));
// $pipeline is now Standard<string, Foo>
```

If `Foo::bar()` were renamed or its constructor changed to expect a string, PHPStan would flag both the constructor call inside `cast()` and the `$value->bar()` call.

### Extracting Data

Terminal operations carry the types out into plain PHP arrays:

```php
$list = $pipeline->toList();   // list<Foo>
$assoc = $pipeline->toAssoc(); // array<string, Foo>
```

## Important Considerations

- **Untyped callbacks weaken inference**: `fn($x) => ...` gives the analyzer little to work with. Prefer parameter and return types on closures.
- **Key-changing operations**: `chunk()`, `flip()`, `keys()`, `values()`, and `tuples()` rewrite the key/value relationship; the annotations model this, but in complex compositions an explicit annotation can help:

    ```php
    use Pipeline\Standard;
    use function Pipeline\fromArray;

    /** @var Standard<int, string> $pipeline */
    $pipeline = fromArray(['a' => 1])->flip();
    ```

- **Zero runtime cost**: All typing lives purely in PHPDoc comments; nothing changes at runtime.

## Tool Setup & Tips

- Run PHPStan or Psalm at a high analysis level; the library itself is checked at the maximum levels.
- Provide explicit type hints when the data source isn't statically known (e.g., decoded JSON).
- Integrate static analysis into your CI pipeline so type regressions surface in review.
