# Changelog

This documentation always describes the latest version of the library.

For the detailed version history, see the [GitHub releases page](https://github.com/sanmai/pipeline/releases). The library follows semantic versioning.

## Upgrading from Older Versions

Code written against older versions of the library may use APIs that have since been renamed or removed. The replacements are mechanical:

### Array Conversion

The old `toArray()` method was split into two explicit methods:

```php
// Before: keys discarded
$result = take($data)->toArray();
$result = take($data)->toArray(false);

// After
$result = take($data)->toList();

// Before: keys preserved
$result = take($data)->toArray(true);
$result = take($data)->toArrayPreservingKeys();

// After
$result = take($data)->toAssoc();
```

### Filtering Semantics

`filter()` always removed every falsy value, like `array_filter()`. The newer `select()` removes only `null` and `false` by default, which is the safer choice when `0` or empty strings are valid data:

```php
$result = take([0, '', false, null])->filter()->toList(); // []
$result = take([0, '', false, null])->select()->toList(); // [0, '']
```

Existing `filter()` calls keep working unchanged.

## Newer Methods Worth Adopting

If your codebase predates them, these methods can simplify common patterns:

```php
// tap(): side effects without modifying values
take($users)
    ->tap(fn($user) => logger()->info("Processing: {$user->name}"))
    ->map(fn($user) => $user->email)
    ->toList();

// chunkBy(): variable-size chunks, sizes from any iterable
take($records)
    ->chunkBy([10, 100, 1000])
    ->each(fn($batch) => Database::bulkInsert($batch));

// cursor(): pause and resume iteration without errors
$cursor = take($largeDataset)->cursor();
foreach ($cursor as $item) {
    process($item);
    if (needsPause()) {
        break;
    }
}
foreach ($cursor as $item) { // Continues where the first loop stopped
    process($item);
}

// peek(): look at the first N elements, then decide
$head = $pipeline->peek(5)->toList();
```

See the [Method Index](method-index.md) for the complete API.
