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

include 'vendor/autoload.php';


use function Pipeline\take;

class Foo
{
    public function __construct(
        public int $n,
    ) {}

    public function bar(): string
    {
        return "{$this->n}\n";
    }
}


$foos = take(['a' => 1, 'b' => 2, 'c' => 3])
    ->map(fn(int $n): int => $n * 2)
    ->cast(fn(int $n): Foo => new Foo($n));

foreach ($foos as $value) {
    echo $value->bar();
}

$pipeline = take(['a' => 1, 'b' => 2, 'c' => 3]);
$pipeline->map(fn(int $n): int => $n * 2);
$pipeline->cast(fn(int $n): Foo => new Foo($n));

foreach ($pipeline as $value) {
    echo $value->bar();
}

