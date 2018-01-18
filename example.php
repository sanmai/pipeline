<?php
/*
 * Copyright 2017 Alexey Kopytko <alexey@kopytko.com>
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

// now with League\Pipeline
$leaguePipeline = (new \League\Pipeline\Pipeline())->pipe(function ($payload) {
    return $payload + 1;
})->pipe(function ($payload) {
    return $payload * 2;
});

$pipeline = new \Pipeline\Simple(new \ArrayIterator([10, 20, 30]));
$pipeline->map($leaguePipeline);

var_dump(iterator_to_array($pipeline));
// array(3) {
//   [0] =>
//     int(22)
//   [1] =>
//     int(42)
//   [2] =>
//     int(62)
// }