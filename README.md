[![Total Downloads](https://poser.pugx.org/sanmai/pipeline/downloads)](https://packagist.org/packages/sanmai/pipeline)
[![Latest Stable Version](https://poser.pugx.org/sanmai/pipeline/v/stable)](https://packagist.org/packages/sanmai/pipeline)
![CI](https://github.com/sanmai/pipeline/workflows/CI/badge.svg)
[![Coverage Status](https://coveralls.io/repos/github/sanmai/pipeline/badge.svg?branch=main)](https://coveralls.io/github/sanmai/pipeline?branch=main)
[![Type Coverage](https://shepherd.dev/github/sanmai/pipeline/coverage.svg)](https://shepherd.dev/github/sanmai/pipeline)

Pipeline is a PHP library that brings the power of streaming pipelines to your code. Inspired by the pipe operator (`|>`), common in functional programming languages, Pipeline lets you chain together a series of operations on your data in a clear, concise, and readable way. This approach not only simplifies complex data transformations but also makes your code more maintainable and easier to test.

Pipeline makes dealing with `iterable` types as easy as it can be, making it a perfect tool for bespoke data processing pipelines, hence the name. If you ever piped together several bash commands where one command uses output of another in succession, this library does just that but for PHP functions, generators, arrays, and iterators.

Pipeline comes with the most important yet basic building blocks. It boasts methods to map, filter, reduce, zip, and unpack data from arbitrary generators and from all kinds of standard iterators.

This rigorously tested library just works. Pipeline neither defines nor throws any exceptions.

# Install

    composer require sanmai/pipeline

The latest version requires PHP 7.4 or above, including PHP 8.2 and later.

There are earlier versions that work under PHP 5.6 and above, but they are not as feature complete.

# Use

```php
use function Pipeline\take;

// iterable corresponds to arrays, generators, iterators
// we use an array here simplicity sake
$iterable = range(1, 3);

// wrap the initial iterable with a pipeline
$pipeline = take($iterable);

// join side by side with other iterables of any type
$pipeline->zip(
    \range(1, 3),
    map(function () {
        yield 1;
        yield 2;
        yield 3;
    })
);

// lazily process their elements together
$pipeline->unpack(function (int $a, int $b, int $c) {
    return $a - $b - $c;
});

// map one value into several more
$pipeline->map(function ($i) {
    yield pow($i, 2);
    yield pow($i, 3);
});

// simple one-to-one mapper
$pipeline->cast(function ($i) {
    return $i - 1;
});

// map into arrays
$pipeline->map(function ($i) {
    yield [$i, 2];
    yield [$i, 4];
});

// unpack array into arguments
$pipeline->unpack(function ($i, $j) {
    yield $i * $j;
});

// one way to filter
$pipeline->map(function ($i) {
    if ($i > 50) {
        yield $i;
    }
});

// this uses a filtering iterator from SPL under the hood
$pipeline->filter(function ($i) {
    return $i > 100;
});

// reduce to a single value; can be an array or any value
$value = $pipeline->reduce(function ($carry, $item) {
    // for the sake of convenience the default reducer from the simple
    // pipeline does summation, just like we do here
    return $carry + $item;
}, 0);

var_dump($value);
// int(104)
```

# API entry points

All entry points always return an instance of the pipeline.

|  Method     | Details                       | Use with       |
| ----------- | ----------------------------- | ----------- |
| `map()`     | Takes an optional initial callback, where it must not require any arguments. Other than that, works just like an instance method below. | `use function Pipeline\map;` |
| `take()`  | Takes any iterables, including arrays, joining them together in succession.  | `use function Pipeline\take;` |
| `fromArray()`  | Takes an array, initializes a pipeline with it.  | `use function Pipeline\fromArray;` |
| `zip()`  | Takes an iterable, and several more, transposing them together.  | `use function Pipeline\zip;` |


# Instance methods in a nutshell

|  Method     | Details                       | A.K.A.            |
| ----------- | ----------------------------- | ----------------- |
| `map()`     | Takes an optional callback that for each input value may return one or yield many. Also takes an initial generator, where it must not require any arguments. Provided no callback does nothing. Also available as a plain function. |  `SelectMany`                  |
| `cast()`    | Takes a callback that for each input value expected to return another single value. Unlike `map()`, it assumes no special treatment for generators. Provided no callback does nothing. | `array_map`, `Select`                  |
| `append()` | Appends the contents of an interable to the end of the pipeline. | `array_merge` |
| `push()` | Appends the arguments to the end of the pipeline. | `array_push` |
| `prepend()` | Appends the contents of an interable to the end of the pipeline. | `array_merge` |
| `unshift()` | Prepends the pipeline with a list of values. | `array_unshift` |
| `zip()`  | Takes a number of iterables, transposing them together with the current sequence, if any.  | `array_map(null, ...$array)`, Python's `zip()`, transposition |
| `reservoir()` | Reservoir sampling with an optional weighting function. |  |
| `flatten()` |  Flattens inputs: `[[1, 2], [3, 4]]` becomes `[1, 2, 3, 4]`. |  `flat_map`, `flatten`, `collect_concat`      |
| `unpack()`  | Unpacks arrays into arguments for a callback. Flattens inputs if no callback provided. |             |
| `chunk()` | Chunks the pipeline into arrays of specified length. | `array_chunk` |
| `filter()`  | Removes elements unless a callback returns true. Removes falsey values if no callback provided.  |  `array_filter`, `Where`                |
| `skipWhile()` | Skips elements while the predicate returns true, and keeps everything after the predicate return false just once. |  | 
| `slice()`  | Extracts a slice from the inputs. Keys are not discarded intentionally. Suppors negative values for both arguments. |  `array_slice`                |
| `fold()`  | Reduces input values to a single value. Defaults to summation. Requires an initial value. | `array_reduce`, `Aggregate`, `Sum` |
| `reduce()`  | Alias to `fold()` with a reversed order of arguments. | `array_reduce` |
| `flip()`    | Swaps keys and values. | `array_flip` |
| `tuples()`    | Converts stream to [key, value] tuples. | |
| `max()`     | Finds the highest value. | `max` |
| `min()`     | Finds the lowest value. | `min` |
| `count()`     | Counts values. Eagerly executed.| `array_count` |
| `each()`     | Eagerly iterates over the sequence. | `foreach`, `array_walk` |
| `runningCount()` | Counts seen values using a reference argument. | |
| `toArray()` | Returns an array with all values. Eagerly executed. | `dict`, `ToDictionary` |
| `toArrayPreservingKeys()` | Returns an array with all values and keys. Eagerly executed. |  |
| `runningVariance()` | Computes online statistics: sample mean, sample variance, standard deviation. | [Welford's method](https://en.wikipedia.org/wiki/Algorithms_for_calculating_variance#Welford's_online_algorithm) |
| `finalVariance()` | Computes final statistics for the sequence. |   |
| `__construct()` | Can be provided with an optional initial iterator. Used in the `take()` function from above.  |     |

Pipeline is an iterator and can be used as any other iterable. 

Pipeline can be used as an argument to `count()`. Implements `Countable`. Be warned that operation of counting values is [a terminal operation](https://docs.oracle.com/javase/8/docs/api/java/util/stream/package-summary.html#StreamOps).

In general, Pipeline instances are mutable, meaning every Pipeline-returning method returns the very same Pipeline instance. This gives us great flexibility on trusting someone or something to add processing stages to a Pipeline instance, while also avoiding non-obivius mistakes, raised from a need to strictly follow a fluid interface. E.g. if you add a processing stage, it stays there no matter if you capture the return value or not. This peculiarity could have been a thread-safety hazard in other circumstances, but under PHP this is not an issue.

# Caveats

- Since most callback are [lazily evaluated](https://en.wikipedia.org/wiki/Lazy_evaluation) as more data coming in and out, you must consume the results with a plain `foreach` or use a `reduce()` to make sure processing happens.

    ```php
    foreach ($pipeline as $result) {
        // Processing happens only if you consume the results.
        // Want to stop early after few results? Not a problem here!
    }
    ```

  Almost nothing will happen unless you use the results. That's the point of lazy evaluation after all!
  
- That said, if a non-generator used to seed the pipeline, it will be executed eagerly.

    ```php
    $pipeline = new \Pipeline\Standard();
    $pipeline->map(function () {
        // will be executed immediately on the spot, unless yield is used
        return $this->veryExpensiveMethod();
    })->filter();
    ```
  In the above case the pipeline will store an array internally, with which the pipeline will operate eagerly whenever possible. Ergo, *when in doubt, use a generator.*
  
    ```php
    $pipeline->map(function () {
        // will be executed only as needed, when needed
        yield $this->veryExpensiveMethod();
    })->filter();
    ```  

- Keys for yielded values are being kept as is on a best effort basis, so one must take care when using `iterator_to_array()` on a pipeline: values with duplicate keys will be discarded with only the last value for a given key being returned.
    
    ```php
	$pipeline = \Pipeline\map(function () {
	    yield 'foo' => 'bar';
	    yield 'foo' => 'baz';
	});
	
	var_dump(iterator_to_array($pipeline));
	/* ['foo' => 'baz'] */
    ```
  
  Safer would be to use provided `toArray()` method. It will return all values regardless of keys used, making sure to discard all keys in the process.
  
    ```php
    var_dump($pipeline->toArray());
    /* ['bar', 'baz'] */
    ```
  This method also takes an optional argument to keep the keys.

- The resulting pipeline is an iterator and should be assumed not rewindable, just like generators it uses.

	```php
	$pipeline = \Pipeline\map(function () {
	    yield 1;
	});
	
	$sum = $pipeline->reduce();
	
	// Won't work the second time though
	$pipeline->reduce();
	// Exception: Cannot traverse an already closed generator
	```
 
  Although there are some cases where a pipeline can be rewinded and reused just like a regular array, a user should make no assumptions about this behavior as it is not a part of the API compatibility guarantees.   
 
- Pipeline implements `IteratorAggregate` which is not the same as `Iterator`. Where the latter needed, the pipeline can be wrapped with an `IteratorIterator`:

    ```php
    $iterator = new \IteratorIterator($pipeline);
    /** @var $iterator \Iterator */
    ```

- Iterating over a pipeline all over again results in undefined behavior. Best to avoid doing this.

# Classes and interfaces: overview

- `\Pipeline\Standard` is the main user-facing class for the pipeline with sane defaults for most methods.

This library is built to last. There's not a single place where an exception is thrown. Never mind any asserts whatsoever.

# Methods

## `__construct()`

Takes an instance of `Traversable` or none. In the latter case the pipeline must be primed by passing an initial generator to the `map` method. 

## `$pipeline->map()`

Takes a processing stage in a form of a generator function or a plain mapping function. Provided no callback does nothing.

```php
$pipeline->map(function (Customer $customer) {
    foreach ($customer->allPayments() as $item) {
        yield $item;
    }
});

// Now process all and every payment
$pipeline->map(function (Payment $payment) {
    return $payment->amount;
});
```

Can also take an initial generator, where it must not require any arguments.

```php
$pipeline = new \Pipeline\Standard();
$pipeline->map(function () {
    yield $this->foo;
    yield $this->bar;
});
```

## `$pipeline->flatten()`

Flatten inputs:

```php
$pipeline->map(function () {
    yield [1];
    yield [2, 3];
})->unpack()->toArray();
// [1, 2, 3]
```

## `$pipeline->unpack()`

An extra variant of `map` which unpacks arrays into arguments for a callback.

Where with `map()` you would use:

```php
$pipeline->map(function ($args) {
    list ($a, $b) = $args;

    // and so on
});
```

With `unpack()` these things are done behind the scene for you:

```php
$pipeline->map(function () {
    yield [-1, [10, 20], new DateTime()];
});
$pipeline->unpack(function ($a, array $b, \DateTime ...$dates) {
    // and so on
});
```

You can have all kinds of standard type checks with ease too.

With no callback, the default callback for `unpack()` will flatten inputs as in `flatten()`.

## `$pipeline->cast()`

Works similarly to map, but does not have a special treatment for generators. Think of `array_map`.

```php
$pipeline->cast(function (Customer $customer) {
    foreach ($customer->allPayments() as $item) {
        yield $item;
    }
});

$pipeline->map(function (\Generator $paymentGenerator) {
    // Keeps grouping as per customer
});
```

For this example, where `map()` would have filled the pipeline with a series of payments, `cast()` will add a generator for each customer.

## `$pipeline->zip()`

Sequence-joins several iterables together, forming a feed with elements side by side:

```php
$pipeline = take($iterableA);
$pipeline->zip($iterableB, $iterableC);
$pipeline->unpack(function ($elementOfA, $elementOfB, $elementOfC) {
    // ... 
});
```

With iterators with unequal number of elements, missing elements are left as nulls.

## `$pipeline->filter()`

Takes a filter callback not unlike that of `array_filter`.

```php
$pipeline->filter(function ($item) {
    return $item->isGood() && $item->amount > 0;
});
```

The pipeline has a default callback with the same effect as in `array_filter`: it'll remove all falsy values.

## `$pipeline->slice()`

Takes offset and length arguments, functioning in a very similar fashion to how `array_slice` does with `$preserve_keys` set to true.

```php
$pipeline->slice(1, -1);
```
This example will remove first and last elements of the sequence.

Implementation uses a rolling window buffer for negative values of offset and length, and falls back on plain old `array_slice` for input arrays.

## `$pipeline->reduce()`

Takes a reducing callback not unlike that of `array_reduce` with two arguments for the value of the previous iteration and for the current item.
As a second argument it can take an inital value.

```php
$total = $pipeline->reduce(function ($curry, $item) {
    return $curry + $item->amount;
}, 0);
```

The pipeline has a default callback that sums all values.

## `$pipeline->toArray()`

Returns an array with all values from a pipeline. All array keys are ignored to make sure every single value is returned.

```php
// Yields [0 => 1, 1 => 2]
$pipeline = map(function () {
    yield 1;
    yield 2;
});

// For each value yields [0 => $i + 1, 1 => $i + 2]
$pipeline->map(function ($i) {
    yield $i + 1;
    yield $i + 2;
});

$result = $pipeline->toArray();
// Since keys are ignored we get:
// [2, 3, 3, 4]
```

If in the example about one would use `iterator_to_array($result)` they would get just `[3, 4]`.

## `$pipeline->each()`

Eagerly iterates over the sequence using the provided callback.

```php
$pipeline->each(function ($i) {
    $this->log("Saw $i");
});
```

Discards the sequence after iteration unless instructed otherwise by the second argument.

## `$pipeline->getIterator()`

A method to conform to the `Traversable` interface. In case of unprimed `\Pipeline\Standard` it'll return an empty array iterator, essentially a no-op pipeline. Therefore this should work without errors:

```php
$pipeline = new \Pipeline\Standard();
foreach ($pipeline as $value) {
    // no errors here
}
```

This allows to skip type checks for return values if one has no results to return: instead of `false` or `null` it is safe to return an unprimed pipeline.

## `$pipeline->runningVariance()`

Computes online statistics for the sequence: counts, sample  mean, sample variance, standard deviation. You can access these numbers on the fly with methods such as `getCount()`, `getMean()`, `getVariance()`, `getStandardDeviation()`.

This method also accepts an optional cast callback that should return `float|null`: `null` values are discarded. Therefore you can have several running variances computing numbers for different parts of the data.

```php
$pipeline->runningVariance($varianceForShippedOrders, static function (order $order): ?float {
    if (!$order->isShipped()) {
        // This order will be excluded from the computation.
        return null;
    }

    return $order->getTotal();
});

$pipeline->runningVariance($varianceForPaidOrders, static function (order $order): ?float {
    if ($order->isUnpaid()) {
        // This order will be excluded from the computation.
        return null;
    }

    return $order->getProjectedTotal();
});
```

As you process orders, you will be able to access `$varianceForShippedOrders->getMean()` and `$varianceForPaidOrders->getMean()`.

This computation uses [Welford's online algorithm](https://en.wikipedia.org/wiki/Algorithms_for_calculating_variance#Welford's_online_algorithm), therefore it can handle very large numbers of data points.

## `$pipeline->finalVariance()`

A convenience method to computes the final statistics for the sequence. Accepts an optional cast method, else assumes the sequence countains valid numbers.

```php
// Fibonacci numbers generator
$fibonacci = map(function () {
    yield 0;

    $prev = 0;
    $current = 1;

    while (true) {
        yield $current;
        $next = $prev + $current;
        $prev = $current;
        $current = $next;
    }
});

// Statistics for the second hundred Fibonacci numbers.
$variance = $fibonacci->slice(101, 100)->finalVariance();

$variance->getStandardDeviation();
// float(3.5101061922557E+40)

$variance->getCount();
// int(100)
```

# Contributions

Contributions to documentation and test cases are welcome. Bug reports are welcome too. 

API is expected to stay as simple as it is, though.

# About collection pipelines in general

About [collection pipelines programming pattern](https://martinfowler.com/articles/collection-pipeline/) by Martin Fowler. 

In a more general sense this library implements a subset of [CSP](https://en.wikipedia.org/wiki/Communicating_sequential_processes) paradigm, as opposed to [Actor model](https://en.wikipedia.org/wiki/Actor_model).

What else is out there:

- [Pipe operator from Hack](https://docs.hhvm.com/hack/operators/pipe-operator) is about same, only won't work for generators, and not under the regular PHP. [See a proposal for a similar operator for JavaScript.](https://github.com/tc39/proposal-pipeline-operator)
- [`nikic/iter`](https://github.com/nikic/iter) provides functions like array_map and such, but returning lazy generators. You'll need quite some glue to accomplish the same thing Pipeline does out of box, not to mention some missing features.
- [League\Pipeline](https://github.com/thephpleague/pipeline) is good for single values only. Similar name, but very different purpose. Not supposed to work with sequences of values. Each stage may return only one value.
- [Illuminate\Support\Collection](https://laravel.com/docs/master/collections) a fluent wrapper for working with arrays of data. Can only work with arrays, also immutable, which is kind of expected for an array-only wrapper.
- [Knapsack](https://github.com/DusanKasan/Knapsack) is a close call. Can take a Traversable as an input, has lazy evaluation. But can't have multiple values produced from a single input. Has lots of utility functions for those who need them: they're out of scope for this project.
- [transducers.php](https://github.com/mtdowling/transducers.php) is worth a close look if you're already familiar transducers from Clojure. API is not very PHP-esque. Read as not super friendly. [Detailed write-up from the author.](http://mtdowling.com/blog/2014/12/04/transducers-php/)
- [Primitives for functional programming in PHP](https://github.com/lstrojny/functional-php) by Lars Strojny et al. is supposed to complement currently exisiting PHP functions, which it does, although it is subject to some of the same shortcomings as are `array_map` and `array_filter`. No method chaining.
- [Chain](https://github.com/cocur/chain) provides a consistent and chainable way to work with arrays in PHP, although for arrays only. No lazy evaluation. 
- [Simple pipes with PHP generators](https://www.hughgrigg.com/posts/simple-pipes-php-generators/) by Hugh Grigg. Rationale and explanation for an exceptionally close concept. Probably one can use this library as a drop-in replacement, short of different method names.
- [loophp's Collection](https://github.com/loophp/collection) looks like a viable alternative to this library, as far as processing of multi-gigabyte log files goes. [Supports fluent interface.](https://loophp-collection.readthedocs.io/en/stable/pages/usage.html) It takes the immutability as a first principle, even though PHP's generators are inherently mutable.
- If you're familiar with Java, [package java.util.stream](https://docs.oracle.com/javase/8/docs/api/java/util/stream/package-summary.html) offers an implementation of the same concept.
- [Collection types](https://docs.scala-lang.org/overviews/collections-2.13/performance-characteristics.html#) in Scala.

Submit a PR to add yours.

# More badges

[![License](https://poser.pugx.org/sanmai/pipeline/license)](https://packagist.org/packages/sanmai/pipeline)
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2Fsanmai%2Fpipeline.svg?type=shield)](https://app.fossa.io/projects/git%2Bgithub.com%2Fsanmai%2Fpipeline?ref=badge_shield)
