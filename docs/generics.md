# Type Safety Guide

So you want your IDE to be smarter about Pipeline? Great! We've added type annotations that help PHPStan, Psalm, and PHPStorm understand exactly what's flowing through your pipelines.

## How it works

The `Standard` class now uses generic types (those `@template` things in the PHPDocs). Don't worry if you've never used them - your IDE handles all the magic:

```php
/**
 * @template TKey
 * @template TValue
 */
class Standard implements IteratorAggregate, Countable
```

Basically, this tells your tools "hey, this pipeline has keys of type TKey and values of type TValue."

## Getting Started

### Creating pipelines with known types

Here's the cool part - you often don't need to do anything special:

```php
use function Pipeline\fromArray;
use function Pipeline\fromValues;

// Your IDE figures this out automatically
$strings = fromValues('hello', 'world', 'test');

// Same with arrays
$numbers = fromArray(['a' => 1, 'b' => 2, 'c' => 3]);
```

But if you want to be explicit (or your IDE needs a hint), you can add type annotations:

```php
/** @var Standard<int, string> $strings */
$strings = fromValues('hello', 'world', 'test');
```

### Filtering keeps your types intact

When you filter a pipeline, the types stay the same - you just have fewer items:

```php
$pipeline = fromValues('hello', 'world', 'test', 'php');

// Your IDE knows $s is a string
$pipeline->filter(fn($s) => strlen($s) > 4);

// And it knows you'll get back a list of strings
$result = $pipeline->toList(); // ['hello', 'world']
```

### Transforming types with map()

This is where it gets fun - `map()` can change your value types:

```php
$pipeline = fromArray(['a' => 1, 'b' => 2, 'c' => 3]);

// Transform numbers to strings (modifies the same instance)
$pipeline->map(fn($n) => "Number: $n");

// Your IDE knows the result has string values
$result = $pipeline->toAssoc(); // ['a' => 'Number: 1', 'b' => 'Number: 2', ...]
```

Notice how your IDE knew `$n` was an integer? That's the type system at work!

### Using cast() for simpler transformations

`cast()` is like `map()` but simpler - it's perfect when you just want to change types:

```php
$pipeline = fromValues(1.5, 2.7, 3.9);

// Round floats to integers (modifies the same instance)
$pipeline->cast(fn($f) => (int) round($f));

$result = $pipeline->toList(); // [2, 3, 4]
```

## Getting data out

When you're done transforming, you need to get your data out. These methods give you regular PHP arrays and values:

```php
$data = take($someIterable);

// Get just the values as a list
$list = $data->toList();

// Keep the keys too
$assoc = $data->toAssoc();

// Or just count items
$count = $data->count();
```

Your IDE knows exactly what type each method returns!

## More cool stuff

### Type-safe reduce operations

Even `reduce()` and `fold()` get type checking:

```php
$pipeline = fromValues('hello', 'world', 'test');

// Your IDE knows $carry is int and $word is string
$totalLength = $pipeline->reduce(
    fn($carry, $word) => $carry + strlen($word),
    0 // start from zero
);
// Note: pipeline is now consumed and can't be used again
```

### Methods that adapt to your usage

Some methods behave differently based on arguments:

```php
$pipeline = fromValues('a', 'b', 'c');

// With a callback - transforms the type
$pipeline->map(fn($s) => strlen($s));
$lengths = $pipeline->toList(); // [1, 1, 1]

// Without a callback on a fresh pipeline - acts as pass-through
$pipeline2 = fromValues('x', 'y', 'z');
$pipeline2->map(); // no-op
$same = $pipeline2->toList(); // ['x', 'y', 'z']
```

## Setting up your tools

### PHPStan

Just add Pipeline to your project and PHPStan will understand it. For best results, use a high level:

```neon
parameters:
    level: 7  # or max
```

### Psalm

We've included Psalm annotations too. Works best with:

```xml
<psalm errorLevel="2">
    <!-- your config -->
</psalm>
```

