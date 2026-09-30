# Walkthrough: Processing CSV Data

This walkthrough builds a CSV processing pipeline and shows the main concepts of the library.

## The Goal

The pipeline processes a string of CSV data. It parses each line, skips the header, converts each row into an associative array, filters the rows by age, and collects the result.

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
    ->map(str_getcsv(?, escape: ''))             // 2. Parse each line into an array
    ->slice(1)                                 // 3. Skip the header row
    ->map(fn($row) => [                        // 4. Transform into an associative array
        'name' => $row[0],
        'age' => (int) $row[1],
        'city' => $row[2],
    ])
    ->filter(fn($user) => $user['age'] >= 30)  // 5. Keep users aged 30 or over
    ->toList();                                // 6. Execute and collect the results

// The final result:
// [
//   ['name' => 'Alice', 'age' => 30, 'city' => 'New York'],
//   ['name' => 'Charlie', 'age' => 35, 'city' => 'Chicago'],
// ]
```

## Step-by-Step Explanation

1. **`take(explode("\n", $csv))`**: This call creates a pipeline from the CSV data. `explode()` splits the string into an array of lines. To read a file line by line, use `take(new SplFileObject('users.csv'))` instead.

2. **`map(str_getcsv(?, escape: ''))`**: `map()` applies `str_getcsv()` to each line and converts each CSV string into an array of values. This step uses PHP 8.6 partial function application. Any callable can be a pipeline stage. On earlier PHP versions, use a closure: `fn(string $line) => str_getcsv($line, escape: '')`.

3. **`slice(1)`**: This call skips the first element, which is the header row.

4. **`map(fn($row) => ...)`**: The second `map()` converts each indexed row into an associative array and casts the age to an integer.

5. **`filter(fn($user) => ...)`**: `filter()` keeps only the users aged 30 or older.

6. **`toList()`**: This terminal operation starts the execution of all previous lazy operations and collects the values into an array.

## Key Concepts

This example shows these principles of the library:

- **Lazy Evaluation**: Steps 2 through 5 define the processing. Execution is deferred until `toList()` in step 6. Then each line passes through all stages, one line at a time.
- **Method Chaining**: Each operation returns the same pipeline object, so you can chain the calls.
- **Transformation**: `map()` changes the structure and format of the data.
- **Filtering**: `filter()` and `slice()` remove items.

## Next Steps

- See the [Cookbook](../cookbook/index.md) for recipes.
- See the [API Reference](../api/creation.md) for details on each method.
