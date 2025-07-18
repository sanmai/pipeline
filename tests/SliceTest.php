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
use Closure;
use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Pipeline\Standard;
use RuntimeException;

use function array_merge;
use function array_slice;
use function array_values;
use function Pipeline\fromArray;
use function Pipeline\map;
use function Pipeline\take;
use function range;

use const PHP_INT_MAX;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class SliceTest extends TestCase
{
    public static function provideCallback(): iterable
    {
        $array = [1, 2, 3, 4, 5, 6];

        yield Generator::class => [
            static function () use ($array) {
                return map(static function () use ($array) {
                    yield from $array;
                });
            },
        ];

        yield 'array' => [
            static function () use ($array) {
                return take($array);
            },
        ];

        yield ArrayIterator::class => [
            static function () use ($array) {
                return take(new ArrayIterator($array));
            },
        ];
    }

    /**
     * @dataProvider provideCallback
     *
     * @param Closure():Standard $example
     */
    public function testSliceExample(Closure $example): void
    {
        $this->assertSame(
            [3, 4, 5],
            $example()->slice(2, 3)->toList()
        );

        $this->assertSame(
            [6],
            $example()->slice(5, 200)->toList()
        );

        $this->assertSame(
            [],
            $example()->slice(15, 200)->toList()
        );

        $this->assertSame(
            [5, 6],
            $example()->slice(-2)->toList()
        );

        $this->assertSame(
            [2, 3, 4],
            $example()->slice(-5, -2)->toList()
        );

        $this->assertSame(
            [1, 2, 3],
            $example()->slice(0, -3)->toList()
        );

        $this->assertSame(
            [1, 2, 3],
            $example()->slice(0, 3)->toList()
        );

        $this->assertSame(
            [2, 3, 4, 5],
            $example()->slice(1, -1)->toList()
        );
    }

    public function testSliceExampleWithKeys(): void
    {
        $example = static function () {
            return map(static function () {
                yield from ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6];
            });
        };

        $this->assertSame(
            ['c' => 3, 'd' => 4, 'e' => 5],
            $example()->slice(2, 3)->toAssoc()
        );

        $this->assertSame(
            ['f' => 6],
            $example()->slice(5, 200)->toAssoc()
        );

        $this->assertSame(
            [],
            $example()->slice(15, 200)->toAssoc()
        );

        $this->assertSame(
            ['e' => 5, 'f' => 6],
            $example()->slice(-2)->toAssoc()
        );

        $this->assertSame(
            ['b' => 2, 'c' => 3, 'd' => 4],
            $example()->slice(-5, -2)->toAssoc()
        );

        $this->assertSame(
            ['a' => 1, 'b' => 2, 'c' => 3],
            $example()->slice(0, -3)->toAssoc()
        );

        $this->assertSame(
            ['b' => 2, 'c' => 3, 'd' => 4, 'e' => 5],
            $example()->slice(1, -1)->toAssoc()
        );
    }

    public function testTakeOnlyAsMuch(): void
    {
        $values = fromArray([1, 2, 3, 4])
            ->stream()
            ->cast(function ($value) {
                $this->assertLessThan(3, $value);
                return $value;
            })
            ->slice(0, 2)
            ->toAssoc();

        $this->assertSame([1, 2], $values);
    }

    public function testSliceNil(): void
    {
        $pipeline = new Standard();

        $this->assertSame([], $pipeline->slice(0)->toList());
    }

    public static function specimens(): iterable
    {
        yield [
            'expected' => [],
            'input' => [],
            'offset' => 0,
        ];

        yield [
            'expected' => [
                0 => 3,
                23 => 4,
            ],
            'input' => ['one' => 1, 'two' => 2, 3, 23 => 4],
            'offset' => 2,
            'length' => 2,
            'preserve_keys' => true,
        ];

        yield [
            'expected' => [
                3,
                4,
            ],
            'input' => ['one' => 1, 'two' => 2, 3, 23 => 4],
            'offset' => 2,
        ];

        yield [range(1, 3), range(1, 3), 0];

        yield [[], range(1, 3), 0, 0];

        yield [[3], range(1, 3), -1];

        yield [[2 => 3], range(1, 3), -1, null, true];

        $inputs = [
            [],
            [1, 2, 3, 4, 5, 6, 7, 8, 9],
            ['One', 'Two', 'Three', 'Four', 'Five'],
            [6, 'six', 7, 'seven', 8, 'eight', 9, 'nine'],
            ['a' => 'aaa', 'A' => 'AAA', 'c' => 'ccc', 'd' => 'ddd', 'e' => 'eee'],
            ['1' => 'one', '2' => 'two', '3' => 'three', '4' => 'four', '5' => 'five'],
            [1 => 'one', 2 => 'two', 3 => 7, 4 => 'four', 5 => 'five'],
            [12, 'name', 'age', '11'],
            [['oNe', 'tWo', 4], [10, 20, 30, 40, 50], []],
        ];

        $argsList = [
            [0],
            [-2],
            [1, 3],
            [1, 0],
            [0, 3],
            [0, 0],

            [0, -3],
            [-2, 3],
            [-2, 0],
            [-2, -3],

            [1, -3],
            [-3, -2],

            [2, -4],
            [-4, -1],
            [PHP_INT_MAX],
            [-PHP_INT_MAX],
            [PHP_INT_MAX, PHP_INT_MAX],
            [-PHP_INT_MAX, -PHP_INT_MAX],
            [PHP_INT_MAX, -PHP_INT_MAX],
            [-PHP_INT_MAX, PHP_INT_MAX],

            [0, PHP_INT_MAX],
            [0, -PHP_INT_MAX],
            [PHP_INT_MAX, 0],
            [-PHP_INT_MAX, 0],
        ];

        foreach ($inputs as $array) { // @phpstan-ignore-line
            foreach ($argsList as $args) {
                // First with keys:
                $args = $args + [null, null, true];

                yield array_merge(
                    [array_slice($array, ...$args), $array],
                    $args
                );

                // Now without keys:
                $args[2] = false;

                yield array_merge(
                    [array_values(array_slice($array, ...$args)), $array],
                    $args
                );
            }
        }
    }

    /**
     * @dataProvider specimens
     *
     * @covers \Pipeline\Standard::slice()
     */
    public function testSliceWithArrays(array $expected, array $input, int $offset, ?int $length = null, bool $preserve_keys = false): void
    {
        $pipeline = fromArray($input);

        $this->assertSame(
            $expected,
            $pipeline->slice($offset, $length)->toArray($preserve_keys)
        );
    }

    /**
     * @dataProvider specimens
     *
     * @covers \Pipeline\Standard::slice()
     */
    public function testSliceWithIterables(array $expected, array $input, int $offset, ?int $length = null, bool $preserve_keys = false): void
    {
        $pipeline = map(static function () use ($input) {
            yield from $input;
        });

        try {
            $this->assertSame(
                $expected,
                $pipeline->slice($offset, $length)->toArray($preserve_keys)
            );
        } catch (InvalidArgumentException $e) {
            if ('Not implemented yet' === $e->getMessage()) {
                $this->markTestIncomplete();
            }
        }
    }

    public function testNoopZeroOffset(): void
    {
        $pipeline = map(function () {
            throw new RuntimeException();
            yield;
        });

        try {
            $pipeline->slice(0)->toList();
        } catch (RuntimeException $e) {
            // We must not have any static methods called.
            $this->assertStringNotContainsString('Standard::', (string) $e);
        }
    }

    /**
     * @see https://en.wikipedia.org/wiki/Fibonacci_sequence
     */
    public function testFibonacci(): void
    {
        // Fibonacci numbers generator
        $fibonacci = map(function () {
            yield 0;

            $prev = 0;
            $current = 1;

            while (true) {
                yield $current;
                $next = $prev + $current;
                $prev = $current;
                $current = $next;
            }
        });

        $this->assertSame([
            0, 1, 1, 2, 3, 5, 8, 13, 21, 34, 55, 89, 144, 233, 377, 610, 987, 1597, 2584, 4181,
        ], $fibonacci->slice(0, 20)->toList());
    }
}
