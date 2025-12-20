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
use Pipeline\Standard;

use function Pipeline\fromArray;
use function Pipeline\map;
use function Pipeline\take;

/**
 * @covers \Pipeline\Standard::cursor
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

        yield 'generator' => [map(function () {
            yield from [1, 2, 3, 4, 5];
        })];

        yield 'stream' => [fromArray([1, 2, 3, 4, 5])->stream()];
    }

    /**
     * @dataProvider provideIterables
     */
    public function testCursorContinuesAfterBreak(\Pipeline\Standard $pipeline): void
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

        // NoRewindIterator resumes at current position, so element 2 appears again
        $this->assertSame([2, 3, 4, 5], $remaining);
    }

    /**
     * @dataProvider provideIterables
     */
    public function testCursorWithTakeCount(\Pipeline\Standard $pipeline): void
    {
        $cursor = $pipeline->cursor();

        foreach ($cursor as $i) {
            if (2 === $i) {
                break;
            }
        }

        // Resumes at current position (2), so 4 elements remain: 2, 3, 4, 5
        $this->assertSame(4, take($cursor)->count());
    }

    /**
     * @dataProvider provideIterables
     */
    public function testCursorWithTakeReduce(\Pipeline\Standard $pipeline): void
    {
        $cursor = $pipeline->cursor();

        foreach ($cursor as $i) {
            if (2 === $i) {
                break;
            }
        }

        // Remaining: 2 + 3 + 4 + 5 = 14
        $this->assertSame(14, take($cursor)->reduce());
    }

    /**
     * @dataProvider provideIterables
     */
    public function testExhaustedCursorReturnsEmpty(\Pipeline\Standard $pipeline): void
    {
        $cursor = $pipeline->cursor();

        // Consume all elements
        foreach ($cursor as $i) {
            // drain
        }

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

        $collected = [];
        foreach ($cursor as $i) {
            $collected[] = $i;
        }

        $this->assertSame([], $collected);
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

        // Resumes at current position, so 'a' appears again
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $remaining);
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
