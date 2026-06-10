# Walkthrough: Processing CSV Data

This walkthrough demonstrates the core concepts of the library by building a practical data processing pipeline.

## The Goal

Our objective is to process a string of CSV data. We will parse the data, skip the header, transform it into a more usable format, filter it based on a condition, and finally, collect the results.

## The Pipeline

Here is the complete pipeline:

```php
use function Pipeline\take;

// Sample CSV data with a header row
$csv = <<<CSV
name,age,city
Alice,30,New York
Bob,25,Los Angeles
Charlie,35,Chicago
David,28,New York
CSV;

// Build the pipeline
$users = take(explode("\n", $csv))
    ->map(str_getcsv(...))                     // 1. Parse each line into an array
    ->slice(1)                                 // 2. Skip the header row
    ->map(fn($row) => [                        // 3. Transform into an associative array
        'name' => $row[0],
        'age' => (int) $row[1],
        'city' => $row[2],
    ])
    ->filter(fn($user) => $user['age'] >= 30)  // 4. Keep users aged 30 or over
    ->toList();                                // 5. Execute and collect the results

// The final result:
// [
//   ['name' => 'Alice', 'age' => 30, 'city' => 'New York'],
//   ['name' => 'Charlie', 'age' => 35, 'city' => 'Chicago'],
// ]
```

## Step-by-Step Explanation

1. **`take(explode("\n", $csv))`**: We begin by creating a pipeline from the CSV data. `explode()` splits the string into an array of lines. In a real application this would more likely be `take(new SplFileObject('users.csv'))`, streaming the file line by line.

2. **`map(str_getcsv(...))`**: The `map()` method applies `str_getcsv()` to each line, converting each CSV string into an array of values. Note the first-class callable syntax: any callable works as a pipeline stage.

3. **`slice(1)`**: This skips the first element of the pipeline—the header row.

4. **`map(fn($row) => ...)`**: We use `map()` again to transform each indexed row into a more readable associative array, casting the age to an integer along the way.

5. **`filter(fn($user) => ...)`**: The `filter()` method applies our business logic, keeping only the users who are 30 years of age or older.

6. **`toList()`**: This is a terminal operation. It triggers the execution of all the previous (lazy) operations and collects the final results into an array.

## Key Concepts

This example illustrates several core principles of the library:

- **Lazy Evaluation**: Steps 2 through 5 only describe the processing; nothing runs until `toList()` is called in step 6. Each line then flows through the whole chain, one at a time.
- **Method Chaining**: Each operation returns the same pipeline object, allowing for a fluent and expressive syntax.
- **Transformation**: `map()` changes the structure and format of the data.
- **Filtering**: `filter()` and `slice()` selectively remove data.

## Next Steps

- Explore the [Cookbook](../cookbook/index.md) for ready-to-use recipes.
- Consult the [API Reference](../api/creation.md) for detailed information on each method.
