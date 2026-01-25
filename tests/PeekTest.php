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

use function iterator_to_array;
use function Pipeline\map;
use function Pipeline\take;
use function range;

use Tests\Pipeline\Scenarios\PeekScenario;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class PeekTest extends TestCase
{
    /**
     * @return iterable<PeekScenario>
     */
    public static function providePeekData(): iterable
    {
        yield 'empty array' => new PeekScenario();
        yield 'empty array, count 3' => new PeekScenario(count: 3, expected_remains: []);
        yield 'zero count' => new PeekScenario(count: 0, input: [1, 2, 3], expected_peeked: [], expected_remains: [1, 2, 3]);

        yield 'simple peek' => new PeekScenario(count: 3, input: [1, 2, 3, 4, 5], expected_peeked: [1, 2, 3], expected_remains: [3 => 4, 5]);

        yield 'preserve keys' => new PeekScenario(count: 2, input: ['a' => 1, 'b' => 2, 'c' => 3], expected_peeked: ['a' => 1, 'b' => 2], expected_remains: ['c' => 3]);

        yield 'peek more than available' => new PeekScenario(count: 10, input: [1, 2, 3], expected_peeked: [1, 2, 3], expected_remains: []);
        yield 'consume all' => new PeekScenario(count: 3, input: [1, 2, 3], expected_peeked: [1, 2, 3], expected_remains: []);
    }

    /**
     * @return iterable<PeekScenario>
     */
    public static function providePeekIterables(): iterable
    {
        foreach (self::providePeekData() as $name => $item) {
            foreach (self::wrapArray($item->input) as $label => $iterator) {
                yield "{$label}({$name})" => [$item->withInput($iterator)];
            }
        }
    }

    /**
     * @dataProvider providePeekIterables
     */
    public function testPeekWithProvider(PeekScenario $item): void
    {
        $pipeline = take($item->input);

        $peeked = $pipeline->peek($item->count);

        $this->assertSame(
            take($item->expected_peeked)->tuples()->toList(),
            take($peeked)->tuples()->toList(),
        );

        if (null === $item->expected_remains) {
            return;
        }

        $this->assertSame(
            take($item->expected_remains)->tuples()->toList(),
            take($pipeline)->tuples()->toList(),
        );
    }

    public function testPeekBasic(): void
    {
        $pipeline = take([1, 2, 3, 4, 5]);
        $peeked = iterator_to_array($pipeline->peek(3));

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([4, 5], $pipeline->toList());
    }

    public function testPeekPreservesKeys(): void
    {
        $pipeline = take(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        $peeked = iterator_to_array($pipeline->peek(2));

        $this->assertSame(['a' => 1, 'b' => 2], $peeked);
        $this->assertSame(['c' => 3, 'd' => 4], $pipeline->toAssoc());
    }

    public function testPeekWithDuplicateKeys(): void
    {
        $generator = map(static function () {
            yield 'a' => 1;
            yield 'a' => 2;
            yield 'b' => 3;
        });

        $pipeline = take($generator);
        $peeked = $pipeline->peek(2);

        $this->assertSame(
            [['a', 1], ['a', 2]],
            take($peeked)->tuples()->toList(),
        );

        $this->assertSame(['b' => 3], $pipeline->toAssoc());
    }

    public function testPeekFromGenerator(): void
    {
        $pipeline = take(self::xrange(1, 5));
        $peeked = iterator_to_array($pipeline->peek(3), false);

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([4, 5], $pipeline->toList());
    }

    public function testPeekMoreThanAvailable(): void
    {
        $pipeline = take([1, 2, 3]);
        $peeked = iterator_to_array($pipeline->peek(10), false);

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([], $pipeline->toList());
    }

    public function testPeekFromEmptyPipeline(): void
    {
        $pipeline = take([]);
        $peeked = iterator_to_array($pipeline->peek(5), false);

        $this->assertSame([], $peeked);
        $this->assertSame([], $pipeline->toList());
    }

    public function testPeekWithZeroCount(): void
    {
        $pipeline = take([1, 2, 3]);
        $peeked = iterator_to_array($pipeline->peek(0), false);

        $this->assertSame([], $peeked);
        $this->assertSame([1, 2, 3], $pipeline->toList());
    }

    public function testPeekWithNegativeCount(): void
    {
        $pipeline = take([1, 2, 3]);
        $peeked = iterator_to_array($pipeline->peek(-5), false);

        $this->assertSame([], $peeked);
        $this->assertSame([1, 2, 3], $pipeline->toList());
    }

    public function testPeekNonDestructiveWithPrepend(): void
    {
        $pipeline = take([1, 2, 3, 4, 5]);
        $peeked = iterator_to_array($pipeline->peek(3), true);

        // Restore items manually with prepend()
        $pipeline->prepend($peeked);

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([1, 2, 3, 4, 5], $pipeline->toList());
    }

    /** @dataProvider provideOneToFive */
    public function testMultipleSequentialPeeks(iterable $input): void
    {
        $pipeline = take($input);

        $first = iterator_to_array($pipeline->peek(2), false);
        $this->assertSame([1, 2], $first);

        $second = iterator_to_array($pipeline->peek(2), false);
        $this->assertSame([3, 4], $second);

        $this->assertSame([5], $pipeline->toList());
    }

    public static function provideOneToFive(): iterable
    {
        yield [range(1, 5)];
        yield [self::xrange(1, 5)];
    }

    /** @dataProvider provideOneToFive */
    public function testMultipleSequentialPeeksOutOfOrder(iterable $input): void
    {
        $pipeline = take($input);

        $first = $pipeline->peek(2);
        $second = $pipeline->peek(2);

        $this->assertSame([5], $pipeline->toList());

        $this->assertSame([3, 4], iterator_to_array($second, false));
        $this->assertSame([1, 2], iterator_to_array($first, false));
    }

    public function testPeekOneDefersCosts(): void
    {
        $pipeline = map(function () {
            yield 1;
            $this->fail('Should not be called');
        });

        $this->assertSame([1], iterator_to_array($pipeline->peek(1)));
    }

    /**
     * Test chaining cast() on peek result
     */
    public function testPeekReturnsFluentPipeline(): void
    {
        $pipeline = take([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        $peeked = $pipeline->peek(5)
            ->cast(fn($x) => $x * 2)
            ->toList();

        $this->assertSame([2, 4, 6, 8, 10], $peeked);
        $this->assertSame([6, 7, 8, 9, 10], $pipeline->toList());
    }

    private static function xrange(int $start, int $end): iterable
    {
        for ($i = $start; $i <= $end; $i++) {
            yield $i;
        }
    }
}
