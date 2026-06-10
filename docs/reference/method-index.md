# Method Index

A quick reference to every public method and helper function in the Pipeline library, grouped by purpose. Terminal operations consume the pipeline; all other methods return the same pipeline instance for chaining.

## Creation

| Method | Description | Reference |
| --- | --- | --- |
| `new Standard()` | Creates a new pipeline from an optional iterable. | [Creation](../api/creation.md#new-standard) |
| `take()` | Creates a pipeline from one or more iterables. | [Creation](../api/creation.md#take) |
| `fromArray()` | Creates a pipeline from an array. | [Creation](../api/creation.md#fromarray) |
| `fromValues()` | Creates a pipeline from individually listed values. | [Creation](../api/creation.md#fromvalues) |
| `map()` (function) | Creates a pipeline from a generator function or value. | [Creation](../api/creation.md#map) |
| `zip()` (function) | Combines iterables into a pipeline of tuples. | [Creation](../api/creation.md#zip) |
| `append()` | Appends an iterable to the end of the pipeline. | [Creation](../api/creation.md#append) |
| `push()` | Appends individually listed values. | [Creation](../api/creation.md#push) |
| `prepend()` | Prepends an iterable to the beginning. | [Creation](../api/creation.md#prepend) |
| `unshift()` | Prepends individually listed values. | [Creation](../api/creation.md#unshift) |

## Transformation

| Method | Description | Reference |
| --- | --- | --- |
| `map()` | Transforms each element; `yield` produces any number of elements. | [Transformation](../api/transformation.md#map) |
| `cast()` | Transforms each element strictly one-to-one. | [Transformation](../api/transformation.md#cast) |
| `flatten()` | Flattens nested iterables by one level. | [Transformation](../api/transformation.md#flatten) |
| `unpack()` | Spreads array elements into callback arguments. | [Transformation](../api/transformation.md#unpack) |
| `chunk()` | Splits the pipeline into fixed-size arrays. | [Transformation](../api/transformation.md#chunk) |
| `chunkBy()` | Splits the pipeline into chunks of varying sizes. | [Transformation](../api/transformation.md#chunkby) |
| `slice()` | Extracts a portion; supports negative offset and length. | [Transformation](../api/transformation.md#slice) |

## Filtering

| Method | Description | Reference |
| --- | --- | --- |
| `select()` | Keeps approved elements; by default drops only `null` and `false`. | [Filtering](../api/filtering.md#select) |
| `filter()` | Alias of `select()`; by default drops all falsy values. | [Filtering](../api/filtering.md#filter) |
| `skipWhile()` | Skips leading elements while a predicate holds. | [Filtering](../api/filtering.md#skipwhile) |

## Aggregation (Terminal)

| Method | Description | Reference |
| --- | --- | --- |
| `fold()` | Reduces to a single value from a required initial value. | [Aggregation](../api/aggregation.md#fold) |
| `reduce()` | Alias of `fold()` with reversed, optional arguments. | [Aggregation](../api/aggregation.md#reduce) |
| `count()` | Counts the elements. | [Aggregation](../api/aggregation.md#count) |
| `min()` | Finds the lowest value. | [Aggregation](../api/aggregation.md#min) |
| `max()` | Finds the highest value. | [Aggregation](../api/aggregation.md#max) |
| `first()` | Returns the first element without consuming the rest. | [Aggregation](../api/aggregation.md#first) |
| `last()` | Returns the last element. | [Aggregation](../api/aggregation.md#last) |

## Collection

| Method | Description | Reference |
| --- | --- | --- |
| `toList()` | Returns all values as a list, discarding keys. Terminal. | [Collection](../api/collection.md#tolist) |
| `toAssoc()` | Returns all values with keys preserved. Terminal. | [Collection](../api/collection.md#toassoc) |
| `getIterator()` | Makes the pipeline `foreach`-able (`IteratorAggregate`). | [Collection](../api/collection.md#getiterator) |
| `each()` | Eagerly iterates for side effects. Terminal. | [Collection](../api/collection.md#each) |
| `cursor()` | Returns a forward-only iterator that survives loop breaks. | [Collection](../api/collection.md#cursor) |
| `peek()` | Removes and returns the first N elements as a new pipeline. | [Collection](../api/collection.md#peek) |

## Utility

| Method | Description | Reference |
| --- | --- | --- |
| `tap()` | Performs side effects without changing values. | [Utility](../api/utility.md#tap) |
| `stream()` | Forces lazy, element-by-element processing. | [Utility](../api/utility.md#stream) |
| `runningCount()` | Counts elements as they flow through. | [Utility](../api/utility.md#runningcount) |
| `reservoir()` | Samples random elements. Terminal. | [Utility](../api/utility.md#reservoir) |
| `zip()` | Transposes the pipeline with other iterables. | [Utility](../api/utility.md#zip) |
| `values()` | Keeps only the values, discarding keys. | [Utility](../api/utility.md#values) |
| `keys()` | Keeps only the keys as the new values. | [Utility](../api/utility.md#keys) |
| `flip()` | Swaps keys and values. | [Utility](../api/utility.md#flip) |
| `tuples()` | Converts the stream to `[key, value]` pairs. | [Utility](../api/utility.md#tuples) |

## Statistics

| Method | Description | Reference |
| --- | --- | --- |
| `runningVariance()` | Observes statistics as elements flow through. | [Statistics](../api/statistics.md#runningvariance) |
| `finalVariance()` | Computes final statistics. Terminal. | [Statistics](../api/statistics.md#finalvariance) |
