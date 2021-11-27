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
use IteratorIterator;
use function mt_rand;
use function mt_srand;
use PHPUnit\Framework\TestCase;
use function Pipeline\map;
use function Pipeline\take;
use function range;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class ReservoirTest extends TestCase
{
    protected function setUp(): void
    {
        mt_srand(0);
    }

    public function testRandomSeed(): void
    {
        $this->assertSame(
            [20, 17, 11, 13, 18, 13],
            take(range(0, 5))->map(function () {
                return mt_rand(10, 20);
            })->toArray()
        );
    }

    public function provideInputs(): iterable
    {
        yield 'no change case' => [['a', 'b', 'c'], 3, ['a', 'b', 'c']];

        yield [['a', 'b', 'c'], -1, []];

        yield [['a', 'b', 'c'], 0, []];

        yield [['a', 'b', 'c'], 1, ['c']];

        yield [['a', 'b', 'c'], 2, ['a', 'b']];

        yield [['a', 'b', 'c'], 4, ['a', 'b', 'c']];

        yield [['a', 'b', 'c', 'd', 'e', 'f'], 2, ['f', 'b']];

        yield [['a', 'b', 'c', 'd', 'e', 'f'], 3, ['d', 'b', 'c']];

        yield [['a', 'b', 'c', 'd', 'e', 'f'], 4, ['a', 'b', 'c', 'f']];

        yield [range(0, 1000), 10, [
            838,
            96,
            381,
            971,
            87,
            715,
            589,
            168,
            693,
            366,
        ]];
    }

    /**
     * @dataProvider provideInputs
     */
    public function testSampleFromGenerator(array $input, int $size, array $expected): void
    {
        $this->assertSame($expected, map(static function () use ($input) {
            yield from $input;
        })->reservoir($size));
    }

    /**
     * @dataProvider provideInputs
     */
    public function testSampleFromArray(array $input, int $size, array $expected): void
    {
        $this->assertSame($expected, take($input)->reservoir($size));
    }

    /**
     * @dataProvider provideInputs
     */
    public function testSampleFromIterator(array $input, int $size, array $expected): void
    {
        $input = new IteratorIterator(new ArrayIterator($input));

        $this->assertSame($expected, take($input)->reservoir($size));
    }
}
