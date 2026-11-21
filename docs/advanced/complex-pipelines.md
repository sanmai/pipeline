# Complex Pipeline Patterns

This page describes patterns for building large pipelines from small parts.

## Pipeline Composition

You can compose a complex pipeline from small, reusable components. Put the business logic into separate classes or functions, then chain them.

### Reusable Components

Put pipeline operations into dedicated classes. You can then reuse and test each class as a component.

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

Build the pipeline with these components:

```php
use App\Pipeline\Components\UserProcessor;
use function Pipeline\take;

$processedUsers = take($rawUsers)
    ->filter(UserProcessor::filterActive(...))
    ->map(UserProcessor::normalize(...))
    ->toList();
```

This approach has these advantages:

-   **Readability**: The pipeline states the business logic directly.
-   **Testability**: You can unit-test each component in isolation.
-   **Reusability**: Multiple pipelines can share the same components.

## Stateful Transformations

If an operation must keep state between elements, encapsulate the state in a class.

**Example: A `ChangeDetector`**

```php
class ChangeDetector
{
    private ?float $previous = null;

    public function detect(float $value): ?array
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

/** @var iterable<float> $prices A stream of prices, e.g. a generator */
$detector = new ChangeDetector();
$changes = take($prices)
    ->cast($detector->detect(...))
    ->select() // Remove the null produced for the first element
    ->toList();
```

## Error Handling

If a transformation can throw exceptions, wrap it. The wrapper catches each exception and records the error.

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

### Practical Example: Processing API Responses

This example records errors while it processes API responses:

```php
use function Pipeline\take;

// Simulate API responses with potential failures
$apiResponses = [
    ['url' => '/users/1', 'data' => '{"id":1,"name":"Alice"}'],
    ['url' => '/users/2', 'data' => 'invalid json'],
    ['url' => '/users/3', 'data' => '{"id":3,"name":"Charlie"}'],
    ['url' => '/users/4', 'data' => null], // Failed request
];

// Process with error collection
$errors = [];

$validUsers = take($apiResponses)
    ->cast(function ($response) use (&$errors) {
        if ($response['data'] === null) {
            $errors[] = ['url' => $response['url'], 'error' => 'Request failed'];
            return null;
        }

        $decoded = json_decode($response['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = ['url' => $response['url'], 'error' => 'Invalid JSON'];
            return null;
        }

        return $decoded;
    })
    ->select() // Remove nulls
    ->toList();

// $validUsers:
// [
//     ['id' => 1, 'name' => 'Alice'],
//     ['id' => 3, 'name' => 'Charlie'],
// ]
//
// $errors:
// [
//     ['url' => '/users/2', 'error' => 'Invalid JSON'],
//     ['url' => '/users/4', 'error' => 'Request failed'],
// ]
```

## Hierarchical Data

To process a nested data structure, apply the pipeline recursively to each level of the tree.

**Example: A `TreeProcessor`**

```php
use function Pipeline\take;

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
