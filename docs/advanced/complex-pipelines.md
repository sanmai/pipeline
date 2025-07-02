# Complex Pipeline Patterns

This section explores advanced techniques for building sophisticated, maintainable, and scalable data processing pipelines.

## Pipeline Composition

One of the most powerful features of the library is the ability to compose complex pipelines from smaller, reusable components. This is achieved by encapsulating business logic into separate classes or functions, which can then be chained together.

### Reusable Components

By creating dedicated classes for pipeline operations, you can build a library of reusable, testable components.

**Example: A `UserProcessor` Class**

```php
namespace App\Pipeline\Components;

class UserProcessor
{
    public static function filterActive(array $user): bool
    {
        return ($user['active'] ?? false) === true;
    }

    public static function normalize(array $user): array
    {
        return [
            ...$user,
            'name' => ucwords(strtolower(trim($user['name'] ?? ''))),
            'email' => strtolower(trim($user['email'] ?? '')),
        ];
    }
}
```

These components can then be used to build a clean and readable pipeline:

```php
use App\Pipeline\Components\UserProcessor;
use function Pipeline\take;

$processedUsers = take($rawUsers)
    ->filter(UserProcessor::filterActive(...))
    ->map(UserProcessor::normalize(...))
    ->toList();
```

This approach offers several advantages:

-   **Readability**: The pipeline clearly expresses the business logic.
-   **Testability**: Each component can be unit-tested in isolation.
-   **Reusability**: Components can be shared across multiple pipelines.

## Stateful Transformations

For operations that require state to be maintained between elements, you can use a class to encapsulate the state.

**Example: A `ChangeDetector`**

```php
class ChangeDetector
{
    private $previous = null;

    public function detect($value): ?array
    {
        if ($this->previous === null) {
            $this->previous = $value;
            return null;
        }

        $change = $value - $this->previous;
        $this->previous = $value;

        return ['value' => $value, 'change' => $change];
    }
}

$detector = new ChangeDetector();
$changes = take($prices)
    ->map([$detector, 'detect'])
    ->filter() // Remove the first null
    ->toList();
```

## Error Handling

For pipelines that may encounter errors, you can create a wrapper to handle exceptions gracefully.

**Example: A `SafeProcessor`**

```php
class SafeProcessor
{
    private array $errors = [];

    public function transform(callable $transformer): callable
    {
        return function ($item) use ($transformer) {
            try {
                return ['success' => true, 'data' => $transformer($item)];
            } catch (\Exception $e) {
                $this->errors[] = ['item' => $item, 'error' => $e->getMessage()];
                return ['success' => false, 'data' => null];
            }
        };
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

$processor = new SafeProcessor();

$results = take($inputs)
    ->map($processor->transform(fn($item) => process($item)))
    ->filter(fn($result) => $result['success'])
    ->map(fn($result) => $result['data'])
    ->toList();

$errors = $processor->getErrors();
```

## Hierarchical Data

For nested data structures, you can use recursion to process the entire tree.

**Example: A `TreeProcessor`**

```php
class TreeProcessor
{
    public static function traverse(array $node, callable $processor): array
    {
        $result = $processor($node);

        if (isset($node['children'])) {
            $result['children'] = take($node['children'])
                ->map(fn($child) => self::traverse($child, $processor))
                ->toList();
        }

        return $result;
    }
}

$processedTree = TreeProcessor::traverse($tree, fn($node) => [
    ...$node,
    'processed' => true,
]);
```
