# Method Index

This index provides a quick reference to all methods and helper functions.

## Creation

| Method | Description |
| --- | --- |
| `new Standard()` | Creates a new pipeline instance. |
| `take()` | Creates a pipeline from one or more iterables. |
| `fromArray()` | Creates a pipeline from an array. |
| `fromValues()` | Creates a pipeline from individual values. |
| `map()` | Creates a pipeline from a generator function. |
| `zip()` | Creates a new pipeline by combining multiple iterables into tuples. |

## Adding Data

| Method | Description |
| --- | --- |
| `append()` | Adds elements from an iterable to the end. |
| `push()` | Appends individual values to the end. |
| `prepend()` | Adds elements from an iterable to the beginning. |
| `unshift()` | Prepends individual values to the beginning. |

## Transformation

| Method | Description |
| --- | --- |
| `map()` | Transforms elements (1-to-1 or 1-to-many with generators). |
| `cast()` | Performs a simple 1-to-1 transformation. |
| `flatten()` | Flattens a nested pipeline by one level. |
| `unpack()` | Unpacks array elements as arguments to a callback. |
| `chunk()` | Splits the pipeline into chunks of a fixed size. |
| `chunkBy()` | Splits the pipeline into chunks of variable sizes. |
| `slice()` | Extracts a portion of the pipeline. |

## Filtering

| Method | Description |
| --- | --- |
| `filter()` | Filters elements based on a condition. |
| `skipWhile()` | Skips elements from the beginning while a condition is true. |

## Aggregation

| Method | Description |
| --- | --- |
| `reduce()` | Reduces the pipeline to a single value. |
| `fold()` | Reduces the pipeline with a required initial value. |
| `count()` | Counts the number of elements. |
| `min()` | Finds the minimum value. |
| `max()` | Finds the maximum value. |

## Collection

| Method | Description |
| --- | --- |
| `toList()` | Converts the pipeline to a numerically indexed array. |
| `toAssoc()` | Converts the pipeline to an associative array. |
| `toArray()` | Converts to an array, alias for `toList()` or `toAssoc()`. |
| `getIterator()` | Returns an iterator to use the pipeline in a `foreach` loop. |
| `each()` | Eagerly iterates over the pipeline for side effects. |

## Statistics

| Method | Description |
| --- | --- |
| `finalVariance()` | Calculates a full set of statistics (terminal operation). |
| `runningVariance()` | Calculates statistics without consuming the pipeline. |

## Utility

| Method | Description |
| --- | --- |
| `reservoir()` | Selects a random subset of elements (terminal operation). |
| `runningCount()` | Counts elements without consuming the pipeline. |
| `stream()` | Converts an array-based pipeline to a lazy, generator-based one. |

