[![Build Status](https://travis-ci.org/sanmai/pipeline.svg?branch=master)](https://travis-ci.org/sanmai/pipeline)
[![Coverage Status](https://coveralls.io/repos/github/sanmai/pipeline/badge.svg?branch=master)](https://coveralls.io/github/sanmai/pipeline?branch=master)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/7484f26ddbfd42ebb6f0eac92e68d04d)](https://www.codacy.com/app/sanmai/pipeline?utm_source=github.com&utm_medium=referral&utm_content=sanmai/pipeline&utm_campaign=badger)
[![Maintainability](https://api.codeclimate.com/v1/badges/a1291887920116526e2a/maintainability)](https://codeclimate.com/github/sanmai/pipeline/maintainability)
[![Latest Stable Version](https://poser.pugx.org/sanmai/pipeline/v/stable)](https://packagist.org/packages/sanmai/pipeline)
[![License](https://poser.pugx.org/sanmai/pipeline/license)](https://packagist.org/packages/sanmai/pipeline)

Imagine you have a very deep and complex processing chain. Something akin to this obviously contrived example:

	foreach ($obj->generator() as $val) {
	    if ($val->a || $val->foo() == 3) {
	        foreach ($val->bar as $b) {
	            if ($b->keys) {
	                foreach ($b->keys as $key) {
	                    if ($key->name == "foo") {
	                        foreach ($b->assoc[$key->id] as $foo) {
	                            // ...
	                        }
                            foreach ($b->uassoc[$key->id] as $foo) {
                                // ...
                            }
	                    }
	                }
	            }
	        }
	    }
	}

Now, you naturally want to break this monster down into manageable parts. You think you could do it like this:

	$step1 = [];
	foreach ($obj->generator() as $val) {
	    if ($val->a || $val->foo() == 3) {
	        $step1[] = $val->bar;
	    }
	}

	$step2 = [];
	foreach ($step1 as $b) {
	    if ($b->keys) {
	        $step2[] = $b->keys;
	    }
	}

	$step3 = [];
	foreach ($step2 as $key) {
	    if ($key->name == "foo") {
	        $step3[] = $b->assoc[$key->id];
	        $step3[] = $b->uassoc[$key->id];
	    }
	}

	$step4 = [];
	foreach ($step3 as $foo) {
	    // ...
	}

Indeed you made it somewhat simpler to understand, but this is still far from perfect. Three things come to mind:

1. You lost type information here and there, so no autocomplete suggestions for you.
2. On every step, every result has to buffer. This not only takes memory space, but you would not see if your algorithm is failing on the last step until you passed all the previous steps. What a bummer!
3. These separate cycles are nice, but you still can not test them one by one. That's practically impossible without further work.

One may think he can pull the trick with `array_map`. But there's a catch: you can't easily return more than one value from `array_map`. No luck here too.

So, how do you solve this problem? Pipeline to the rescue!

# Pipeline

With the pipeline, you could split just about any processing chain into a manageable sequence of testable generators or mapping functions. Want to know average shipping delay for these three warehouses for orders made during previous sale? Map matching orders into shipments, exclude unwanted warehouses, map shipments into dates and timings, sum and divide. Done!

Take a single step and write a generator or a function for it:

    $this->double = function ($value) {
        return $value * 2;
    };

	$this->rowTotal = function (SomeType $value) {
	    yield $value->price * $value->quantity;
	};

With type checks and magic of autocomplete! Apply it to the data:

    $sourceData = new \ArrayIterator(range(1, 1000)); // can be any type of generator

    $pipeline = new \Pipeline\Simple($sourceData);
    $pipeline->map($this->double);
    // any number of times in any sequence
    $pipeline->map($this->double);

Get results for the first rows immediately.

    foreach ($pipeline as $result) {
        echo "$result,";
    }
    // immediately starts printing 4,8,12,...

Test with ease:

    $this->plusone = function ($value) {
        yield $value;
        yield $value + 1;
    };

    $this->assertSame([4, 5], iterator_to_array(call_user_func($this->plusone, 4)));

Pretty neat, eh?

Heck, you can even pass on an instance of [League\Pipeline](https://github.com/thephpleague/pipeline) to batch-process a collection of values, not just a single value it can usually handle:

	$leaguePipeline = (new \League\Pipeline\Pipeline())->pipe(function ($payload) {
	    return $payload + 1;
	})->pipe(function ($payload) {
	    return $payload * 2;
	});
	
	$pipeline = new \Pipeline\Simple(new \ArrayIterator([10, 20, 30]));
	$pipeline->map($leaguePipeline);
	
	foreach ($pipeline as $result) {
	    echo "$result,";
	}
	// prints 22,42,62,

# Install

    composer require sanmai/pipeline

# Known caveats

- Since all callback are lazily evaluated as more data coming in and out, make sure you consume the results.

        foreach ($pipeline as $result) {
            // Processing happens only if you consume the results.
            // Want to stop early after few results? Not a problem here!
        }

  Nothing will happen unless you use the results. That's the point of having lazy evaluation.

- Keys for yielded values are not being kept. This may change in the future, but that is for now.

- The resulting pipeline is an iterator and by default is not rewindable.

# Classes and interfaces

- `\Pipeline\Simple` is the main user-facing class for the pipeline with sane defaults for most methods.
- `\Pipeline\Principal` is an abstract class you may want to extend if you're not satisfied with defaults from the class above. E.g. `getIterator()` can have different error handling.
- Interface `Pipeline` defines three main functions all pipelines must bear.

# Methods

## `__construct()`

Takes an insance of `Traversable` or none. In the latter case the pipeline must be primed by passing an initial generator to the `map` method.

## `map()`

Takes a processing stage in a form of a generator function or a plain mapping function. Can also take an initial generator, where it must not require any arguments.

    $pipeline->map(function (Customer $customer) {
        foreach ($customer->allPayments() as $item) {
            yield $item;
        }
    });
    
Can also take an initial generator, where it must not require any arguments.

    $pipeline = new \Pipeline\Simple();
    $pipeline->map(function () {
        yield $this->foo;
        yield $this->bar;
    });

## `filter()`

Takes a filter callback not unlike that of `array_filter`.

    $pipeline->filter(function ($item) {
        return $item->isGood() && $item->amount > 0;
    });
    
Simple pipeline has a default callback with the same effect as in `array_filter`: it'll remove all falsy values.

## `reduce()`

Takes a reducing callback not unlike that of `array_reduce` with two arguments for the value of the previous iteration and for the current item. 
As a second argument it can take an inital value.

    $total = $pipeline->reduce(function ($curry, $item) {
        return $curry + $item->amount;
    }, 0);

Simple pipeline has a default callback that sums all values.

## `getIterator()`

A method to conform to the `Traversable` interface. In case of unprimed `\Pipeline\Simple` it'll return an empty array iterator, essentially a no-op pipeline. Therefore this should work without errors:

    $pipeline = new \Pipeline\Simple();
    foreach ($pipeline as $value) {
        // no errors here
    }

This allows to skip type checks for return values if one has no results to return: instead of `false` or `null` it is safe to return an unprimed pipeline.

## `__invoke()`

Returns a generator with all values currently in the pipeline. Allows to connect pipelines freely.

	$foo = new Simple();
	$foo->map(function () {
	    yield 1;
	    yield 2;
	});
	
	$bar = new Simple();
	$bar->map($foo);
	$this->assertEquals(3, $bar->reduce());
	var_dump($bar->reduce());
	// int(3)

# Showcase

    $pipeline = new \Pipeline\Simple();

    // initial generator
    $pipeline->map(function () {
        foreach (range(1, 3) as $i) {
            yield $i;
        }
    });

    // next processing step
    $pipeline->map(function ($i) {
        yield pow($i, 2);
        yield pow($i, 3);
    });

    // simple one-to-one mapper
    $pipeline->map(function ($i) {
        return $i - 1;
    });

    // one-to-many generator
    $pipeline->map(function ($i) {
        yield $i * 2;
        yield $i * 4;
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

    // default reducer from the simple pipeline, for the sake of convenience    
    $value = $pipeline->reduce(function ($a, $b) {
        return $a + $b;
    }, 0);

    var_dump($value);
    // int(104)

# Contributions

Contributions to documentation and test cases are welcome.

API is expected to stay as simple as it is. Do not expect a PR with utility functions to get accepted.

# About collection pipelines in general

What about alternatives? How are they different?

- [League\Pipeline](https://github.com/thephpleague/pipeline) is good for single values only. Similar name, but very different purpose. Not supposed to work with sequences of values. Each stage may return only one value.

- [Knapsack](https://github.com/DusanKasan/Knapsack) is a close call. Can take a Traversable as an input, has lazy evaluation. But can't have multiple values produced from a single input. Has lots of utility functions for those who need them: they're out of scope for this project.

- [transducers.php](https://github.com/mtdowling/transducers.php) is worth a close look if you're already familiar transducers from Clojure. API is not very PHP-esque. Read as not super friendly. [Detailed write-up from the author.](http://mtdowling.com/blog/2014/12/04/transducers-php/)

- Submit PR to add yours.

[More about pipelines in general](https://martinfowler.com/articles/collection-pipeline/) from Martin Fowler.


# TODO

- [ ] Memory benchmarks?
