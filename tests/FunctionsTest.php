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
 * @covers \Pipeline\fromArray
 * @covers \Pipeline\map
 * @covers \Pipeline\take
 * @covers \Pipeline\zip
 *
 * @internal
 */
final class FunctionsTest extends TestCase
{
    /**
     * @covers \Pipeline\map
     */
    public function testMapFunction(): void
    {
        $pipeline = map();
        $this->assertInstanceOf(Standard::class, $pipeline);
        $this->assertSame([], \iterator_to_array($pipeline));

        $pipeline = map(function () {
            yield 1;
            yield 2;
        });

        $this->assertInstanceOf(Standard::class, $pipeline);

        $this->assertSame(3, $pipeline->reduce());
    }

    /**
     * @covers \Pipeline\take
     */
    public function testTakeFunction(): void
    {
        $pipeline = take();
        $this->assertInstanceOf(Standard::class, $pipeline);
        $this->assertSame([], \iterator_to_array($pipeline));

        $pipeline = take(new \ArrayIterator([1, 2, 3]));
        $this->assertInstanceOf(Standard::class, $pipeline);
        $this->assertSame(6, $pipeline->reduce());
    }

    /**
     * @covers \Pipeline\take
     */
    public function testTakeArray(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], take([1, 2, 3, 4, 5])->toArray());
    }

    /**
     * @covers \Pipeline\fromArray
     */
    public function testFromArray(): void
    {
        $pipeline = fromArray(\range(0, 100));
        $this->assertInstanceOf(Standard::class, $pipeline);
        $this->assertSame(\range(0, 100), $pipeline->toArray());
    }

    /**
     * @covers \Pipeline\zip
     */
    public function testZip(): void
    {
        $pipeline = zip([1, 2], [3, 4]);
        $this->assertSame([[1, 3], [2, 4]], $pipeline->toArray());

        $array = [[1, 2, 3], [4, 5, 6], [7, 8, 9]];

        $pipeline = zip(...$array);
        $this->assertInstanceOf(Standard::class, $pipeline);

        $this->assertSame(\array_map(null, ...$array), $pipeline->toArray());
    }
}
