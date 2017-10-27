[![Build Status](https://travis-ci.org/sanmai/pipeline.svg?branch=master)](https://travis-ci.org/sanmai/pipeline)
[![Coverage Status](https://coveralls.io/repos/github/sanmai/pipeline/badge.svg?branch=master)](https://coveralls.io/github/sanmai/pipeline?branch=master)

Imagine you have a very deep and complex processing chain. Something akin to this obviously contrived example of a pyramid of doom:

	foreach ($obj->generator() as $val) {
	    if ($val->a || $val->foo() == 3) {
	        foreach ($val->bar as $b) {
	            if ($b->keys) {
	                foreach ($b->keys as $key) {
	                    if ($key->name == "foo") {
	                        foreach ($b->assoc[$key->id] as $foo) {
	                            // ...
	                        }
	                    }
	                }
	            }
	        }
	    }
	}

Now, you naturally want to break this monster down into manageable steps. You think you could do it like this:

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
	    }
	}
	
	$step4 = [];
	foreach ($step3 as $foo) {
	    // ...
	}

Indeed you made it somewhat simpler to understand, but this is still far from perfect. Three things come to mind:

1. You lost type information here and there, so no autocomplete suggestions for you.
2. On every step, every result has to buffer. This not only takes memory space, but you would not see if your algorithm is failing on the last step until you passed all the previous steps. What a bummer!
2. These separate cycles are nice, but you still can not test them one by one. That's practically impossible.

One may think he can pull the trick with `array_map`. But there's a catch: you can't easily return more than one value from `array_map`. No luck here too.

So, how do you solve this problem? Look no more! Pipeline to the rescue. 

# Pipeline

With the pipeline, you could split just about any processing chain into a manageable sequence of testable generators.

Take a single step and write a generator for it:

    $this->double = function ($value) {
        yield $value * 2;
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
    // immediately stats printing 4,8,12,...

Test with ease:

    $this->assertSame([2], iterator_to_array(call_user_func($example->double, 1)));

Pretty neat, eh?

## Another example

    $pipeline = new \Pipeline\Simple();
    
    // initial generator
    $pipeline->map(function () {
        foreach (range(1, 3) as $i) {
            yield $i;
        }
    });
    
    // next processing test
    $pipeline->map(function ($i) {
        yield pow($i, 2);
        yield pow($i, 3);
    });
    
    $pipeline->map(function ($i) {
        yield $i - 1;
    });
    
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
    
    $pipeline->filter(function ($i) {
        return $i > 100;
    });

    // for the sake of convenience    
    $value = $pipeline->reduce(function ($a, $b) {
        return $a + $b;
    }, 0);
    
    var_dump($value);
    // int(104)

# Install

    composer require sanmai/pipeline

# Common pitfalls

Check that you passing an actual generator.

    $pipeline->map(function ($i) {
        // this won't work - not a generator!
        return $i * 2;
    });

Make sure you consume the results.

    foreach ($pipeline as $result) {
        // processing happens only if you consume the results
    }


# TODO

- [ ] Document all the things
- [ ] Scrutinize and format
- [ ] Memory benchmarks?

# Methods

## map

## filter

## reduce

# General purpose collection pipelines

- https://github.com/DusanKasan/Knapsack
- https://github.com/mtdowling/transducers.php ([Detailed writeup.](http://mtdowling.com/blog/2014/12/04/transducers-php/))
- Submit PR

[More about pipelines in general.](https://martinfowler.com/articles/collection-pipeline/)