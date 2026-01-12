# Changelog

This document outlines the version history and key changes for the Pipeline library.

## Version 7.x

### Key Features

-   **New Methods**: Added `tap()`, `chunkBy()`, and `cursor()` for more expressive pipelines.
-   **Enhanced peek()**: Now returns a Pipeline instance for fluent API chaining.
-   **PHP 8.5 Support**: Full compatibility with PHP 8.5.
-   **JIT Compatibility**: Workaround for PHP JIT NAN bug.

### New Methods in 7.x

-   **`tap(callable $callback)`** (7.2): Execute side effects without modifying values. Useful for logging or debugging.
-   **`chunkBy(callable $callback)`** (7.3): Create chunks with variable sizes based on a callback.
-   **`cursor()`** (7.6): Returns a forward-only iterator that maintains position across loop breaks.

### Breaking Changes

-   **`toArray()` now requires a parameter**: Use `toList()` for a simple array or `toAssoc()` for an associative array.
-   **`toArrayPreservingKeys()` removed**: Use `toAssoc()` instead.

## Version 6.x

### Key Features

-   **PHP 8.2+ Requirement**: The minimum required PHP version has been updated to 8.2.
-   **Strict Filtering**: The `filter()` method now includes a `strict` mode for more precise control over data cleaning.
-   **New Convenience Methods**: Added `first()`, `last()`, `toList()`, and `toAssoc()`.
-   **Improved Type Safety**: Enhanced type annotations and static analysis support for PHPStan and Psalm.
-   **Performance Optimizations**: The `cast()` method uses `array_map()` for arrays, improving performance.

### Breaking Changes from 5.x

-   The minimum required PHP version is now 8.2.
-   Some internal method signatures have been updated to improve type safety.

### Deprecations in 6.x

-   `toArray()`: Deprecated, use `toList()` or `toAssoc()` instead.
-   `toArrayPreservingKeys()`: Deprecated, use `toAssoc()` instead.

## Migration Guide

### Migrating from v6.x to v7.x

**Array Conversion (Breaking Change)**

The `toArray()` method now requires a boolean parameter. Replace all previous usages as follows:

```php
// Before: toArray() or toArray(false) - get indexed array
$result = take([1, 2, 3])->toArray();
$result = take([1, 2, 3])->toArray(false);

// After: use toList()
$result = take([1, 2, 3])->toList();


// Before: toArray(true) - get associative array with preserved keys
$result = take($data)->toArray(true);

// After: use toAssoc()
$result = take($data)->toAssoc();
```

**toArrayPreservingKeys() Removed**

```php
// Before (v6.x)
$result = take($data)->toArrayPreservingKeys();

// After (v7.x)
$result = take($data)->toAssoc();
```

**New Features to Consider**

After migrating, consider using these new methods for cleaner code:

```php
// tap() for side effects without modifying values
take($users)
    ->tap(fn($user) => logger()->info("Processing: {$user->name}"))
    ->map(fn($user) => $user->email)
    ->toList();

// chunkBy() for variable-size chunks
take($records)
    ->chunkBy(fn($record) => $record->category)
    ->each(fn($chunk) => processCategory($chunk));

// cursor() for pauseable iteration
$cursor = take($largeDataset)->cursor();
foreach ($cursor as $item) {
    process($item);
    if (needsPause()) {
        break; // Can continue later from same position
    }
}
// Continue iteration later...
foreach ($cursor as $item) {
    process($item);
}
```

### Migrating from v5.x to v6.x

**Array Conversion**

-   Replace `->toArray()` or `->toArray(false)` with `->toList()`.
-   Replace `->toArray(true)` or `->toArrayPreservingKeys()` with `->toAssoc()`.

**Filtering**

To maintain the old filtering behavior (which removes all falsy values), no changes are needed. To use the new, safer strict filtering, add the `strict: true` parameter:

```php
// Old behavior (removes all falsy values)
$result = take([0, '', false, null])->filter()->toList(); // []

// New strict mode (removes only null and false)
$result = take([0, '', false, null])->filter(strict: true)->toList(); // [0, '']
```

## Future Development

The library follows semantic versioning. Future development will focus on:

-   Performance and memory optimizations
-   Enhanced type safety
-   Additional utility methods

For the latest updates, please refer to the [GitHub repository](https://github.com/sanmai/pipeline).
