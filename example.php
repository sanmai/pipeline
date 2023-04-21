<?php
/**
 * Copyright 2017, 2018 Alexey Kopytko <alexey@kopytko.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

include 'vendor/autoload.php';

use function Pipeline\map;
use function Pipeline\take;

// iterable corresponds to arrays, generators, iterators
// we use an array here simplicity sake
$iterable = range(5, 7);

// wrap the initial iterable with a pipeline
$pipeline = take($iterable);

// join side by side with other iterables of any type
$pipeline->zip(
    range(1, 3),
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
    yield $i ** 2;
    yield $i ** 3;
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

// Collect online statistics
$pipeline->runningVariance($variance);

// one way to filter
$pipeline->map(function ($i) {
    if ($i > 50) {
        yield $i;
    }
});

// this uses a filtering iterator from SPL under the hood
$pipeline->filter(function ($value) {
    return $value > 100;
});

// reduce to a single value; can be an array or any value
$value = $pipeline->reduce(function ($carry, $valuetem) {
    // for the sake of convenience the default reducer from the simple pipeline does summation, just like we do here
    return $carry + $valuetem;
}, 0);

var_dump($value);
// int(104)

var_dump($variance->getCount());
// int(12)
var_dump($variance->getMean());
// float(22)
var_dump($variance->getStandardDeviation());
// float(30.3794188704967)

$sum = take([1, 2, 3])->reduce();
var_dump($sum);
// int(6)

// now with League\Pipeline
$leaguePipeline = (new \League\Pipeline\Pipeline())->pipe(function ($payload) {
    return $payload + 1;
})->pipe(function ($payload) {
    return $payload * 2;
});

$pipeline = new \Pipeline\Standard(new \ArrayIterator([10, 20, 30]));
$pipeline->map($leaguePipeline);

$result = iterator_to_array($pipeline);
var_dump($result);
// array(3) {
//   [0] =>
//     int(22)
//   [1] =>
//     int(42)
//   [2] =>
//     int(62)
// }

// Now an example for toArray()
// Yields [0 => 1, 1 => 2]
$pipeline = map(function () {
    yield 1;
    yield 2;
});

// For each value yields [0 => $value + 1, 1 => $value + 2]
$pipeline->map(function ($value) {
    yield $value + 1;
    yield $value + 2;
});

// remove first and last elements
$pipeline->slice(1, -1);

$arrayResult = $pipeline->toArray();
var_dump($arrayResult);
// Since keys are discarded we get:
// array(4) {
//     [0] =>
//     int(3)
//     [1] =>
//     int(3)
// }

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

// Statistics for the second hundred Fibonacci numbers
$variance = $fibonacci->slice(101, 100)->finalVariance();

var_dump($variance->getStandardDeviation());
// float(3.5101061922557E+40)

var_dump($variance->getCount());
// int(100)
