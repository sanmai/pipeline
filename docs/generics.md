# Type Safety with Generics

Pipeline uses PHP's generic types (`@template` in PHPDocs) to provide type safety. This enhances static analysis (PHPStan, Psalm), leading to more reliable code.

## How It Works

The `Pipeline\Standard` class uses generic types to inform static analysis tools about the types of keys (`TKey`) and values (`TValue`) in your pipeline.

## Using Type-Safe Pipelines

### Type Inference

Pipeline often infers types automatically:

```php
use function Pipeline\fromArray;
use function Pipeline\fromValues;

$strings = fromValues('hello', 'world'); // Inferred: Standard<int, string>
$numbers = fromArray(['a' => 1, 'b' => 2]); // Inferred: Standard<string, int>
```

Explicit PHPDoc type annotations can be added:

```php
/** @var Standard<int, string> $strings */
$strings = fromValues('hello', 'world');
```

### Type Transformations

*   **`filter()`**: Preserves types.
    ```php
    $pipeline = fromValues('hello', 'world')->filter(fn($s) => strlen($s) > 4);
    // Result: list<string>
    ```
*   **`map()` / `cast()`**: Can change value types.
    ```php
    $pipeline = fromArray(['a' => 1])->map(fn($n) => "Number: $n");
    // Result: array<string, string>
    ```
*   **`reduce()` / `fold()`**: Also type-checked.

### Extracting Data

Methods like `toList()` and `toAssoc()` return standard PHP arrays with inferred types:

```php
use function Pipeline\take;

$data = take($someIterable);
$list = $data->toList();   // Numerically indexed
$assoc = $data->toAssoc(); // Associative
```

## Important Considerations

*   **Mutability**: Pipeline instances are mutable.
*   **One-Time Use**: After a terminal operation (e.g., `toList()`), a pipeline is exhausted.
*   **Complex Transformations**: Operations like `chunk()`, `flip()`, `values()`, or `keys()` can alter key/value relationships. Explicit type hints may be needed:
    ```php
    use Pipeline\Standard;
    /** @var Standard<int, string> $pipeline */
    $pipeline = fromArray(['a' => 1])->flip();
    ```
*   **Backward Compatibility**: All type improvements are PHPDoc-based, ensuring no runtime impact and 100% backward compatibility.

## Tool Setup & Tips

*   **PHPStan/Psalm**: Include Pipeline in your project. Configure a high analysis level.
*   **Tips**:
    *   Provide type hints when the source isn't obvious.
    *   Prefer arrow functions for better type inference.
    *   Integrate static analysis into your CI pipeline.

The Pipeline type system is designed to be a helpful tool, providing robust static analysis.
