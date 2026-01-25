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
use PHPUnit\Framework\Attributes\CoversClass;
use Pipeline\Standard;

use function Pipeline\take;

/**
 * @internal
 */
#[CoversClass(Standard::class)]
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

    public function testChunkWithPreserveKeys(): void
    {
        $pipeline = take(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5]);
        $pipeline->chunkBy([2, 3], true);

        $this->assertSame([
            ['a' => 1, 'b' => 2],
            ['c' => 3, 'd' => 4, 'e' => 5],
        ], $pipeline->toAssoc());
    }

    public function testChunkWithoutPreserveKeys(): void
    {
        $pipeline = take(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5]);
        $pipeline->chunkBy([2, 3], false);

        $this->assertSame([
            [1, 2],
            [3, 4, 5],
        ], $pipeline->toAssoc());
    }

    public function testChunkPreserveKeysWithZero(): void
    {
        $pipeline = take(['a' => 1, 'b' => 2, 'c' => 3]);
        $pipeline->chunkBy([0, 2, 0, 2], true);

        $this->assertSame([
            [],
            ['a' => 1, 'b' => 2],
            [],
            ['c' => 3],
        ], $pipeline->toAssoc());
    }

    public function testChunkDataExhaustedMidChunk(): void
    {
        $pipeline = take(self::xrange(1, 5));
        $pipeline->chunkBy([2, 10]);

        $this->assertSame([
            [1, 2],
            [3, 4, 5],
        ], $pipeline->toList());
    }

    public function testChunkEmptyPipeline(): void
    {
        $pipeline = take([]);
        $pipeline->chunkBy([2, 5]);

        $this->assertSame([], $pipeline->toList());
    }

    public function testChunkEmptySizes(): void
    {
        $pipeline = take(self::xrange(1, 10));
        $pipeline->chunkBy([]);

        $this->assertSame([], $pipeline->toList());
    }

    public function testChunkWithZeroSize(): void
    {
        $pipeline = take(self::xrange(1, 5));
        $pipeline->chunkBy([2, 0, 2]);

        $this->assertSame([
            [1, 2],
            [],
            [3, 4],
        ], $pipeline->toList());
    }

    public function testChunkWithNegativeSize(): void
    {
        $pipeline = take(self::xrange(1, 5));
        $pipeline->chunkBy([2, -1, 2]);

        $this->assertSame([
            [1, 2],
            [],
            [3, 4],
        ], $pipeline->toList());
    }

    public function testChunkZeroNotEmittedWhenExhausted(): void
    {
        $pipeline = take(self::xrange(1, 3));
        $pipeline->chunkBy([3, 0]);

        $this->assertSame([
            [1, 2, 3],
        ], $pipeline->toList());
    }

    public function testChunkZeroAtStart(): void
    {
        $pipeline = take(self::xrange(1, 3));
        $pipeline->chunkBy([0, 3]);

        $this->assertSame([
            [],
            [1, 2, 3],
        ], $pipeline->toList());
    }

    public function testChunkMultipleZeros(): void
    {
        $pipeline = take(self::xrange(1, 3));
        $pipeline->chunkBy([0, 0, 2, 0]);

        $this->assertSame([
            [],
            [],
            [1, 2],
            [],
        ], $pipeline->toList());
    }

    public function testChunkSizeOne(): void
    {
        $pipeline = take(self::xrange(1, 3));
        $pipeline->chunkBy([1, 1, 1]);

        $this->assertSame([
            [1],
            [2],
            [3],
        ], $pipeline->toList());
    }

    private static function xrange(int $start, int $limit, int $step = 1)
    {
        for ($i = $start; $i <= $limit; $i += $step) {
            yield $i;
        }
    }

}
