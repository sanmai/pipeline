# Complex Pipeline Patterns

This section explores advanced techniques for building sophisticated and maintainable data processing pipelines.

## Pipeline Composition with Reusable Components

For complex workflows, encapsulate business logic into separate classes or functions. This allows you to build clean, readable pipelines from reusable, testable components.

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

These components can then be used to build a clean pipeline:

```php
use App\Pipeline\Components\UserProcessor;
use function Pipeline\take;

$processedUsers = take($rawUsers)
    ->filter(UserProcessor::filterActive(...))
    ->map(UserProcessor::normalize(...))
    ->toList();
```

This approach improves readability, testability, and reusability.

## Stateful Transformations

For operations that require state to be maintained between elements, use a class to encapsulate the state.

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
    ->map($detector->detect(...))
    ->filter() // Remove the first null
    ->toList();
```

## Graceful Error Handling

For pipelines that may encounter errors, you can handle exceptions within a `map` step or use a dedicated error-handling object.

**Example: Processing API Responses**

Here's a practical example of handling errors when processing API responses, collecting both valid results and errors.

```php
use function Pipeline\take;

// Simulate API responses with potential failures
$apiResponses = [
    ['url' => '/users/1', 'data' => '{"id":1,"name":"Alice"}'],
    ['url' => '/users/2', 'data' => 'invalid json'],
    ['url' => '/users/3', 'data' => '{"id":3,"name":"Charlie"}'],
    ['url' => '/users/4', 'data' => null], // Failed request
];

$validUsers = [];
$errors = [];

take($apiResponses)
    ->map(function($response) use (&$errors) {
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
    ->filter() // Remove nulls from failed steps
    ->each(function($user) use (&$validUsers) {
        $validUsers[] = $user;
    });

// $validUsers will contain the valid user data.
// $errors will contain information about the failed responses.
```

## Processing Hierarchical Data

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
