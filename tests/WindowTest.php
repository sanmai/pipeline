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
use EmptyIterator;
use IteratorIterator;
use PHPUnit\Framework\TestCase;
use Iterator;
use Pipeline\Helper\WindowIterator;
use Pipeline\Standard;

use function iterator_count;
use function Pipeline\fromArray;
use function Pipeline\map;
use function Pipeline\take;

/**
 * @covers \Pipeline\Standard::window
 * @covers \Pipeline\Helper\WindowIterator
 *
 * @internal
 */
final class WindowTest extends TestCase
{
    public static function provideIterables(): iterable
    {
        yield 'array' => [fromArray([1, 2, 3, 4, 5])];

        yield 'ArrayIterator' => [take(new ArrayIterator([1, 2, 3, 4, 5]))];

        yield 'IteratorIterator' => [take(new IteratorIterator(new ArrayIterator([1, 2, 3, 4, 5])))];

        yield 'IteratorAggregate' => [take(new Standard(new IteratorIterator(new ArrayIterator([1, 2, 3, 4, 5]))))];

        yield 'generator' => [map(fn() => yield from [1, 2, 3, 4, 5])];

        yield 'stream' => [fromArray([1, 2, 3, 4, 5])->stream()];
    }

    /**
     * @dataProvider provideIterables
     */
    public function testWindowRewindsAndReplays(Standard $pipeline): void
    {
        $window = $pipeline->window();

        $collected = [];
        foreach ($window as $i) {
            $collected[] = $i;
            if (3 === $i) {
                break;
            }
        }

        $this->assertSame([1, 2, 3], $collected);

        // Replay from beginning
        $replayed = [];
        foreach ($window as $i) {
            $replayed[] = $i;
        }

        // Full replay including items after the break
        $this->assertSame([1, 2, 3, 4, 5], $replayed);
    }

    /**
     * @dataProvider provideIterables
     */
    public function testWindowWithSizeLimit(Standard $pipeline): void
    {
        $window = $pipeline->window(3);

        $collected = [];
        foreach ($window as $i) {
            $collected[] = $i;
            if (4 === $i) {
                break;
            }
        }

        $this->assertSame([1, 2, 3, 4], $collected);

        // Rewind - oldest element (1) was dropped
        $replayed = [];
        foreach ($window as $i) {
            $replayed[] = $i;
        }

        // Buffer had [2, 3, 4], then continues to fetch 5
        $this->assertSame([2, 3, 4, 5], $replayed);
    }

    /**
     * @dataProvider provideIterables
     */
    public function testWindowWithTakeCount(Standard $pipeline): void
    {
        $window = $pipeline->window();

        foreach ($window as $i) {
            if (2 === $i) {
                break;
            }
        }

        // Rewind and count all
        $window->rewind();
        $this->assertSame(5, take($window)->count());
    }

    /**
     * @dataProvider provideIterables
     */
    public function testWindowWithTakeReduce(Standard $pipeline): void
    {
        $window = $pipeline->window();

        foreach ($window as $i) {
            if (2 === $i) {
                break;
            }
        }

        // Rewind and reduce all: 1 + 2 + 3 + 4 + 5 = 15
        $window->rewind();
        $this->assertSame(15, take($window)->reduce());
    }

    /**
     * @dataProvider provideIterables
     */
    public function testExhaustedWindowCanRewind(Standard $pipeline): void
    {
        $window = $pipeline->window();

        // Consume all elements
        $this->assertSame(5, iterator_count($window));

        // Rewind and replay
        $replayed = [];
        foreach ($window as $i) {
            $replayed[] = $i;
        }

        $this->assertSame([1, 2, 3, 4, 5], $replayed);
    }

    public function testWindowReturnsIterator(): void
    {
        $pipeline = fromArray([1, 2, 3]);
        $window = $pipeline->window();

        $this->assertInstanceOf(Iterator::class, $window);
    }

    public function testWindowAvoidDoubleWrapping(): void
    {
        $pipeline = fromArray([1, 2, 3]);

        $window1 = $pipeline->window();
        $window2 = take($window1)->window();

        // Should be the same instance (no double wrapping)
        $this->assertSame($window1, $window2);
    }

    public function testWindowWithEmptyPipeline(): void
    {
        $pipeline = fromArray([]);
        $window = $pipeline->window();

        $this->assertSame([], take($window)->toList());
    }

