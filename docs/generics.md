# Generic Type Support

Pipeline library includes generic type annotations that provide better type safety when using static analysis tools like PHPStan, Psalm, or PHPStorm.

## Overview

The `Standard` class now includes `@template` annotations that allow static analyzers to track types through pipeline transformations:

```php
/**
 * @template TKey
 * @template TValue
 */
class Standard implements IteratorAggregate, Countable
```

## Basic Usage

### Type-Safe Pipeline Creation

```php
use function Pipeline\fromArray;
use function Pipeline\fromValues;

// Create a pipeline with known types
/** @var Standard<int, string> $strings */
$strings = fromValues('hello', 'world', 'test');

// Or from an associative array
/** @var Standard<string, int> $numbers */  
$numbers = fromArray(['a' => 1, 'b' => 2, 'c' => 3]);
```

### Type Preservation with Filter

The `filter()` method preserves both key and value types:

```php
/** @var Standard<int, string> $strings */
$strings = fromValues('hello', 'world', 'test', 'php');

// Still Standard<int, string>
$longStrings = $strings->filter(fn(string $s): bool => strlen($s) > 4);

// Result is list<string>
$result = $longStrings->toList(); // ['hello', 'world']
```

### Type Transformation with Map

The `map()` method can transform value types while preserving keys:

```php
/** @var Standard<string, int> $numbers */
$numbers = fromArray(['a' => 1, 'b' => 2, 'c' => 3]);

// Transforms to Standard<string, string>
$strings = $numbers->map(fn(int $n): string => "Number: $n");

// Result is array<string, string>
$result = $strings->toAssoc(); // ['a' => 'Number: 1', 'b' => 'Number: 2', ...]
```

### Type Transformation with Cast

The `cast()` method is similar to `map()` but doesn't treat generators specially:

```php
/** @var Standard<int, float> $floats */
$floats = fromValues(1.5, 2.7, 3.9);

// Transforms to Standard<int, int>
$integers = $floats->cast(fn(float $f): int => (int) round($f));

// Result is list<int>
$result = $integers->toList(); // [2, 3, 4]
```

## Terminal Operations

Terminal operations return concrete types:

```php
/** @var Standard<string, mixed> $data */
$data = take($someIterable);

// Returns list<mixed> - numeric keys, values only
$list = $data->toList();

// Returns array<string, mixed> - preserves keys
$assoc = $data->toAssoc();

// Returns int
$count = $data->count();
```

## Advanced Type Tracking

### Reduce with Type Safety

The `reduce()` and `fold()` methods support typed accumulators:

```php
/** @var Standard<int, string> $words */
$words = fromValues('hello', 'world', 'test');

// Type-safe reduction
$totalLength = $words->reduce(
    fn(int $carry, string $word): int => $carry + strlen($word),
    0 // initial value
); // Returns int
```

### Conditional Types

Some methods return different types based on arguments:

```php
/** @var Standard<int, string> $strings */
$strings = fromValues('a', 'b', 'c');

// With callback: transforms type
$lengths = $strings->map(fn(string $s): int => strlen($s)); // Standard<int, int>

// Without callback: preserves type  
$same = $strings->map(); // Standard<int, string>
```

## Static Analysis Tools

### PHPStan

Add to your `phpstan.neon`:

```neon
parameters:
    level: max
```

### Psalm

The library includes Psalm annotations. Use error level 2 or lower:

```xml
<psalm errorLevel="2">
    <!-- your config -->
</psalm>
```

### IDE Support

Modern IDEs like PHPStorm will automatically recognize the generic annotations and provide:
- Accurate autocompletion
- Type hints for callback parameters
- Return type information

## Backward Compatibility

The generic type annotations are implemented using PHPDoc only, ensuring full backward compatibility:

- No runtime changes
- No breaking changes to method signatures  
- Existing code continues to work unchanged
- Child classes can override methods with any return type

## Limitations

Due to PHP's type system and the library's mutable design:

1. Each pipeline instance maintains the same generic types throughout its lifetime
2. Type transformations (like `map()`) return the same instance with updated behavior
3. Pipelines cannot be reused after terminal operations due to generator exhaustion
4. Some operations internally change types in ways that static analyzers cannot track:
   - `chunk()` transforms values into arrays
   - `flip()` swaps keys and values
   - `values()` resets keys to sequential integers
   - `keys()` makes values from keys

### Static Analysis Warnings

When running static analyzers on the library itself, you may see warnings about type mismatches. These are due to the internal implementation details and do not affect the type safety of user code.

For user code, the generic annotations provide accurate type information for:
- Pipeline creation
- Filter operations (type-preserving)
- Map/cast operations (type-transforming)
- Terminal operations (toList, toAssoc)

### Working with Dynamic Types

If you need operations that change types dynamically, you can:

```php
// Start with known types
/** @var Standard<string, int> $numbers */
$numbers = fromArray(['a' => 1, 'b' => 2, 'c' => 3]);

// After flip(), keys and values are swapped
// Annotate the new type manually
/** @var Standard<int, string> $flipped */
$flipped = $numbers->flip();

// Continue with correct types
$result = $flipped->toAssoc(); // array<int, string>
```

## Best Practices

1. **Annotate pipeline creation** for better type inference:
   ```php
   /** @var Standard<int, User> $users */
   $users = take($userRepository->findAll());
   ```

2. **Use specific callbacks** to help type inference:
   ```php
   // Good: Type inference works
   $names = $users->map(fn(User $user): string => $user->getName());
   
   // Less ideal: May need additional annotations
   $names = $users->map([$this, 'getName']);
   ```

3. **Leverage IDE support** - Let your IDE suggest parameter types in callbacks

4. **Run static analysis** in CI to catch type errors early
