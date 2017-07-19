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

namespace Pipeline;

use PHPUnit\Framework\TestCase;

class SimpleTest extends TestCase
{
    public function testEmpty()
    {
        $this->assertEquals([], iterator_to_array(new Simple()));
    }

    public function testSingle()
    {
        $pipeline = new Simple();

        $pipeline->map(function () {
            foreach (range(1, 3) as $i) {
                yield $i;
            }
        });

        $this->assertEquals([1, 2, 3], iterator_to_array($pipeline));
    }

    public function testDouble()
    {
        $pipeline = new Simple();

        $pipeline->map(function () {
            foreach (range(1, 3) as $i) {
                yield $i;
            }
        });

        $pipeline->map(function ($i) {
            yield $i * 10;
            yield $i * 100;
            yield $i * 1000;
        });

        $this->assertEquals([10, 100, 1000, 20, 200, 2000, 30, 300, 3000], iterator_to_array($pipeline));
    }

    public function testTriple()
    {
        $pipeline = new Simple(new \ArrayIterator(range(1, 3)));

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

        $this->assertEquals([52, 104], iterator_to_array($pipeline));
    }

    public function testFilter()
    {
        $pipeline = new Simple();

        $pipeline->map(function () {
            foreach (range(1, 100) as $i) {
                yield $i;
            }
        });

        $pipeline->filter(function ($i) {
            return $i % 7 == 0;
        });

        $pipeline->map(function ($i) {
            yield max(0, $i - 50);
        });

        $pipeline->filter();

        $this->assertEquals([6, 13, 20, 27, 34, 41, 48], array_values(iterator_to_array($pipeline)));
    }

    public function testReduce()
    {
        $pipeline = new Simple();

        $pipeline->map(function () {
            foreach (range(1, 10) as $i) {
                yield $i;
            }
        });

        $result = $pipeline->reduce();

        $this->assertEquals(55, $result);
    }

    public function testReduceToArray()
    {
        $pipeline = new Simple();

        $pipeline->map(function () {
            foreach (range(1, 10) as $i) {
                yield $i;
            }
        });

        $pipeline->map(function ($i) {
            yield pow($i, 2);
            yield pow($i, 3);
        });

        $pipeline->filter(function ($i) {
            return $i % 3 == 0;
        });

        // just what iterator_to_array does
        $result = $pipeline->reduce(function ($sum, $i) {
            $sum[] = $i;

            return $sum;
        }, []);

        $this->assertEquals([9, 27, 36, 216, 81, 729], $result);
    }

    public function testMeaningless()
    {
        $pipeline = new Simple(new \ArrayIterator([]));

        $pipeline->map(function ($i) {
            // never gets called
            yield $i + 1;
        });

        $this->assertEquals(0, $pipeline->reduce());
    }
}
