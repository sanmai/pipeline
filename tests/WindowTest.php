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

use EmptyIterator;
use Iterator;

use function iterator_count;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

use function Pipeline\fromArray;

use Pipeline\Helper\WindowIterator;
use Pipeline\Standard;

use function Pipeline\take;

/**
 * @internal
 */
#[CoversClass(WindowIterator::class)]
#[CoversClass(Standard::class)]
final class WindowTest extends TestCase
{
    public static function provideIterables(): iterable
    {
        yield from self::pipelinesForInput([1, 2, 3, 4, 5]);
    }

    /**
     */
    #[DataProvider('provideIterables')]
    public function testWindowRewindsAndReplays(Standard $pipeline): void
    {
        $window = $pipeline->window(10);

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
     */
    #[DataProvider('provideIterables')]
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
     */
    #[DataProvider('provideIterables')]
    public function testWindowWithTakeCount(Standard $pipeline): void
    {
        $window = $pipeline->window(10);

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
     */
    #[DataProvider('provideIterables')]
    public function testWindowWithTakeReduce(Standard $pipeline): void
    {
        $window = $pipeline->window(10);

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
     */
    #[DataProvider('provideIterables')]
    public function testExhaustedWindowCanRewind(Standard $pipeline): void
    {
        $window = $pipeline->window(10);

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
        $window = $pipeline->window(10);

        $this->assertInstanceOf(Iterator::class, $window);
    }

    public function testWindowWithEmptyPipeline(): void
    {
        $pipeline = fromArray([]);
        $window = $pipeline->window(10);

        $this->assertSame([], take($window)->toList());
    }

    public function testWindowPreservesKeys(): void
    {
        $pipeline = fromArray(['a' => 1, 'b' => 2, 'c' => 3]);
        $window = $pipeline->window(10);

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
        $window = $pipeline->window(10);

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
        $window = $pipeline->window(10);

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
        $window = $pipeline->window(10);

        // Exhaust the window
        $this->assertSame(3, iterator_count($window));

        // Now invalid - should return null
        $this->assertFalse($window->valid());
        $this->assertNull($window->current());
        $this->assertNull($window->key());
    }

    public function testWindowIteratorWithEmptyIterator(): void
    {
        $window = new WindowIterator(new EmptyIterator(), 10);

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

        $window = new WindowIterator($iterator, 10);

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

        $window = new WindowIterator($iterator, 10);

        // Empty iterator - next() should not have been called
        $this->assertFalse($window->valid());
        $this->assertSame(0, $nextCalls);

        // Calling next() on window should not call inner next()
        $window->next();
        $window->next();
        $this->assertSame(0, $nextCalls);
    }

    public function testWindowWithStartedGenerator(): void
    {
        $generator = (function () {
            yield 1;
            yield 2;
            yield 3;
        })();

        // Advance the generator - it's now "dirty"
        $generator->next();
        $this->assertSame(2, $generator->current());

        // Wrapping in WindowIterator should not throw "Cannot rewind a generator"
        $window = new WindowIterator($generator, 10);

        // Should capture current element (2) and continue
        $collected = [];
        foreach ($window as $value) {
            $collected[] = $value;
        }

        $this->assertSame([2, 3], $collected);

        // Can rewind and replay
        $window->rewind();
        $this->assertSame([2, 3], take($window)->toList());
    }
}
