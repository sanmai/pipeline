# Collection Methods

Methods for converting pipeline data into arrays and managing iteration. These are typically terminal operations.

## Array Conversion Methods

### `toList()`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Terminal operation |
> | **Terminal?** | Yes |
> | **When to Use** | To get results as a sequential array without keys |
> | **Key Behavior** | Discards all keys, creates numeric indices starting from 0 |

Converts the pipeline to an indexed array, discarding all keys.

**Returns:** array - Indexed array with sequential numeric keys starting from 0

**Examples:**

```php
// Basic conversion
$result = take([1, 2, 3, 4, 5])->toList();
// Result: [1, 2, 3, 4, 5]

// Discards original keys
$result = take(['a' => 1, 'b' => 2, 'c' => 3])->toList();
// Result: [1, 2, 3] (keys lost)

// After transformation
$result = take(['hello', 'world'])
    ->map('strtoupper')
    ->toList();
// Result: ['HELLO', 'WORLD']

// From generator
$result = take(function() {
    yield 'a';
    yield 'b';
    yield 'c';
})->toList();
// Result: ['a', 'b', 'c']

// Empty pipeline
$result = take([])->toList();
// Result: []

// Flattening with list
$result = take([[1, 2], [3, 4]])
    ->flatten()
    ->toList();
// Result: [1, 2, 3, 4]
```

### `toAssoc()`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Terminal operation |
> | **Terminal?** | Yes |
> | **When to Use** | To preserve key-value associations |
> | **Key Behavior** | Maintains original keys, duplicates overwrite |

Converts the pipeline to an associative array, preserving keys.

**Returns:** array - Associative array with preserved keys

**Examples:**

```php
// Preserves keys
$result = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->map(fn($x) => $x * 2)
    ->toAssoc();
// Result: ['a' => 2, 'b' => 4, 'c' => 6]

// Duplicate keys (last value wins)
$result = take(function() {
    yield 'key' => 'first';
    yield 'key' => 'second';
    yield 'key' => 'third';
})->toAssoc();
// Result: ['key' => 'third']

// Mixed keys
$result = take([
    'name' => 'Alice',
    'age' => 30,
    0 => 'extra',
    'city' => 'NYC'
])->toAssoc();
// Result: ['name' => 'Alice', 'age' => 30, 0 => 'extra', 'city' => 'NYC']

// After key manipulation
$result = take(['a' => 1, 'b' => 2])
    ->flip()
    ->toAssoc();
// Result: [1 => 'a', 2 => 'b']
```

### `toArray(bool $preserve_keys = false)` [DEPRECATED]

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Terminal operation (deprecated) |
> | **Terminal?** | Yes |
> | **When to Use** | Never - use `toList()` or `toAssoc()` instead |
> | **Key Behavior** | Legacy method, behavior depends on parameter |

Legacy method for array conversion. Use `toList()` or `toAssoc()` instead.

**Parameters:**
- `$preserve_keys` (bool): Whether to preserve keys

**Returns:** array

**Examples:**

```php
// Without preserving keys (use toList() instead)
$result = take(['a' => 1, 'b' => 2])->toArray();
// Result: [1, 2]

// Preserving keys (use toAssoc() instead)
$result = take(['a' => 1, 'b' => 2])->toArray(true);
// Result: ['a' => 1, 'b' => 2]
```

### `toArrayPreservingKeys()` [DEPRECATED]

> **Quick Reference**
> 
> | | |
> |---|---|
> | **Type** | Terminal operation (deprecated) |
> | **Terminal?** | **Yes** |
> | **Execution** | Depends on Input |
> | **Key Behavior** | Legacy method - use `toAssoc()` instead. |

Legacy method. Use `toAssoc()` instead.

**Returns:** array - Same as `toAssoc()`

## Iterator Access

### `getIterator()`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Iterator access |
> | **Terminal?** | No (but exposes internal state) |
> | **When to Use** | When you need manual iterator control or foreach support |
> | **Key Behavior** | Enables foreach loops, returns EmptyIterator for empty pipelines |

