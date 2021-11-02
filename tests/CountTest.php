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
use function Pipeline\fromArray;
use function Pipeline\map;
use Pipeline\Standard;
use function Pipeline\take;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class CountTest extends TestCase
{
    public function testCountZeroForUninitialized(): void
    {
        $this->assertCount(0, new Standard());
    }

    public function testCountZeroEmptyArray(): void
    {
        $this->assertCount(0, fromArray([]));
    }

    public function testCountNonEmptyArray(): void
    {
        $this->assertCount(3, fromArray([1, 2, 3]));
    }

    public function testCountNonEmptyIterator(): void
    {
        $this->assertCount(3, take(new ArrayIterator([1, 2, 3])));
    }

    public function testCountValues(): void
    {
        $this->assertCount(3, map(static function () {
            yield 1 => 1;
            yield 1 => 2;
            yield 2 => 3;
        }));
    }
}
