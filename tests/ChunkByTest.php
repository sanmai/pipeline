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

use function Pipeline\take;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class ChunkByTest extends TestCase
{
    public function testChunkIterable(): void
    {
        $pipeline = take(self::xrange(1, 10));
        $pipeline->chunkBy([2, 5, 5]);

        $this->assertSame([
            [1, 2],
            [3, 4, 5, 6, 7],
            [8, 9, 10],
        ], $pipeline->toList());
    }

    public function testChunkIterablePartial(): void
    {
        $pipeline = take(self::xrange(1, 10));
        $pipeline->chunkBy([2, 5]);

        $this->assertSame([
            [1, 2],
            [3, 4, 5, 6, 7],
            [8, 9],
            [10],
        ], $pipeline->toList());
    }

    public function testChunkGenerator(): void
    {
        $pipeline = take(self::xrange(1, 10));
        $pipeline->chunkBy(static function () {
            yield 2;
            while (true) {
                yield 5;
            }
        });

        $this->assertSame([
            [1, 2],
            [3, 4, 5, 6, 7],
            [8, 9, 10],
        ], $pipeline->toList());
    }

    private static function xrange(int $start, int $limit, int $step = 1)
    {
        for ($i = $start; $i <= $limit; $i += $step) {
            yield $i;
        }
    }

}
