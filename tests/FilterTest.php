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
use Iterator;
use IteratorIterator;
use NoRewindIterator;
use PHPUnit\Framework\TestCase;
use Pipeline\Standard;

use function iterator_to_array;
use function Pipeline\fromArray;
use function Pipeline\fromValues;
use function Pipeline\map;
use function range;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class FilterTest extends TestCase
{
    private const NON_STRICT_FALSE_VALUES = [
        0,
        0.0,
        '',
        '0',
        [],
    ];

    public function testStandardStringFunctions(): void
    {
        $pipeline = new Standard(new ArrayIterator([1, 2, 'foo', 'bar']));
        $pipeline->filter('is_int');

        $this->assertSame([1, 2], iterator_to_array($pipeline));
    }

    public function testStandardFunctions(): void
    {
        $pipeline = new Standard(new ArrayIterator([1, 2, 'foo', 'bar']));
        $pipeline->filter(is_int(...));

        $this->assertSame([1, 2], iterator_to_array($pipeline));
    }

    public function testFilterAnyFalseValueDefaultCallback(): void
    {
        $pipeline = map(function () {
            yield false;
            yield 0;
            yield 0.0;
            yield '';
            yield '0';
            yield [];
            yield null;
        });

        $pipeline->filter();

        $this->assertCount(0, $pipeline->toList());
    }

    public function testFilterAnyFalseValueCustomCallback(): void
    {
        $pipeline = map(function () {
            yield false;
            yield 0;
            yield 0.0;
            yield '';
            yield '0';
            yield [];
            yield null;
            yield 1;
        });

        $pipeline->filter('intval');

        $this->assertSame([1], $pipeline->toList());
    }

    public function testFilterStrictMode(): void
    {
        $pipeline = map(function () {
            yield false;
            yield null;

            yield from self::NON_STRICT_FALSE_VALUES;
        });

        $pipeline->filter(strict: true);

        $this->assertSame(self::NON_STRICT_FALSE_VALUES, $pipeline->toList());
    }

    public function testFilterStrictModeWithPredicate(): void
    {
        $pipeline = map(function () {
            yield false;
            yield null;

            yield from self::NON_STRICT_FALSE_VALUES;
        });

        $pipeline->filter(fn($value) => $value, strict: true);

        $this->assertSame(self::NON_STRICT_FALSE_VALUES, $pipeline->toList());
    }

    public function testFilterNonStrictMode(): void
    {
        $pipeline = fromValues(false, null, '');
        $pipeline->filter(strict: false);
        $this->assertCount(0, $pipeline);
    }

    public function testFilterUnprimed(): void
    {
        $pipeline = new Standard();
        $pipeline->filter()->unpack();

        $this->assertSame([], $pipeline->toList());
    }
}
