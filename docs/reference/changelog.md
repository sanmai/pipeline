# Changelog

This document outlines the version history and key changes for the Pipeline library.

## Version 6.x

### Key Features

-   **PHP 8.2+ Requirement**: The minimum required PHP version has been updated to 8.2.
-   **Strict Filtering**: The `filter()` method now includes a `strict` mode for more precise control over data cleaning.
-   **Improved Type Safety**: Enhanced type annotations and static analysis support for PHPStan, Psalm, and Phan.
-   **Performance Optimizations**: Array operations are now faster, and the `cast()` method is optimized for arrays.

### Breaking Changes

-   The minimum required PHP version is now 8.2.
-   Some internal method signatures have been updated to improve type safety.

### Deprecations

-   `toArrayPreservingKeys()`: Use `toAssoc()` instead.

## Migration Guide

### Migrating from v5.x to v6.x

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
-   Additional statistical methods

For the latest updates, please refer to the [GitHub repository](https://github.com/sanmai/pipeline).