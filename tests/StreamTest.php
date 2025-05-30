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
use function Pipeline\fromArray;
use function Pipeline\map;
use function Pipeline\take;
use function shuffle;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class StreamTest extends TestCase
{
    public function testStreamArray(): void
    {
        $values = fromArray([1, 2, 3, 4, 5])
            ->stream()
            ->cast(function ($value) {
                $this->assertLessThan(5, $value);
                return $value;
            })
            ->slice(0, 2)
            ->toAssoc();

        $this->assertSame([1, 2], $values);
    }

    public function testStreamArrayIterator(): void
    {
        $values = take(new ArrayIterator([1, 2, 3, 4, 5]))
            ->stream()
            ->cast(function ($value) {
                $this->assertLessThan(5, $value);
                return $value;
            })
            ->slice(0, 2)
            ->toAssoc();

        $this->assertSame([1, 2], $values);
    }

    public function testStreamIterator(): void
    {
        $values = map(static function () {
            yield 2 => 'a';
            yield 4 => 'b';
            yield 7 => 'c';
            yield 1 => 'd';
        })->stream()->toAssoc();

        $this->assertSame([2 => 'a', 4 => 'b', 7 => 'c', 1 => 'd'], $values);
    }

    public function testNonPrimed(): void
    {
        $this->assertSame([], (new Standard())->stream()->toArray());
    }
}
