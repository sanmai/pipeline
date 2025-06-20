# Walkthrough: A Simple Pipeline

This walkthrough demonstrates the core concepts of the Pipeline library through a practical example.

## CSV Processing Pipeline

Let's build a pipeline that processes CSV data step by step:

```php
use function Pipeline\take;

// Sample CSV data with header row
$csv = <<<CSV
name,age,city
Alice,30,New York
Bob,25,Los Angeles
Charlie,35,Chicago
David,28,New York
CSV;

// Build the pipeline
$users = take(explode("\n", $csv))
    ->map('str_getcsv')           // Convert each line to array
    ->slice(1)                    // Skip header row
    ->map(fn($row) => [          // Transform to associative array
        'name' => $row[0],
        'age' => (int)$row[1],
        'city' => $row[2]
    ])
    ->filter(fn($user) => $user['age'] >= 30)  // Keep users 30+
    ->toList();                   // Execute pipeline

// Result: [
//   ['name' => 'Alice', 'age' => 30, 'city' => 'New York'],
//   ['name' => 'Charlie', 'age' => 35, 'city' => 'Chicago']
// ]
```

## Understanding the Flow

1. **Create the pipeline**: `take()` initializes a pipeline with your data
2. **Transform line by line**: `map('str_getcsv')` parses CSV format
3. **Filter data**: `slice(1)` removes the header row
4. **Structure the data**: The second `map()` creates proper objects
5. **Apply business logic**: `filter()` keeps only relevant records
6. **Execute**: `toList()` runs the pipeline and returns results

## Key Concepts Demonstrated

- **Lazy evaluation**: Nothing happens until `toList()` is called
- **Method chaining**: Each method returns the pipeline for fluent syntax
- **Transformation**: `map()` changes data structure
- **Filtering**: `filter()` removes unwanted elements
- **Terminal operation**: `toList()` executes and collects results

## Next Steps

Now that you understand the basics, explore the [Pipeline Cookbook](../cookbook/index.md) for solutions to specific problems, or dive into the [API Reference](../api/creation.md) for detailed method documentation.