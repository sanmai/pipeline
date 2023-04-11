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
use function array_flip;
use function array_reverse;
use function call_user_func;
use function chr;
use function count;
use function Pipeline\map;
use function Pipeline\take;
use function shuffle;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class FlipTest extends TestCase
{
    public function testFlipDiscardKeys(): void
    {
        $keys = map(static function () {
            yield 'a' => 1;
            yield 'b' => 2;
            yield 'c' => 2;
            yield 'd' => 3;
        })->flip()->toArray();

        $this->assertSame(['a', 'b', 'c', 'd'], $keys);
    }

    public function testFlipRespectKeys(): void
    {
        $keys = map(static function () {
            yield 'a' => 1;
            yield 'b' => 2;
            yield 'c' => 2;
            yield 'd' => 3;
        })->flip()->toArray(true);

        $this->assertSame([1 => 'a', 'c', 'd'], $keys);
    }

    private function provideInputs(): iterable
    {
        yield [];

        yield ['one' => 1, 'two' => 2, 3 => 1, 'index' => 1];
        yield ['key' => 1, 'two' => 'TWO', 'three' => 3];
        yield [
            'one',
            1 => 'int_key',
            -2 => 'negative_key',
            8.9 => 'float_key',
            012 => 'octal_key',
            0x34 => 'hex_key',
            'key' => 'string_key1',
            'two' => 'string_key2',
            '' => 'string_key3',
            ' ' => 'string_key4',
            'a'.chr(0).'b' => 'binary_key1',
        ];

        yield [1 => 'value', 2 => 'VALUE', 3 => 4];

        yield ['key' => 1, 'two' => 'TWO', 'three' => 3, 'key1' => 'FOUR'];

        yield ['key1' => 'value1', 'key2' => '2', 'key3' => 'value1'];
    }

    private function provideRandomizedInputs(): iterable
    {
        foreach ($this->provideInputs() as $input) {
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
        }
    }

    public function provideFlipInputs(): iterable
    {
        foreach ($this->provideRandomizedInputs() as $input) {
            $expected = array_flip($input);

            yield [$expected, $input];

            yield [$expected, new ArrayIterator($input)];

            yield [$expected, new IteratorIterator(new ArrayIterator($input))];

            yield [$expected, call_user_func(function () use ($input) {
                yield from $input;
            })];

            yield [$expected, call_user_func(function () use ($input) {
                foreach ($input as $key => $value) {
                    yield $key => $value;
                }
            })];
        }
    }

    /**
     * @dataProvider provideFlipInputs
     */
    public function testFlip(array $expected, iterable $input): void
    {
        $this->assertSame($expected, take($input)->flip()->toArray(true));
    }

    public function testNonPrimedFlip(): void
    {
        $this->assertSame([], (new Standard())->flip()->toArray());
    }
}
