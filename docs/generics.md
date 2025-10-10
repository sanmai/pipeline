# Type Safety with Generics

Pipeline uses PHP's generic types (`@template` in PHPDocs) to provide robust type safety for static analysis tools like PHPStan and Psalm.

## How It Works

The `Pipeline\Standard` class uses generics to track the types of keys (`TKey`) and values (`TValue`) in your pipeline, allowing static analysis to catch potential bugs before runtime.

### Type Inference

Pipeline automatically infers types when creating pipelines.

```php
use function Pipeline\fromArray;
use function Pipeline\fromValues;

$strings = fromValues('hello', 'world'); // Inferred: Standard<int, string>
$numbers = fromArray(['a' => 1, 'b' => 2]); // Inferred: Standard<string, int>
```

### Type Transformations

Static analysis tools accurately track type changes through the pipeline.

-   **`filter()`**: Preserves the existing types, only reducing the number of elements.
-   **`map()` / `cast()`**: Can transform values, changing their types.
-   **`reduce()` / `fold()`**: Ensure type safety for accumulators and elements.

```php
$pipeline = fromArray(['a' => 1])->map(fn($n) => "Number: $n");
// Inferred type of values is now string
```

### Terminal Operations

Terminal operations like `toList()` and `toAssoc()` produce standard PHP arrays with correctly inferred types.

```php
use function Pipeline\take;

$data = take($someIterable);
$list = $data->toList();   // Produces a list<TValue>
$assoc = $data->toAssoc(); // Produces an array<TKey, TValue>
```

## Important Considerations

-   **Mutability**: Pipeline instances are mutable; methods like `map()` modify the *same* instance.
-   **One-Time Use**: Like PHP generators, pipelines are generally for one-time use. After a terminal operation (e.g., `toList()`), the pipeline is consumed.
-   **Complex Transformations**: Operations like `chunk()`, `flip()`, `values()`, and `keys()` alter the key/value structure. In these cases, you may need to add explicit type hints to assist static analysis.
    ```php
    use Pipeline\Standard;
    /** @var Standard<int, string> $pipeline */
    $pipeline = fromArray(['a' => 1])->flip();
    ```
-   **Backward Compatibility**: All type information is in PHPDoc comments, ensuring no runtime impact and 100% backward compatibility.
