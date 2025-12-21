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
use Iterator;
use Pipeline\Helper\CursorIterator;
use Pipeline\Standard;

use function iterator_count;
use function Pipeline\fromArray;
use function Pipeline\map;
use function Pipeline\take;

/**
 * @covers \Pipeline\Standard::cursor
 * @covers \Pipeline\Helper\CursorIterator
 *
 * @internal
 */
final class CursorTest extends TestCase
{
    public static function provideIterables(): iterable
    {
        yield 'array' => [fromArray([1, 2, 3, 4, 5])];

        yield 'ArrayIterator' => [take(new ArrayIterator([1, 2, 3, 4, 5]))];

        yield 'IteratorIterator' => [take(new IteratorIterator(new ArrayIterator([1, 2, 3, 4, 5])))];

        yield 'IteratorAggregate' => [take(new Standard(new IteratorIterator(new ArrayIterator([1, 2, 3, 4, 5]))))];

        yield 'Generator' => [map(fn() => yield from [1, 2, 3, 4, 5])];

        yield 'SameKeyGenerator' => [map(static function () {
            yield 1 => 1;
            yield 1 => 2;
            yield 1 => 3;
            yield 1 => 4;
            yield 1 => 5;
        })];

        yield 'stream' => [fromArray([1, 2, 3, 4, 5])->stream()];
    }

    /**
     * @dataProvider provideIterables
     */
    public function testCursorContinuesAfterBreak(Standard $pipeline): void
    {
        $cursor = $pipeline->cursor();

        $collected = [];
        foreach ($cursor as $i) {
            $collected[] = $i;
            if (2 === $i) {
                break;
            }
        }

        $this->assertSame([1, 2], $collected);

        $remaining = [];
        foreach ($cursor as $i) {
            $remaining[] = $i;
        }

        // CursorIterator auto-advances past the break point
        $this->assertSame([3, 4, 5], $remaining);
    }

    /**
     * @dataProvider provideIterables
     */
    public function testCursorWithTakeCount(Standard $pipeline): void
    {
        $cursor = $pipeline->cursor();

        foreach ($cursor as $i) {
            if (2 === $i) {
                break;
            }
        }

        // 3 elements remain: 3, 4, 5
        $this->assertSame(3, take($cursor)->count());
    }

    /**
     * @dataProvider provideIterables
     */
    public function testCursorWithSlice(Standard $pipeline): void
    {
        $cursor = $pipeline->cursor();

        $this->assertSame([1, 2], take($cursor)->slice(0, 2)->toList());

        // 3 elements remain: 3, 4, 5
        $this->assertSame(3, take($cursor)->count());
    }

    /**
     * @dataProvider provideIterables
     */
    public function testCursorWithTakeReduce(Standard $pipeline): void
    {
        $cursor = $pipeline->cursor();

        foreach ($cursor as $i) {
            if (2 === $i) {
                break;
            }
        }

        // Remaining: 3 + 4 + 5 = 12
        $this->assertSame(12, take($cursor)->reduce());
    }

    /**
     * @dataProvider provideIterables
     */
    public function testExhaustedCursorReturnsEmpty(Standard $pipeline): void
    {
        $cursor = $pipeline->cursor();

        // Consume all elements
        $this->assertSame(5, iterator_count($cursor));

        $remaining = [];
        foreach ($cursor as $i) {
            $remaining[] = $i;
        }

        $this->assertSame([], $remaining);
    }

    public function testCursorReturnsIterator(): void
    {
        $pipeline = fromArray([1, 2, 3]);
        $cursor = $pipeline->cursor();

        $this->assertInstanceOf(Iterator::class, $cursor);
    }

    public function testCursorAvoidDoubleWrapping(): void
    {
        $pipeline = fromArray([1, 2, 3]);

        $cursor1 = $pipeline->cursor();
        $cursor2 = take($cursor1)->cursor();

        // Should be the same instance (no double wrapping)
        $this->assertSame($cursor1, $cursor2);
    }

    public function testCursorWithEmptyPipeline(): void
    {
        $pipeline = fromArray([]);
        $cursor = $pipeline->cursor();

        $this->assertSame([], take($cursor)->toList());
    }

    public function testCursorPreservesKeys(): void
    {
        $pipeline = fromArray(['a' => 1, 'b' => 2, 'c' => 3]);
        $cursor = $pipeline->cursor();

        $collected = [];
        foreach ($cursor as $key => $value) {
            $collected[$key] = $value;
            if ('a' === $key) {
                break;
            }
        }

        $this->assertSame(['a' => 1], $collected);

        $remaining = [];
        foreach ($cursor as $key => $value) {
            $remaining[$key] = $value;
        }

        // CursorIterator auto-advances past 'a'
        $this->assertSame(['b' => 2, 'c' => 3], $remaining);
    }

    public function testCursorManualIteration(): void
    {
        $pipeline = fromArray([1, 2, 3]);
        $cursor = $pipeline->cursor();

        $this->assertTrue($cursor->valid());
        $this->assertSame(1, $cursor->current());

        $cursor->next();
        $this->assertSame(2, $cursor->current());

        $cursor->next();
        $this->assertSame(3, $cursor->current());

        $cursor->next();
        $this->assertFalse($cursor->valid());
    }
}