Returns an iterator for the pipeline data. Implements `IteratorAggregate`.

**Returns:** Traversable - Iterator over pipeline elements

**Examples:**

```php
// Direct iteration
$pipeline = take([1, 2, 3]);
foreach ($pipeline as $value) {
    echo $value . "\n";
}
// Output: 1, 2, 3

// With keys
$pipeline = take(['a' => 1, 'b' => 2]);
foreach ($pipeline as $key => $value) {
    echo "$key: $value\n";
}
// Output: a: 1, b: 2

// Manual iterator control
$iterator = take([1, 2, 3])->getIterator();
$iterator->rewind();
while ($iterator->valid()) {
    echo $iterator->current() . "\n";
    $iterator->next();
}

// Empty pipeline returns EmptyIterator
$iterator = take()->getIterator();
// Returns: EmptyIterator instance
```

## Lazy Evaluation Control

### `stream()`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Evaluation control |
> | **Terminal?** | No |
> | **When to Use** | To force element-by-element processing and reduce memory usage |
> | **Key Behavior** | Converts arrays to generators, prevents batch optimization |

Converts the pipeline's internal data source (typically an array) into a lazy, element-by-element stream, forcing all subsequent operations to process data one item at a time.

**Returns:** $this (Pipeline\Standard instance)

**When to Use:**

The primary purpose of `stream()` is to switch from the library's optimized **batch processing** for arrays to a **streaming model**. This is crucial for managing memory when you have a large array and apply transformations that would otherwise create large, intermediate arrays.

### How `stream()` Changes Behavior

Understanding this is key to performance tuning.

* **Without `stream()` (Array Batch Processing):** When the pipeline holds an array, each method is optimized to process the *entire array at once* before passing it to the next step. For example, `->map(...)->filter(...)` will first create a **new, full array** with all the mapped values, and only then will `filter()` iterate over that new array to produce the final result. This can lead to high peak memory usage.

* **With `stream()` (Element-at-a-Time Streaming):** After calling `stream()`, each element is pulled through the *entire chain of operations* individually. A single element is mapped, then immediately filtered, and only then is the next element from the source array processed. This avoids the creation of large intermediate arrays.

**Example: Managing Intermediate Memory**

```php
$largeArray = range(1, 100000);

// --- Scenario 1: WITHOUT stream() ---
// Creates a full intermediate array. Peak memory is higher.
$result = take($largeArray)
    // Step 1: map() creates a NEW array of 100,000 strings in memory.
    ->map(fn($id) => "user_id_{$id}")
    // Step 2: filter() processes that new 100,000-item array.
    ->filter(fn($user) => substr($user, -1) === '5')
    ->toList();

// --- Scenario 2: WITH stream() ---
// Processes one element at a time. Peak memory is significantly lower.
$result = take($largeArray)
    ->stream() // Switch to one-by-one processing.
    // Now, for each number:
    // 1. One element (e.g., 15) is mapped to "user_id_15".
    // 2. That single string is immediately passed to filter().
    // 3. The process repeats for the next number. No large intermediate array is ever created.
    ->map(fn($id) => "user_id_{$id}")
    ->filter(fn($user) => substr($user, -1) === '5')
    ->toList();
```

**Performance Trade-offs:**

- **Memory**: `stream()` significantly reduces peak memory usage by avoiding intermediate arrays
- **Speed**: Batch processing (without `stream()`) is typically faster for small-to-medium arrays
- **Best Practice**: Use `stream()` when memory is a concern or when transformations are expensive

**Examples:**

