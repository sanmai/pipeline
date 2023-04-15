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
use function array_merge;
use function array_reverse;
use function call_user_func;
use function count;
use function max;
use function min;
use function Pipeline\take;
use function range;
use function shuffle;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class MinMaxTest extends TestCase
{
    private static function provideInputs(): iterable
    {
        yield [];
        yield [null];
        yield [M_E];
        yield [1, 2, 3];
        yield [1, 2, 3, null];
        yield [-1, -2, -3];
        yield [-1, -2, -3, null];
        yield [1, null];
        yield [-1, null];
        yield [-1, 0, 1];
        yield ['a', 'b', 'c'];
        yield [2, 1, 2];
        yield [-2, 1, 2];
        yield [2.1, 2.11, 2.09];
        yield ['', 'f', 'c'];
        yield [false, true, false];
        yield [true, false, true];
        yield [1, true, false, true];
        yield [0, true, false, true];
        yield [0, 1, [2, 3]];
        yield [2147483645, 2147483646];
        yield [2147483647, 2147483648];
        yield [2147483646, 2147483648];
        yield [-2147483647, -2147483646];
        yield [-2147483648, -2147483647];
        yield [-2147483649, -2147483647];
        yield range(-100, 100);
        yield range(-10, 10, 0.1);
    }

    private static function provideRandomizedInputs(): iterable
    {
        foreach (self::provideInputs() as $input) {
            yield $input;

            if ([] === $input) {
                continue;
            }

            yield array_reverse($input);

            if (count($input) <= 2) {
                continue;
            }

            shuffle($input);

            yield $input;

            yield array_merge($input, $input, $input);
        }
    }

    public static function provideMinInputs(): iterable
    {
        foreach (self::provideRandomizedInputs() as $input) {
            $expected = [] === $input ? null : min($input);

            yield [$expected, $input];

            yield [$expected, new ArrayIterator($input)];

            yield [$expected, new IteratorIterator(new ArrayIterator($input))];

            yield [$expected, call_user_func(function () use ($input) {
                yield from $input;
            })];

            yield [$expected, call_user_func(function () use ($input) {
                foreach ($input as $value) {
                    yield 0 => $value;
                }
            })];
        }
    }

    /**
     * @dataProvider provideMinInputs
     *
     * @param mixed $expected
     */
    public function testMin($expected, iterable $input): void
    {
        $this->assertSame($expected, take($input)->min());
    }

    public static function provideMaxInputs(): iterable
    {
        foreach (self::provideRandomizedInputs() as $input) {
            $expected = [] === $input ? null : max($input);

            yield [$expected, $input];

            yield [$expected, new ArrayIterator($input)];

            yield [$expected, new IteratorIterator(new ArrayIterator($input))];

            yield [$expected, call_user_func(function () use ($input) {
                yield from $input;
            })];

            yield [$expected, call_user_func(function () use ($input) {
                foreach ($input as $value) {
                    yield 0 => $value;
                }
            })];
        }
    }

    /**
     * @dataProvider provideMaxInputs
     *
     * @param mixed $expected
     */
    public function testMax($expected, iterable $input): void
    {
        $this->assertSame($expected, take($input)->max());
    }

    public function testNonPrimedMin(): void
    {
        $this->assertNull((new Standard())->min());
    }

    public function testNonPrimedMax(): void
    {
        $this->assertNull((new Standard())->max());
    }
}
