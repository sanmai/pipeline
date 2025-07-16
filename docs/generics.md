# Type Safety with Generics

Pipeline uses PHP's generic types (`@template` in PHPDocs) to provide robust type safety. This enhances static analysis (PHPStan, Psalm), leading to more reliable code.

## How It Works

The `Pipeline\Standard` class uses generic types to inform static analysis tools about the types of keys (`TKey`) and values (`TValue`) in your pipeline.

## Using Type-Safe Pipelines

### Type Inference

Pipeline often infers types automatically when creating pipelines using functions like `fromValues()` or `fromArray()`.

```php
use function Pipeline\fromArray;
use function Pipeline\fromValues;

$strings = fromValues('hello', 'world'); // Inferred: Standard<int, string>
$numbers = fromArray(['a' => 1, 'b' => 2]); // Inferred: Standard<string, int>
```

Explicit PHPDoc type annotations can be added if needed:

```php
/** @var Standard<int, string> $strings */
$strings = fromValues('hello', 'world');
```

### Type Transformations

*   **`filter()`**: This method preserves the existing types of elements, only reducing their count.
    ```php
    $pipeline = fromValues('hello', 'world')->filter(fn($s) => strlen($s) > 4);
    // Result: list<string>
    ```
*   **`map()` / `cast()`**: These methods are used to transform values, potentially changing their types. Static analysis tools accurately track these type changes.
    ```php
    $pipeline = fromArray(['a' => 1])->map(fn($n) => "Number: $n");
    // Result: array<string, string>
    ```
*   **`reduce()` / `fold()`**: Aggregation methods like `reduce()` and `fold()` also benefit from type checking, ensuring type safety for accumulators and elements.

### Extracting Data

Terminal operations such as `toList()` and `toAssoc()` extract processed data into standard PHP arrays, with types correctly inferred by static analysis.

```php
use function Pipeline\take;

$data = take($someIterable);
$list = $data->toList();   // Numerically indexed
$assoc = $data->toAssoc(); // Associative
```

## Important Considerations

*   **Mutability**: Pipeline instances are mutable; methods like `map()` modify the *same* instance.
*   **One-Time Use**: As with PHP generators, pipelines are generally one-time use; after a terminal operation (e.g., `toList()`), the pipeline is exhausted.
*   **Complex Transformations**: Operations such as `chunk()`, `flip()`, `values()`, and `keys()` inherently alter the key/value relationships within the pipeline. Due to these complex transformations, explicit type hints may occasionally be necessary to assist static analysis tools.
    ```php
    use Pipeline\Standard;
    /** @var Standard<int, string> $pipeline */
    $pipeline = fromArray(['a' => 1])->flip();
    ```
*   **Backward Compatibility**: All type improvements are implemented purely through PHPDoc comments, ensuring no runtime impact and 100% backward compatibility. This design principle is consistent across the library.

## Tool Setup & Tips

*   **PHPStan/Psalm**: Include Pipeline in your project. Configure a high analysis level.
*   **Tips**:
    *   Provide type hints when the source isn't obvious.
    *   Prefer arrow functions for better type inference.
    *   Integrate static analysis into your CI pipeline.

The Pipeline type system is designed to be a helpful tool, providing robust static analysis.
