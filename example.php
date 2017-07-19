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

$pipeline->map(function () {
    foreach (range(1, 3) as $i) {
        yield $i;
    }
});

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

$pipeline->map(function ($i) {
    if ($i > 50) {
        yield $i;
    }
});

$pipeline->filter(function ($i) {
    return $i > 100;
});

$value = $pipeline->reduce(function ($a, $b) {
    return $a + $b;
}, 0);

// int(104)
var_dump($value);