### IDE Support

PHPStorm, VS Code (with plugins), and other modern IDEs will automatically:
- Suggest the right types in callbacks
- Autocomplete methods based on your data
- Warn you about type mismatches

## Don't worry about breaking changes

All these type improvements are just PHPDoc comments. That means:

- Your existing code works exactly the same
- No runtime performance impact
- You can ignore the types if you want
- Everything is 100% backward compatible

## Things to know

Pipeline works a bit differently than you might expect:

1. **Pipelines are mutable** - when you call `map()`, you're modifying the same pipeline, not creating a new one:
   ```php
   $pipeline = fromValues(1, 2, 3);
   $same = $pipeline->map(fn($n) => $n * 2); // $same === $pipeline
   ```

2. **Type tracking works with both chaining and separate statements** - Thanks to `@phpstan-self-out`:
   ```php
   // ✅ GOOD: Method chaining - PHPStan tracks the type changes
   $result = fromArray(['a' => 1, 'b' => 2])
       ->map(fn($n) => $n * 2)
       ->cast(fn($n) => new Foo($n))
       ->toList(); // PHPStan knows this is list<Foo>
   
   // ✅ ALSO GOOD: Separate statements - PHPStan still tracks the type changes!
   $pipeline = fromArray(['a' => 1, 'b' => 2]);
   $pipeline->map(fn($n) => $n * 2);
   $pipeline->cast(fn($n) => new Foo($n));
   $result = $pipeline->toList(); // PHPStan knows this is list<Foo>!
   ```
   
   This works because Pipeline uses both `@return Standard<NewType>` (for method chaining) and `@phpstan-self-out self<NewType>` (for tracking mutations). This dual annotation approach gives you complete type safety regardless of your coding style.

3. **One-time use** - after you call `toList()` or `reduce()`, the pipeline is exhausted:
   ```php
   $pipeline = fromValues(1, 2, 3);
   $first = $pipeline->toList();  // Works fine
   $second = $pipeline->toList(); // ERROR! Already consumed
   ```

4. **Some operations confuse static analyzers**:
   - `chunk()` turns your values into arrays of values
   - `flip()` swaps keys and values around
   - `values()` gives you new numeric keys
   - `keys()` makes the keys become your values

For these tricky operations, you might need to help your IDE with a type hint.

### About those warnings

If you run static analysis on the Pipeline library itself, you'll see some warnings. Don't worry - that's just because we do some clever stuff internally. Your code that uses Pipeline will get proper type checking for:

- Creating pipelines
- Filtering (keeps types)
- Mapping (changes types)
- Getting results (toList, toAssoc, etc.)

### When types get tricky

For operations that swap types around, you might need to help your IDE:

```php
// Start with string keys and int values
$pipeline = fromArray(['a' => 1, 'b' => 2, 'c' => 3]);

// flip() swaps keys and values
$pipeline->flip();

// Your IDE might not track this automatically, so you can hint:
/** @var Standard<int, string> $pipeline */

// Now it knows the types again
$result = $pipeline->toAssoc(); // array<int, string>
```

## Tips for best results

1. **Help your IDE when needed**:
   ```php
   // When the source isn't obvious, add a hint
   /** @var Standard<int, User> $pipeline */
   $pipeline = take($userRepository->findAll());
   ```

2. **Use arrow functions for better type inference**:
   ```php
   // Your IDE can figure this out
   $pipeline->map(fn($user) => $user->getName());
   $names = $pipeline->toList();
   
   // Method references might need help
   $pipeline2 = take($users);
   $pipeline2->map([$this, 'getName']);
   ```

3. **Trust your IDE** - Modern IDEs are pretty smart about figuring out types

4. **Add static analysis to CI** - Catch type issues before they hit production

## That's it!

The type system is here to help you, not get in your way. Use as much or as little as you want - Pipeline will keep working just like it always has, but now with better IDE support when you need it.
