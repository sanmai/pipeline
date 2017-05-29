[![Build Status](https://travis-ci.org/sanmai/pipeline.svg?branch=master)](https://travis-ci.org/sanmai/pipeline)

# Pipeline

Not your general purpose collection pipeline.

## Purpose

Cycles unrolling where it isn't feasible to store intermediary result.

## Example

One may think he can unroll cycles with `array_map`. But there's a catch: you can't easily return more than one value from `array_map`.

    <?php
    include 'vendor/autoload.php';
    
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

    // for the sake of convinience    
    $value = $pipeline->reduce(function ($a, $b) {
        return $a + $b;
    }, 0);
    
    var_dump($value);
    // int(104)

# Install

    composer require sanmai/pipeline
    
Consider that API isn't yet stable.

# TODO

- Consider accepting existing generators as arguments
- Document all the things
- Scrutinize and format
- More tests
- Memory benchmarks?

# Methods

## map

## filter

## reduce

# General purpose collection pipelines

- https://github.com/DusanKasan/Knapsack
- https://github.com/mtdowling/transducers.php
- Submit PR

[More about pipelines in general.](https://martinfowler.com/articles/collection-pipeline/)
