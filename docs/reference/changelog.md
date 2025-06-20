# Changelog

## Version History

### v6.x Series

#### Key Features
- PHP 8.2+ requirement
- Strict mode for `filter()` method
- Improved type safety and static analysis support
- Performance optimizations for array operations

#### Breaking Changes from v5.x
- Minimum PHP version increased to 8.2
- Some internal method signatures changed for better type safety

### Method Evolution

#### Deprecated Methods
- `toArray()` - Use `toList()` or `toAssoc()` instead
- `toArrayPreservingKeys()` - Use `toAssoc()` instead

#### New Methods (Recent Versions)
- `toList()` - Replaces `toArray(false)`
- `toAssoc()` - Replaces `toArray(true)`
- `strict` parameter for `filter()` - More precise filtering control

### Performance Improvements

- Array operations now use native PHP functions when possible
- `cast()` method optimized with `array_map` for arrays
- Memory usage reduced for large dataset processing
- Generator handling improved for better lazy evaluation

### Static Analysis Support

- PHPStan max level support
- Psalm error level 2 compatibility
- Phan integration
- Comprehensive type annotations

## Migration Guide

### From v5.x to v6.x

```php
// Old way
$result = $pipeline->toArray();  // Without preserving keys
$result = $pipeline->toArray(true);  // Preserving keys

// New way
$result = $pipeline->toList();  // Without preserving keys
$result = $pipeline->toAssoc();  // Preserving keys
```

### Filter Strict Mode

```php
// Old behavior (all falsy values removed)
$result = take([0, '', false, null])->filter()->toList();
// Result: []

// New strict mode (only null and false removed)
$result = take([0, '', false, null])->filter(null, strict: true)->toList();
// Result: [0, '']
```

## Future Considerations

The library follows semantic versioning and maintains backward compatibility within major versions. Future enhancements focus on:

- Performance optimization
- Enhanced type safety
- Additional statistical methods
- Improved memory efficiency

For the latest changes, see the [GitHub repository](https://github.com/sanmai/pipeline).