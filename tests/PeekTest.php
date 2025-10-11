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
use Generator;
use IteratorIterator;
use PHPUnit\Framework\TestCase;
use Tests\Pipeline\Examples\PeekExample;

use function Pipeline\fromArray;
use function Pipeline\take;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class PeekTest extends TestCase
{
    /**
     * @return iterable<PeekExample>
     */
    public static function providePeekData(): iterable
    {
        yield 'empty array' => new PeekExample();
        yield 'empty array, count 3' => new PeekExample(count: 3);
        yield 'zero count' => new PeekExample(count: 0, input: [1, 2, 3], expected_peeked: []);

        yield 'simple peek' => new PeekExample(count: 3, input: [1, 2, 3, 4, 5], expected_peeked: [1, 2, 3]);
        yield 'simple consume' => new PeekExample(count: 3, consume: true, input: [1, 2, 3, 4, 5], expected_peeked: [1, 2, 3]);

        yield 'preserve keys' => new PeekExample(count: 2, preserve_keys: true, input: ['a' => 1, 'b' => 2, 'c' => 3], expected_peeked: ['a' => 1, 'b' => 2]);
        yield 'no preserve keys with string keys' => new PeekExample(count: 2, input: ['a' => 1, 'b' => 2, 'c' => 3], expected_peeked: [1, 2]);
        yield 'no preserve keys with duplicate string keys' => new PeekExample(count: 2, input: self::joinArrays(['a' => 1], ['a' => 2], ['a' => 3]), expected_peeked: [1, 2]);

        yield 'consume with preserve keys' => new PeekExample(count: 2, consume: true, preserve_keys: true, input: ['a' => 1, 'b' => 2, 'c' => 3], expected_peeked: ['a' => 1, 'b' => 2]);
        yield 'consume without preserve keys' => new PeekExample(count: 2, consume: true, input: ['a' => 1, 'b' => 2, 'c' => 3], expected_peeked: [1, 2]);

        yield 'no preserve keys with numeric keys' => new PeekExample(count: 2, input: [10, 20, 30, 40], expected_peeked: [10, 20]);

        yield 'peek more than available' => new PeekExample(count: 10, input: [1, 2, 3], expected_peeked: [1, 2, 3]);
        yield 'consume all' => new PeekExample(count: 10, consume: true, input: [1, 2, 3], expected_peeked: [1, 2, 3]);
    }

    /**
     * @return iterable<PeekExample>
     */
    public static function providePeekIterables(): iterable
    {
        foreach (self::providePeekData() as $name => $item) {
            // Original array
            yield $name . ' (array)' => [$item];

            // ArrayIterator
            yield $name . ' (ArrayIterator)' => [$item->withInput(new ArrayIterator($item->input))];

            // IteratorIterator
            yield $name . ' (IteratorIterator)' => [$item->withInput(new IteratorIterator(new ArrayIterator($item->input)))];

            // Pipeline fromArray->stream
            yield $name . ' (stream)' => [$item->withInput(take($item->input)->stream())];
        }
    }

    /**
     * @dataProvider providePeekIterables
     */
    public function testPeekWithProvider(PeekExample $item): void
    {
        $pipeline = take($item->input);

        $peeked = $pipeline->peek($item->count, $item->consume, $item->preserve_keys);

        $this->assertSame($item->expected_peeked, $peeked);
    }

    public function testPeekNonDestructiveFromArray(): void
    {
        $pipeline = take([1, 2, 3, 4, 5]);
        $peeked = $pipeline->peek(3);

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([1, 2, 3, 4, 5], $pipeline->toList());
    }

    public function testPeekDestructiveFromArray(): void
    {
        $pipeline = take([1, 2, 3, 4, 5]);
        $peeked = $pipeline->peek(3, consume: true);

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([4, 5], $pipeline->toList());
    }

    public function testPeekNonDestructiveFromGenerator(): void
    {
        $pipeline = take(self::xrange(1, 5));
        $peeked = $pipeline->peek(3);

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([1, 2, 3, 4, 5], $pipeline->toList());
    }

    public function testPeekDestructiveFromGenerator(): void
    {
        $pipeline = take(self::xrange(1, 5));
        $peeked = $pipeline->peek(3, consume: true);

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([4, 5], $pipeline->toList());
    }

    public function testPeekMoreThanAvailable(): void
    {
        $pipeline = take([1, 2, 3]);
        $peeked = $pipeline->peek(10);

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([1, 2, 3], $pipeline->toList());
    }

    public function testPeekFromEmptyPipeline(): void
    {
        $pipeline = take([]);
        $peeked = $pipeline->peek(5);

        $this->assertSame([], $peeked);
        $this->assertSame([], $pipeline->toList());
    }

    public function testPeekWithZeroCount(): void
    {
        $pipeline = take([1, 2, 3]);
        $peeked = $pipeline->peek(0);

        $this->assertSame([], $peeked);
        $this->assertSame([1, 2, 3], $pipeline->toList());
    }

    public function testPeekWithNegativeCount(): void
    {
        $pipeline = take([1, 2, 3]);
        $peeked = $pipeline->peek(-5);

        $this->assertSame([], $peeked);
        $this->assertSame([1, 2, 3], $pipeline->toList());
    }

    public function testPeekPreservesKeys(): void
    {
        $pipeline = take(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        $peeked = $pipeline->peek(2, preserve_keys: true);

        $this->assertSame(['a' => 1, 'b' => 2], $peeked);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4], $pipeline->toAssoc());
    }

    public function testPeekPreservesKeysWhenConsuming(): void
    {
        $pipeline = take(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        $peeked = $pipeline->peek(2, consume: true, preserve_keys: true);

        $this->assertSame(['a' => 1, 'b' => 2], $peeked);
        $this->assertSame(['c' => 3, 'd' => 4], $pipeline->toAssoc());
    }

    public function testPeekWithoutPreserveKeys(): void
    {
        $pipeline = take(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        $peeked = $pipeline->peek(2);

        $this->assertSame([1, 2], $peeked);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4], $pipeline->toAssoc());
    }

    public function testMultipleSequentialPeeksNonDestructive(): void
    {
        $pipeline = take([1, 2, 3, 4, 5]);

        $first = $pipeline->peek(2);
        $this->assertSame([1, 2], $first);

        $second = $pipeline->peek(3);
        $this->assertSame([1, 2, 3], $second);

        $this->assertSame([1, 2, 3, 4, 5], $pipeline->toList());
    }

    public function testMultipleSequentialPeeksDestructive(): void
    {
        $pipeline = take([1, 2, 3, 4, 5]);

        $first = $pipeline->peek(2, consume: true);
        $this->assertSame([1, 2], $first);

        $second = $pipeline->peek(2, consume: true, preserve_keys: true);
        $this->assertSame([2 => 3, 3 => 4], $second);

        $this->assertSame([5], $pipeline->toList());
    }

    public function testPeekMixedConsume(): void
    {
        $pipeline = take([1, 2, 3, 4, 5]);

        $first = $pipeline->peek(2);
        $this->assertSame([1, 2], $first);

        $second = $pipeline->peek(2, consume: true);
        $this->assertSame([1, 2], $second);

        $this->assertSame([3, 4, 5], $pipeline->toList());
    }

    public function testPeekDestructivePreservesKeysInRemainingPipeline(): void
    {
        $pipeline = take(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        $peeked = $pipeline->peek(2, consume: true, preserve_keys: true);

        $this->assertSame(['a' => 1, 'b' => 2], $peeked);
        $this->assertSame(['c' => 3, 'd' => 4], $pipeline->toAssoc());
    }

    public function testPeekConsumeMoreThanAvailableFromGenerator(): void
    {
        $pipeline = take(self::xrange(1, 3));
        $peeked = $pipeline->peek(10, consume: true);

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([], $pipeline->toList());
    }

    private static function xrange(int $start, int $end): iterable
    {
        for ($i = $start; $i <= $end; $i++) {
            yield $i;
        }
    }

    private static function joinArrays(...$args): Generator
    {
        foreach ($args as $arg) {
            yield from $arg;
        }
    }
}
