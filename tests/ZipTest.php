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
use function Pipeline\map;
use Pipeline\Standard;
use function Pipeline\take;
use function Pipeline\zip;

/**
 * @covers \Pipeline\Principal
 *
 * @internal
 */
final class ZipTest extends TestCase
{
    /**
     * @covers \Pipeline\Principal::zip()
     */
    public function testZipArray(): void
    {
        $pipeline = new Standard();

        $pipeline->zip([1, 2], [3, 4]);
        $this->assertSame([[1, 3], [2, 4]], $pipeline->toArray());

        $array = [[1, 2, 3], [4, 5, 6], [7, 8, 9]];

        $pipeline = new Standard();

        $pipeline->zip(...$array);

        $this->assertSame(\array_map(null, ...$array), $pipeline->toArray());
    }

    /**
     * @covers \Pipeline\Principal::zip()
     */
    public function testZipSelf(): void
    {
        $pipeline = fromArray([1, 2]);

        $pipeline->zip(fromArray([3, 4]));

        $this->assertSame([[1, 3], [2, 4]], $pipeline->toArray());
    }

    /**
     * @covers \Pipeline\Principal::zip()
     */
    public function testZipGenerator(): void
    {
        $pipeline = map(function () {
            yield 1;
            yield 2;
        });

        $pipeline->zip(map(function () {
            yield 3;
            yield 4;
        }));

        $this->assertSame([[1, 3], [2, 4]], $pipeline->toArray());
    }

    /**
     * @covers \Pipeline\Principal::zip()
     */
    public function testNoop(): void
    {
        $pipeline = new Standard();
        $pipeline->zip([3, 4]);

        $this->assertSame([3, 4], $pipeline->toArray());
    }

    public function testZipEmpty(): void
    {
        $this->assertSame([], take([])->zip([1, 2, 3])->toArray());
    }

    public function testZipUnequalLengths(): void
    {
        $actual = take(['a', 'b', 'c'])
            ->zip([1, 2], [3])
            ->toArray()
        ;

        $this->assertSame([
            ['a', 1, 3],
            ['b', 2, null],
            ['c', null, null],
        ], $actual);
    }

    public function testZipUnevenLengths(): void
    {
        $actual = take(['a', 'b', 'c'])
            ->zip([1], [2, 3])
            ->toArray()
        ;

        $this->assertSame([
            ['a', 1, 2],
            ['b', null, 3],
            ['c', null, null],
        ], $actual);
    }

    public function testZipOverShortIndex(): void
    {
        $actual = take(['a', 'b', 'c'])
            ->zip(\range(1, 10), \range(10, 100, 10))
            ->toArray()
        ;

        $this->assertSame([
            ['a', 1, 10],
            ['b', 2, 20],
            ['c', 3, 30],
        ], $actual);
    }

    public function testZipNothing(): void
    {
        $pipeline = new Standard();

        $this->assertSame([], $pipeline->zip([])->toArray());
    }

    public function testExample(): void
    {
        $iterable = \range(5, 7);
        $pipeline = take($iterable);
        $pipeline->zip(
            \range(1, 3),
            map(function () {
                yield 1;
                yield 2;
                yield 3;
            })
        );

        $pipeline->unpack(function (int $a, int $b, int $c) {
            return $a - $b - $c;
        });

        $this->assertSame(6, $pipeline->reduce());
    }

    public function testExampleFromReadme(): void
    {
        $iterableA = new \ArrayIterator([1, 2, 3]);
        $iterableB = new \ArrayIterator([3, 2, 1]);
        $iterableC = new \ArrayIterator([2, 3, 1]);

        $pipeline = take($iterableA);
        $pipeline->zip($iterableB, $iterableC);
        $pipeline->unpack(function ($elementOfA, $elementOfB, $elementOfC) {
            return \max($elementOfA, $elementOfB, $elementOfC);
        });

        $this->assertSame(9, $pipeline->reduce());
    }

    public function testAnotherExampleForReadme(): void
    {
        $iterableA = new \ArrayIterator([1, 2, 3]);
        $iterableB = new \ArrayIterator([3, 2, 1]);
        $iterableC = new \ArrayIterator([2, 3, 1]);

        $pipeline = zip($iterableA, $iterableB, $iterableC);
        $pipeline->unpack(function ($elementOfA, $elementOfB, $elementOfC) {
            return \max($elementOfA, $elementOfB, $elementOfC);
        });

        $this->assertSame(9, $pipeline->reduce());
    }
}