```php
// Force streaming mode for memory efficiency
$result = take($largeArray)
    ->stream()  // Process element-by-element
    ->map(fn($x) => expensiveTransform($x))
    ->filter(fn($x) => $x > threshold())
    ->toList();

// Useful when transformations pull additional data
take($userIds)
    ->stream()  // Avoid loading all user data at once
    ->map(fn($id) => fetchUserData($id))  // This might load lots of data
    ->filter(fn($user) => $user->isActive())
    ->each(fn($user) => processUser($user));

// Convert array to generator for controlled processing
$streamed = take([1, 2, 3, 4, 5])
    ->stream()  // Now uses generator internally
    ->map(fn($x) => expensiveOperation($x));
// Computation deferred until iteration
```

**When NOT to use `stream()`:**

- Small arrays where performance matters more than memory
- Simple transformations that don't create large intermediate values
- When the source is already a generator or lazy iterator

## Element Iteration

### `each(callable $func, bool $discard = true)`

> **Quick Reference**
> 
> | | |
> |:---|:---|
> | **Type** | Terminal operation |
> | **Terminal?** | Yes (by default) |
> | **When to Use** | For side effects like saving to database or logging |
> | **Key Behavior** | Executes immediately, discards pipeline unless $discard=false |

Eagerly iterates over all elements, calling a function for each. Terminal operation.

**Parameters:**
- `$func` (callable): Function to call for each element
- `$discard` (bool): Whether to discard the pipeline after iteration (default: true)

**Returns:** void

**Callback signature:** `function(mixed $value, mixed $key): void`

**Examples:**

```php
// Basic iteration
take([1, 2, 3])->each(fn($x) => print("Value: $x\n"));
// Output: Value: 1, Value: 2, Value: 3

// With keys
take(['a' => 1, 'b' => 2])->each(function($value, $key) {
    echo "$key => $value\n";
});
// Output: a => 1, b => 2

// Side effects
$sum = 0;
take([1, 2, 3, 4, 5])->each(function($x) use (&$sum) {
    $sum += $x;
});
// $sum is now 15

// Process and discard
take(new SplFileObject('data.csv'))
    ->map('str_getcsv')
    ->each(fn($row) => insertToDatabase($row));
// Pipeline is discarded after processing

// Keep pipeline (rare use case)
$pipeline = take([1, 2, 3]);
$pipeline->each(fn($x) => print($x), discard: false);
// $pipeline still contains data

// Error handling
take($items)->each(function($item) {
    try {
        processItem($item);
    } catch (Exception $e) {
        logError($e);
    }
});
```

## Key and Value Methods

### `keys()`

> **Quick Reference**
> 
> | | |
> |---|---|
> | **Type** | Transformation |
> | **Terminal?** | No |
> | **Execution** | Always Lazy |
> | **Key Behavior** | Extracts keys as values, discarding original values. |

Extracts only the keys from the pipeline.

**Returns:** $this (Pipeline\Standard instance)

**Examples:**

```php
// Extract keys
$result = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->keys()
    ->toList();
// Result: ['a', 'b', 'c']

// Numeric keys
$result = take(['first', 'second', 'third'])
    ->keys()
    ->toList();
// Result: [0, 1, 2]

// After transformation
$result = take(['name' => 'Alice', 'age' => 30])
    ->flip()
    ->keys()
    ->toList();
// Result: ['Alice', 30]

// Use keys for processing
$result = take($data)
    ->keys()
    ->filter(fn($key) => str_starts_with($key, 'user_'))
    ->toList();
```

### `values()`

> **Quick Reference**
> 
> | | |
> |---|---|
> | **Type** | Transformation |
> | **Terminal?** | No |
> | **Execution** | Always Lazy |
> | **Key Behavior** | Resets keys to sequential numeric indices. |

Extracts only the values, discarding keys.

**Returns:** $this (Pipeline\Standard instance)

**Examples:**

```php
// Reset keys to sequential
$result = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->values()
    ->toAssoc();
// Result: [0 => 1, 1 => 2, 2 => 3]

// No effect on sequential arrays
$result = take([1, 2, 3])
    ->values()
    ->toList();
// Result: [1, 2, 3]

// Useful after filtering
$result = take(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4])
    ->filter(fn($x) => $x > 2)
    ->values()  // Reset to sequential keys
    ->toList();
// Result: [3, 4]
```