    public function testWindowPreservesKeys(): void
    {
        $pipeline = fromArray(['a' => 1, 'b' => 2, 'c' => 3]);
        $window = $pipeline->window();

        $collected = [];
        foreach ($window as $key => $value) {
            $collected[$key] = $value;
            if ('a' === $key) {
                break;
            }
        }

        $this->assertSame(['a' => 1], $collected);

        // Rewind and get all
        $all = [];
        foreach ($window as $key => $value) {
            $all[$key] = $value;
        }

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $all);
    }

    public function testWindowManualIteration(): void
    {
        $pipeline = fromArray([1, 2, 3]);
        $window = $pipeline->window();

        $this->assertTrue($window->valid());
        $this->assertSame(1, $window->current());

        $window->next();
        $this->assertSame(2, $window->current());

        $window->next();
        $this->assertSame(3, $window->current());

        $window->next();
        $this->assertFalse($window->valid());

        // Can rewind after exhaustion
        $window->rewind();
        $this->assertTrue($window->valid());
        $this->assertSame(1, $window->current());
    }

    public function testWindowSlidingWindowDropsOldest(): void
    {
        $pipeline = fromArray([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
        $window = $pipeline->window(5);

        // Consume all
        $all = [];
        foreach ($window as $i) {
            $all[] = $i;
        }
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $all);

        // Rewind - only last 5 elements in buffer
        $buffered = [];
        foreach ($window as $i) {
            $buffered[] = $i;
        }

        $this->assertSame([6, 7, 8, 9, 10], $buffered);
    }

    public function testWindowMultipleRewinds(): void
    {
        $pipeline = fromArray([1, 2, 3]);
        $window = $pipeline->window();

        // First iteration
        $this->assertSame([1, 2, 3], take($window)->toList());

        // Second
        $window->rewind();
        $this->assertSame([1, 2, 3], take($window)->toList());

        // Third
        $window->rewind();
        $this->assertSame([1, 2, 3], take($window)->toList());
    }

    public function testWindowCurrentAndKeyWhenInvalid(): void
    {
        $pipeline = fromArray([1, 2, 3]);
        $window = $pipeline->window();

        // Exhaust the window
        $this->assertSame(3, iterator_count($window));

        // Now invalid - should return null
        $this->assertFalse($window->valid());
        $this->assertNull($window->current());
        $this->assertNull($window->key());
    }

    public function testWindowIteratorWithEmptyIterator(): void
    {
        $window = new WindowIterator(new EmptyIterator());

        $this->assertFalse($window->valid());
        $this->assertNull($window->current());
        $this->assertNull($window->key());
    }

    public function testWindowIteratorDoesNotCallInnerAfterExhaustion(): void
    {
        $nextCalls = 0;
        $iterator = new class ($nextCalls) implements Iterator {
            private int $pos = 0;

            /** @var array<int, int> */
            private array $data = [1, 2];

            private int $nextCalls;

            public function __construct(int &$nextCalls)
            {
                $this->nextCalls = &$nextCalls;
            }

            public function current(): mixed
            {
                return $this->data[$this->pos] ?? null;
            }

            public function key(): mixed
            {
                return $this->pos;
            }

            public function next(): void
            {
                ++$this->nextCalls;
                ++$this->pos;
            }

            public function rewind(): void
            {
                $this->pos = 0;
            }

            public function valid(): bool
            {
                return isset($this->data[$this->pos]);
            }
        };

        $window = new WindowIterator($iterator);

        // Consume all - next() called twice (to advance from 1 to 2, then to exhaust)
        iterator_count($window);
        $this->assertSame(2, $nextCalls);

        // Call next() on window again - should NOT call inner next()
        $window->next();
        $window->next();
        $this->assertSame(2, $nextCalls);
    }

    public function testWindowIteratorEmptyDoesNotCallInner(): void
    {
        $nextCalls = 0;
        $iterator = new class ($nextCalls) implements Iterator {
            private int $nextCalls;

            public function __construct(int &$nextCalls)
            {
                $this->nextCalls = &$nextCalls;
            }

            public function current(): mixed
            {
                return null;
            }

            public function key(): mixed
            {
                return null;
            }

            public function next(): void
            {
                ++$this->nextCalls;
            }

            public function rewind(): void {}

            public function valid(): bool
            {
                return false;
            }
        };

        $window = new WindowIterator($iterator);

        // Empty iterator - next() should not have been called
        $this->assertFalse($window->valid());
        $this->assertSame(0, $nextCalls);

        // Calling next() on window should not call inner next()
        $window->next();
        $window->next();
        $this->assertSame(0, $nextCalls);
    }
}
