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

namespace Tests\Pipeline;

use PHPUnit\Framework\TestCase;
use function Pipeline\fromArray;

/**
 * @covers \Pipeline\Standard
 */
class UnpackTest extends TestCase
{
    /**
     * @covers \Pipeline\Standard::unpack()
     */
    public function testMapVector()
    {
        $pipeline = new \Pipeline\Standard();

        $pipeline->map(function () {
            yield [5, 7];
            yield [13, 13];
            yield [-1, 1];
            yield [-5, -7];
        });

        $pipeline->unpack(function ($x, $y) {
            return sqrt($x ** 2 + $y ** 2);
        });

        $this->assertEquals(37, round($pipeline->reduce()));
    }

    /**
     * @covers \Pipeline\Standard::unpack()
     */
    public function testFlatMap()
    {
        $pipeline = new \Pipeline\Standard();

        $pipeline->map(function () {
            yield [1];
            yield [2, 3];
            yield [4, 5, 6];
            yield [7, 8, 9, 10];
        })->unpack();

        $this->assertEquals((10 * 11) / 2, round($pipeline->reduce()));
    }

    /**
     * @covers \Pipeline\Standard::unpack()
     */
    public function testWithIterator()
    {
        $this->assertSame([1, 2, 3], fromArray([
            new \ArrayIterator([1]),
            fromArray([2]),
            [3],
        ])->unpack()->toArray());
    }
}
