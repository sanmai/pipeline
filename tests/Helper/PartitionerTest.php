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

namespace Tests\Pipeline\Helper;

use PHPUnit\Framework\TestCase;
use Pipeline\Helper\Partitioner;
use Generator;
use Exception;

use function array_filter;
use function array_map;
use function array_merge;
use function count;
use function gc_collect_cycles;
use function iterator_to_array;
use function range;

/**
 * @covers \Pipeline\Helper\Partitioner
 * @covers \Pipeline\Helper\PartitionIterator
 *
 * @internal
 */
final class PartitionerTest extends TestCase
{
    /** @var int[] */
    private array $sourceData;

    protected function setUp(): void
    {
        // a mix of evens and odds
        $this->sourceData = [2,1,4,3,6,5,8,7];
    }

    private function makeGenerator(array $data): Generator
    {
        foreach ($data as $v) {
            yield $v;
        }
    }

    public function testEvenAndOddSplitCorrectly(): void
    {
        $gen = $this->makeGenerator($this->sourceData);
        [$evenIter, $oddIter] = Partitioner::partition($gen, fn(int $x) => 0 === $x % 2);

        $this->assertSame([2,4,6,8], iterator_to_array($evenIter), 'Evens should be [2,4,6,8]');
        $this->assertSame([1,3,5,7], iterator_to_array($oddIter), 'Odds  should be [1,3,5,7]');
    }

    public function testLazyEvaluationStopsWhenOneSideConsumed(): void
    {
        $calls = 0;
        $gen = (function () use (&$calls) {
            for ($i = 0; $i < 100; $i++) {
                $calls++;
                yield $i;
            }
        })();

        [$evenIter, $oddIter] = Partitioner::partition($gen, fn(int $x) => 0 === $x % 2);

        // Only pull the first two evens, then stop.
        $evens = [];
        foreach ($evenIter as $n) {
            $evens[] = $n;
            if (2 === count($evens)) {
                break;
            }
        }

        // We should have seen only as many source pulls as needed to find 2 evens:
        // the sequence is 0,1,2,3,... so we needed up to 2 -> index 2 -> 3 pulls.
        $this->assertSame([0,2], $evens);
        $this->assertLessThan(10, $calls, "We shouldn't have pulled the full 100 values; calls={$calls}");
    }


    public function testDroppingSideStopsBufferGrowth(): void
    {
        $gen = (function () {
            yield 2;
            // stream of odds
            for ($i = 0; $i < 1000; $i++) {
                yield 1;
            }
            yield 4;
        })();

        [$evenIter, $oddIter] = Partitioner::partition($gen, fn(int $x) => 0 === $x % 2);

        // consume the first even
        $this->assertSame(2, $evenIter->current());
        $evenIter->next();

        // now drop the odd side -> its buffer should never grow unbounded
        unset($oddIter);
        gc_collect_cycles();

        // keep pulling the next even
        $this->assertSame(4, $evenIter->current());
    }

    public function testFullConsumptionWorksBothSides(): void
    {
        $data = range(1, 20);
        $gen  = $this->makeGenerator($data);
        [$evenIter, $oddIter] = Partitioner::partition($gen, fn(int $x) => 0 === $x % 2);

        // interleave full consumption
        $result = [];
        foreach ($evenIter as $e) {
            $result[] = "E{$e}";
        }
        foreach ($oddIter as $o) {
            $result[] = "O{$o}";
        }

        // check exact order of records
        $expected = array_merge(
            array_map(fn($x) => "E{$x}", array_filter($data, fn($x) => 0 === $x % 2)),
            array_map(fn($x) => "O{$x}", array_filter($data, fn($x) => 0 !== $x % 2))
        );
        $this->assertSame($expected, $result);
    }

