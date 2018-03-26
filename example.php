<?php
/*
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
    yield [$i, 2];
    yield [$i, 4];
});

// mapper with arguments unpacked from an input array
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
    // for the sake of convenience the default reducer from the simple pipeline does summation, just like we do here
    return $carry + $item;
}, 0);

var_dump($value);
// int(104)

// now with League\Pipeline
$leaguePipeline = (new \League\Pipeline\Pipeline())->pipe(function ($payload) {
    return $payload + 1;
})->pipe(function ($payload) {
    return $payload * 2;
});

$pipeline = new \Pipeline\Simple(new \ArrayIterator([10, 20, 30]));
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
$pipeline = new \Pipeline\Simple();

// Yields [0 => 1, 1 => 2]
$pipeline->map(function () {
    yield 1;
    yield 2;
});

// For each value yields [0 => $i + 1, 1 => $i + 2]
$pipeline->map(function ($i) {
    yield $i + 1;
    yield $i + 2;
});

$arrayResult = $pipeline->toArray();
var_dump($arrayResult);
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