### `flip()`

> **Quick Reference**
> 
> | | |
> |---|---|
> | **Type** | Transformation |
> | **Terminal?** | No |
> | **Execution** | Always Lazy |
> | **Key Behavior** | Swaps keys and values, useful for deduplication. |

Swaps keys and values.

**Returns:** $this (Pipeline\Standard instance)

> **See Also:** This method is key to the [Memory-Efficient Deduplication recipe](../cookbook/index.md#memory-efficient-deduplication) in the Pipeline Cookbook.

**Examples:**

```php
// Basic flip
$result = take(['a' => 1, 'b' => 2])
    ->flip()
    ->toAssoc();
// Result: [1 => 'a', 2 => 'b']

// Deduplication pattern
$result = take([1, 2, 2, 3, 3, 3])
    ->flip()  // Values become keys (deduplicates)
    ->flip()  // Flip back
    ->values()  // Reset keys
    ->toList();
// Result: [1, 2, 3]

// String keys from values
$result = take(['apple', 'banana', 'cherry'])
    ->flip()
    ->toAssoc();
// Result: ['apple' => 0, 'banana' => 1, 'cherry' => 2]

// Creating lookup tables
$lookup = take($users)
    ->map(fn($user) => [$user['email'] => $user['id']])
    ->flatten()
    ->toAssoc();
```

### `tuples()`

> **Quick Reference**
> 
> | | |
> |---|---|
> | **Type** | Transformation |
> | **Terminal?** | No |
> | **Execution** | Always Lazy |
> | **Key Behavior** | Converts each element to [key, value] array pairs. |

Converts key-value pairs into [key, value] tuples.

**Returns:** $this (Pipeline\Standard instance)

**Examples:**

```php
// Convert to tuples
$result = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->tuples()
    ->toList();
// Result: [['a', 1], ['b', 2], ['c', 3]]

// Process keys and values together
$result = take(['name' => 'Alice', 'age' => 30])
    ->tuples()
    ->map(fn($tuple) => $tuple[0] . ': ' . $tuple[1])
    ->toList();
// Result: ['name: Alice', 'age: 30']

// Filter by key and value
$result = take(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4])
    ->tuples()
    ->filter(fn($tuple) => $tuple[0] !== 'b' && $tuple[1] < 4)
    ->map(fn($tuple) => $tuple[1])
    ->toList();
// Result: [1, 3]

// Reconstruct after processing
$result = take(['a' => 1, 'b' => 2])
    ->tuples()
    ->map(fn($tuple) => [$tuple[0] . '_key', $tuple[1] * 10])
    ->fold([], fn($acc, $tuple) => [...$acc, $tuple[0] => $tuple[1]]);
// Result: ['a_key' => 10, 'b_key' => 20]
```

## Usage Patterns

### Collecting Filtered Results

```php
// Collect matching items
$errors = take($logEntries)
    ->filter(fn($entry) => $entry['level'] === 'ERROR')
    ->toList();

// Collect with preserved indices
$filtered = take($items)
    ->filter($predicate)
    ->toAssoc();  // Keeps original array indices
```

### Key-Based Operations

```php
// Extract specific keys
$userIds = take($users)
    ->map(fn($user) => [$user['id'] => $user])
    ->flatten()
    ->keys()
    ->toList();

// Reindex after operations
$reindexed = take($data)
    ->filter($condition)
    ->sort($comparator)
    ->values()  // Reset to 0-based indices
    ->toList();
```

### Memory Management

```php
// Process without collecting
take($hugeDataset)
    ->filter($predicate)
    ->map($transformer)
    ->each($processor);  // No array created

// Collect only what's needed
$sample = take($hugeDataset)
    ->filter($predicate)
    ->slice(0, 100)  // Limit before collecting
    ->toList();
```

## Next Steps

- [Utility Methods](utility.md) - Helper methods for common operations
- [Statistics](statistics.md) - Statistical analysis methods