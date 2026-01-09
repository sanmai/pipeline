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

use Pipeline\Standard;

use function Pipeline\take;
use function Pipeline\map;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class TuplesTest extends TestCase
{
    public static function provideArrays(): iterable
    {
        yield 'empty_array' => [[], []];
        yield 'basic_key_values' => [['a' => 1, 'b' => 2, 'c' => 3], [['a', 1], ['b', 2], ['c', 3]]];
        yield 'numeric_keys' => [[10, 20, 30], [[0, 10], [1, 20], [2, 30]]];

        yield 'mixed_keys_values' => [
            ['name' => 'Alice', 1 => 'Bob'],
            [['name', 'Alice'], [1, 'Bob']],
        ];

        yield 'null_values' => [['x' => null, 'y' => null], [['x', null], ['y', null]]];

        yield 'empty_string_key' => [['' => 'value'], [['', 'value']]];

        yield 'nested_arrays' => [['outer' => ['inner1' => 'value1', 'inner2' => 'value2']], [['outer', ['inner1' => 'value1', 'inner2' => 'value2']]]];
    }

    public static function provideIterables(): iterable
    {
        foreach (self::provideArrays() as $name => [$input, $expected]) {
            foreach (self::wrapArray($input) as $label => $iterator) {
                yield "{$label}({$name})" => [$iterator, $expected];
            }
        }

        yield 'generator' => [map(function () {
            yield '1' => 2;
            yield '2' => 3;
        }), [['1', 2], ['2', 3]]];
    }

    /**
     * @dataProvider provideIterables
     */
    public function testTuples(iterable $input, array $expected, bool $preserve_keys = false): void
    {
        $pipeline = take($input);

        $actual = $pipeline->tuples()->toArray($preserve_keys);

        $this->assertSame(
            $expected,
            $actual
        );
    }

    public function testNoop(): void
    {
        $pipeline = new Standard();

        $actual = $pipeline->tuples()->toList();

        $this->assertSame(
            [],
            $actual
        );
    }
}
