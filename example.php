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

// wrap an initial generator with a pipeline
$pipeline = map(function () {
    foreach (\range(1, 3) as $value) {
        yield $value;
    }
});

// next processing step
$pipeline->map(function ($value) {
    yield $value ** 2;
    yield $value ** 3;
});

// simple one-to-one mapper
$pipeline->map(function ($value) {
    return $value - 1;
});

// one-to-many generator
$pipeline->map(function ($value) {
    yield [$value, 2];
    yield [$value, 4];
});

// mapper with arguments unpacked from an input array
$pipeline->unpack(function ($value, $multiplier) {
    yield $value * $multiplier;
});

// one way to filter
$pipeline->map(function ($value) {
    if ($value > 50) {
        yield $value;
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

\var_dump($value);
// int(104)

$sum = take([1, 2, 3])->reduce();
\var_dump($sum);
// int(6)

// now with League\Pipeline
$leaguePipeline = (new \League\Pipeline\Pipeline())->pipe(function ($payload) {
    return $payload + 1;
})->pipe(function ($payload) {
    return $payload * 2;
});

$pipeline = new \Pipeline\Standard(new \ArrayIterator([10, 20, 30]));
$pipeline->map($leaguePipeline);

$result = \iterator_to_array($pipeline);
\var_dump($result);
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

$arrayResult = $pipeline->toArray();
\var_dump($arrayResult);
// Since keys are discarded we get:
// array(4) {
//     [0] =>
//     int(2)
//     [1] =>
//     int(3)
//     [2] =>
//     int(3)
//     [3] =>
//     int(4)
// }
