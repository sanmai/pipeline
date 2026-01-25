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

namespace Tests\Pipeline;

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use Pipeline\Standard;

use function Pipeline\fromArray;
use function round;
use function sqrt;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Pipeline\Standard::class)]
final class UnpackTest extends TestCase
{
    public function testMapVector(): void
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

        $this->assertSame(37.0, round($pipeline->reduce()));
    }

    public function testFlatMap(): void
    {
        $pipeline = new \Pipeline\Standard();

        $pipeline->map(function () {
            yield [1];
            yield [2, 3];
            yield [4, 5, 6];
            yield [7, 8, 9, 10];
        })->unpack();

        $this->assertSame((10 * 11) / 2, $pipeline->reduce());
    }

    public function testWithIterator(): void
    {
        $this->assertSame([1, 2, 3], fromArray([
            new ArrayIterator([1]),
            fromArray([2]),
            [3],
        ])->unpack()->toList());
    }

    public function testFlatten(): void
    {
        $this->assertSame([1, 2, 3], fromArray([
            new ArrayIterator([1]),
            fromArray([2]),
            [3],
        ])->flatten()->toList());
    }

    public function testUnpackUnprimed(): void
    {
        $pipeline = new Standard();
        $pipeline->unpack(function () {
            return 1;
        });

        $this->assertSame([1], $pipeline->toList());
    }
}
