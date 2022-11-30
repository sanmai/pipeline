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
use PHPUnit\Framework\TestCase;
use function Pipeline\take;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class ChunkTest extends TestCase
{
    public function provideArrays(): iterable
    {
        yield [false, 3, [1, 2, 3, 4, 5], [[1, 2, 3], [4, 5]]];
    }

    public function provideIterables(): iterable
    {
        foreach ($this->provideArrays() as $item) {
            yield $item;

            $iteratorItem = $item;
            $iteratorItem[2] = new ArrayIterator($iteratorItem[2]);

            yield $iteratorItem;

            $iteratorItem = $item;
            $iteratorItem[2] = new IteratorIterator(new ArrayIterator($iteratorItem[2]));

            yield $iteratorItem;
        }
    }

    /**
     * @dataProvider provideIterables
     */
    public function testChunk(bool $preserve_keys, int $length, iterable $input, array $expected): void
    {
        $pipeline = take($input);

        $pipeline->chunk($length, $preserve_keys);

        $this->assertSame($expected, $pipeline->toArray($preserve_keys));
    }
}
