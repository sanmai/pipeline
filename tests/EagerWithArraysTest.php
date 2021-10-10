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

use PHPUnit\Framework\TestCase;
use function Pipeline\fromArray;
use Pipeline\Standard;
use function Pipeline\take;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class EagerWithArraysTest extends TestCase
{
    public static function specimens(): \Generator
    {
        yield 'take' => [take([0, 0, 1, 2, 3])];
        yield 'fromArray' => [fromArray([0, 0, 1, 2, 3])];
    }

    /**
     * @dataProvider specimens
     */
    public function testEagerArrayFilter(Standard $pipeline): void
    {
        $reflectionClass = new \ReflectionClass(Standard::class);
        $reflectionProperty = $reflectionClass->getProperty('pipeline');
        $reflectionProperty->setAccessible(true);

        $pipeline->filter();
        // At this point $pipeline should contain exactly [1, 2, 3]

        $this->assertSame([2 => 1, 2, 3], $reflectionProperty->getValue($pipeline));

        $this->assertSame([1, 2, 3], $pipeline->toArray());

        // This does nothing more
        $this->assertSame([1, 2, 3], $pipeline->filter()->toArray());
    }

    /**
     * @dataProvider specimens
     */
    public function testEagerArrayReduce(Standard $pipeline): void
    {
        $this->assertSame(6, $pipeline->reduce());

        // Second reduce over the same pipeline is impossible with an underlying generator
        // But should be possible with an array
        $this->assertSame(6, $pipeline->reduce());
    }

    /**
     * @dataProvider specimens
     */
    public function testEagerArrayFilterAndReduce(Standard $pipeline): void
    {
        $this->assertSame(6, $pipeline->filter()->reduce());
        // This should be possible with an array
        $this->assertSame(6, $pipeline->filter()->reduce());
    }

    /**
     * @dataProvider specimens
     */
    public function testNonEagerArrayMap(Standard $pipeline): void
    {
        $this->assertSame([1, 1, 1, 1, 1], $pipeline->map(function ($value) {
            return 1;
        })->toArray());

        // This should not be possible even with an array, as map() is always lazy
        $this->expectExceptionMessage('Cannot traverse an already closed generator');
        $pipeline->toArray();
    }
}
