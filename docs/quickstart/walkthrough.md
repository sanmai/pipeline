# Walkthrough: Processing CSV Data

This walkthrough demonstrates the core concepts of the library by building a practical data processing pipeline to parse CSV data, skip the header, transform, filter, and collect the results.

## The Pipeline

Here is the complete pipeline:

```php
use function Pipeline\take;

// Sample CSV data with a header row
$csv = <<<
name,age,city
Alice,30,New York
Bob,25,Los Angeles
Charlie,35,Chicago
David,28,New York
CSV;

// Build the pipeline
$users = take(explode("\n", $csv))
    ->map(str_getcsv(...))        // 1. Parse each line into an array
    ->slice(1)                    // 2. Skip the header row
    ->map(fn($row) => [          // 3. Transform to an associative array
        'name' => $row[0],
        'age' => (int)$row[1],
        'city' => $row[2]
    ])
    ->filter(fn($user) => $user['age'] >= 30)  // 4. Keep users aged 30 or over
    ->toList();                   // 5. Execute the pipeline and collect results

// The final result:
// [
//   ['name' => 'Alice', 'age' => 30, 'city' => 'New York'],
//   ['name' => 'Charlie', 'age' => 35, 'city' => 'Chicago']
// ]
```

The code comments explain each step of the pipeline.

## Key Concepts

This example illustrates several core principles of the library:

-   **Lazy Evaluation**: No processing occurs until a terminal method like `toList()` is called.
-   **Method Chaining**: Each operation returns the pipeline object, allowing for a fluent and expressive syntax.
-   **Transformation**: `map()` is used to change the structure and format of the data.
-   **Filtering**: `filter()` and `slice()` are used to selectively remove data.

## Next Steps

-   Explore the [Cookbook](../cookbook/index.md) for more practical examples.
-   Consult the [API Reference](../api/creation.md) for detailed information on each method.

