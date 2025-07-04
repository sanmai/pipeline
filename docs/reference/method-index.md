# Method Index

This index provides a quick reference to all methods and helper functions in the Pipeline library.

## Creation

| Method | Description |
| --- | --- |
| `new Standard()` | Creates a new pipeline instance. |
| `take()` | Creates a pipeline from one or more iterables. |
| `fromArray()` | Creates a pipeline from an array. |
| `fromValues()` | Creates a pipeline from individual values. |
| `map()` | Creates a pipeline from a generator function. |
| `zip()` | Combines multiple iterables into a pipeline of tuples. |

## Transformation

| Method | Description |
| --- | --- |
| `map()` | Transforms elements, with support for generators. |
| `cast()` | Transforms elements on a 1-to-1 basis. |
| `flatten()` | Flattens a nested pipeline by one level. |
| `unpack()` | Unpacks array elements as arguments to a callback. |
| `chunk()` | Splits the pipeline into chunks of a specified size. |
| `slice()` | Extracts a portion of the pipeline. |

## Filtering

| Method | Description |
| --- | --- |
| `filter()` | Filters elements based on a condition. |
| `skipWhile()` | Skips elements from the beginning of the pipeline. |

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
| `getIterator()` | Returns an iterator for the pipeline. |
| `each()` | Iterates over the pipeline for side effects. |

## Statistics

| Method | Description |
| --- | --- |
| `finalVariance()` | Calculates a full set of statistics. |
| `runningVariance()` | Calculates statistics without consuming the pipeline. |

## Utility

| Method | Description |
| --- | --- |
| `reservoir()` | Selects a random subset of elements. |
| `zip()` | Combines multiple iterables into a pipeline of tuples. |
| `runningCount()` | Counts elements without consuming the pipeline. |
| `stream()` | Converts an array-based pipeline to a stream. |

