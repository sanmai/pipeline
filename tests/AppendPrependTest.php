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
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pipeline\Standard;

use function count;
use function is_numeric;
use function key;
use function Pipeline\take;

/**
 *
 * @internal
 */
#[CoversClass(Standard::class)]
final class AppendPrependTest extends TestCase
{
    private static function generateIterableCombinations(array $arrays): iterable
    {
        yield $arrays;

        $iterableInput = $arrays;
        $iterableInput[1] = new ArrayIterator($iterableInput[1] ?? []);

        yield $iterableInput;

        $iterableSubjects = $arrays;

        for ($i = 2; $i < count($iterableSubjects); ++$i) {
            $iterableSubjects[$i] = new ArrayIterator($iterableSubjects[$i] ?? []);
        }

        yield $iterableSubjects;
    }

    public static function provideAppendArrays(): iterable
    {
        yield [[1, 2, 3, 4, 5], [1, 2, 3], [4, 5]];

        yield [[1, 2, 3, 4, 5], [1, 2, 3], [4], [5]];

        yield [[1, 2, 3, 4, 5], [1, 2], [3, 4], [5]];

        yield [[1, 2, 3, 4, 5], [], [1, 2, 3, 4], [5]];

        yield [[1, 2, 3, 4, 5], null, [1, 2, 3, 4], [5]];

        yield [[1, 2, 3, 4, 5], [], [1, 2, 3, 4, 5], [], null];

        yield [['a', 'b'], ['a'], ['discard' => 'b']];

        yield [['a' => 'a', 'bb' => 'b'], ['a' => 'a'], ['bb' => 'b']];
    }

    /**
     */
    #[DataProvider('provideAppendArrays')]
    public function testPush(array $expected, ?array $initialValue, ...$iterables): void
    {
        $pipeline = take($initialValue);

        foreach ($iterables as $iterable) {
            $pipeline->push(...$iterable ?? []);
        }

        $preserve_keys = !is_numeric(key($expected));
        $this->assertSame($expected, $pipeline->toArray($preserve_keys));
    }

    public static function provideAppend(): iterable
    {
        return take(self::provideAppendArrays())
            ->map(self::generateIterableCombinations(...));
    }

    /**
     */
    #[DataProvider('provideAppend')]
    public function testAppend(array $expected, ?iterable $initialValue, ...$iterables): void
    {
        $pipeline = take($initialValue);

        foreach ($iterables as $iterable) {
            $pipeline->append($iterable);
        }

        $preserve_keys = !is_numeric(key($expected));
        $this->assertSame($expected, $pipeline->toArray($preserve_keys));
    }

    public static function providePrependArrays(): iterable
    {
        yield [[1, 2, 3, 4, 5], [4, 5], [1, 2, 3]];

        yield [[1, 2, 3, 4, 5], [5], [4], [1, 2, 3]];

        yield [[1, 2, 3, 4, 5], [5], [3, 4], [1, 2]];

        yield [[1, 2, 3, 4, 5], [], [5], [1, 2, 3, 4]];

        yield [[1, 2, 3, 4, 5], null, [5], [1, 2, 3, 4]];

        yield [[1, 2, 3, 4, 5], [], [1, 2, 3, 4, 5], [], null];

        yield [['b', 'a'], ['a'], ['discard' => 'b']];

        yield [['bb' => 'b', 'a' => 'a'], ['a' => 'a'], ['bb' => 'b']];
    }

    /**
     */
    #[DataProvider('providePrependArrays')]
    public function testUnshift(array $expected, ?array $initialValue, ...$iterables): void
    {
        $pipeline = take($initialValue);

        foreach ($iterables as $iterable) {
            $pipeline->unshift(...$iterable ?? []);
        }

        $preserve_keys = !is_numeric(key($expected));
        $this->assertSame($expected, $pipeline->toArray($preserve_keys));
    }

    public static function providePrepend(): iterable
    {
        return take(self::providePrependArrays())
            ->map(self::generateIterableCombinations(...));
    }

    /**
     */
    #[DataProvider('providePrepend')]
    public function testPrepend(array $expected, ?iterable $initialValue, ...$iterables): void
    {
        $pipeline = take($initialValue);

        foreach ($iterables as $iterable) {
            $pipeline->prepend($iterable);
        }

        $preserve_keys = !is_numeric(key($expected));
        $this->assertSame($expected, $pipeline->toArray($preserve_keys));
    }
}
