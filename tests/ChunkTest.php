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
use Pipeline\Standard;

use function Pipeline\fromArray;
use function Pipeline\take;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class ChunkTest extends TestCase
{
    public static function provideArrays(): iterable
    {
        yield [false, 3, [], []];

        yield [false, 3, [1, 2, 3, 4, 5], [[1, 2, 3], [4, 5]]];

        yield [null, 1, [1, 2, 3, 4, 5], [[1], [2], [3], [4], [5]]];

        yield [true, 3, [1, 2, 3, 4, 5], [[1, 2, 3], [3 => 4, 4 => 5]]];

        yield [true, 2, ['a' => 1, 'b' => 2, 'c' => 3], [['a' => 1, 'b' => 2], ['c' => 3]]];

        yield [false, 2, ['a' => 1, 'b' => 2, 'c' => 3], [[1, 2], [3]]];
    }

    public static function provideIterables(): iterable
    {
        foreach (self::provideArrays() as $item) {
            yield $item;

            $iteratorItem = $item;
            $iteratorItem[2] = new ArrayIterator($iteratorItem[2]);

            yield $iteratorItem;

            $iteratorItem = $item;
            $iteratorItem[2] = new IteratorIterator(new ArrayIterator($iteratorItem[2]));

            yield $iteratorItem;

            $iteratorItem = $item;
            $iteratorItem[2] = fromArray($iteratorItem[2]);

            yield $iteratorItem;
        }
    }

    /**
     * @dataProvider provideIterables
     */
    public function testChunk(?bool $preserve_keys, int $length, iterable $input, array $expected): void
    {
        $pipeline = take($input);

        if (null === $preserve_keys) {
            $pipeline->chunk($length);
        } else { // @phpstan-ignore-line
            $pipeline->chunk($length, $preserve_keys);
        }

        $this->assertSame($expected, $pipeline->toArray($preserve_keys ?? false));
    }

    /**
     * @dataProvider provideIterables
     */
    public function testChunkBy(?bool $preserve_keys, int $length, iterable $input, array $expected): void
    {
        $pipeline = take($input);

        if (null === $preserve_keys) {
            $pipeline->chunkBy(self::infiniteGenerator($length));
        } else { // @phpstan-ignore-line
            $pipeline->chunkBy(self::infiniteGenerator($length), $preserve_keys);
        }

        $this->assertSame($expected, $pipeline->toArray($preserve_keys ?? false));
    }

    private static function infiniteGenerator(int $number): iterable
    {
        do {
            yield $number;
        } while (true);
    }

    public function testChunkNoop(): void
    {
        $pipeline = new Standard();

        $pipeline->chunk(100);

        $this->assertSame([], $pipeline->toList());
    }
}