    public function testPartitionIteratorAutoRewind(): void
    {
        // source yields a single even value
        $gen = $this->makeGenerator([2]);
        [$evenIter, $oddIter] = Partitioner::partition(
            $gen,
            fn(int $x): bool => 0 === $x % 2
        );

        // without ever calling rewind(), we should already be at the first element
        $this->assertTrue($evenIter->valid(), 'Even iterator must be valid on creation');
        $this->assertSame(2, $evenIter->current(), 'First current() should be 2');

        // odd‐side has no items, so valid() is false immediately
        $this->assertFalse($oddIter->valid(), 'Odd iterator must be invalid on creation');
    }
    public function testRewindBeforeIterationDoesNotError(): void
    {
        $gen = $this->makeGenerator([2, 4, 6]);
        [$evenIter, ] = Partitioner::partition(
            $gen,
            fn(int $x): bool => 0 === $x % 2
        );

        // rewind before any iteration - must not error
        $evenIter->rewind();

        // still yields all the evens
        $this->assertSame([2,4,6], iterator_to_array($evenIter));
    }

    public function testRewindAfterConsumptionThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot rewind a generator that was already run');

        $gen = $this->makeGenerator([2, 4, 6]);
        [$evenIter,] = Partitioner::partition(
            $gen,
            fn(int $x): bool => 0 === $x % 2
        );

        // consume everything
        foreach ($evenIter as $_) {
        }

        // now this will throw the internal Exception
        $evenIter->rewind();
    }


    /**
     * Dropping the iterator (via unset + GC) also stops buffering.
     */
    public function testDestructUnsetsSide(): void
    {
        $gen = (function () {
            yield 2;
            // a long stretch of odd values
            for ($i = 0; $i < 500; $i++) {
                yield 1;
            }
            yield 4;
        })();

        [$evenIter, $oddIter] = Partitioner::partition(
            $gen,
            fn(int $x): bool => 0 === $x % 2
        );

        // consume the very first even
        $this->assertSame(2, $evenIter->current());
        $evenIter->next();

        // drop the odd iterator; force collection
        unset($oddIter);
        gc_collect_cycles();

        // now we should still get the second even, despite the long odd run
        $this->assertSame(4, $evenIter->current());
    }

    /**
     * Once fully consumed, valid() must return false.
     */
    public function testFullConsumptionInvalidates(): void
    {
        $data = [2,4];
        $gen  = $this->makeGenerator($data);
        [$evenIter, ] = Partitioner::partition(
            $gen,
            fn(int $x): bool => 0 === $x % 2
        );

        // drain it
        foreach ($evenIter as $_) {
        }
        $this->assertFalse($evenIter->valid(), 'After consuming all items, valid() is false');
        $this->assertNull($evenIter->current(), 'After exhaustion, current() yields null');
    }

    /**
     * Interleaved pulls: confirm that pulling odd then even yields the right alternating order
     * and does not dead‐lock or skip elements.
     */
    public function testInterleavedConsumption(): void
    {
        $gen  = $this->makeGenerator([2,1,4,3,6,5]);
        $gen->next();
        [$evenIter, $oddIter] = Partitioner::partition(
            $gen,
            fn(int $x): bool => 0 === $x % 2
        );

        $result = [];
        // pull one from each side, alternating
        $result[] = $oddIter->current();
        $oddIter->next();   // 1
        $result[] = $evenIter->current();
        $evenIter->next();  // 4
        $result[] = $oddIter->current();
        $oddIter->next();   // 3

        $this->assertSame([1,4,3], $result, 'Items must interleave correctly');
    }

    public function testPartitionDoesNotRewindByDefault(): void
    {
        $gen = (function (): Generator {
            yield 1;
            yield 2;
            yield 3;
        })();
        $gen->next(); // skip “1”

        [$evens, $odds] = Partitioner::partition(
            $gen,
            fn(int $x): bool => 0 === $x % 2
        );

        // must start at the *current* position (2,3)
        $this->assertSame([2], iterator_to_array($evens));
        $this->assertSame([3], iterator_to_array($odds));
    }

    public function testDroppingSideStopsBufferingImmediately(): void
    {
        $gen = (function (): Generator {
            yield 2;
            for ($i = 0; $i < 1000; $i++) {
                yield 1;    // would fill the odd-buffer
            }
            yield 4;
        })();

        [$evenIter, $oddIter] = Partitioner::partition(
            $gen,
            fn(int $x): bool => 0 === $x % 2
        );

        // consume the first even
        $this->assertSame(2, $evenIter->current());
        $evenIter->next();

        // now drop the odd generator
        unset($oddIter);

        // immediately the weakref is dead → no more odds gets buffered
        // so we can pull the next even without OOM
        $this->assertSame(4, $evenIter->current());
    }

}
