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

use PHPUnit\Framework\TestCase;
use Pipeline\Standard;

use function Pipeline\fromArray;
use function Pipeline\map;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\CoversClass(Standard::class)]
final class ValuesTest extends TestCase
{
    public function testValuesArray(): void
    {
        $values = fromArray(['a' => 1, 'b' => 2, 'c' => 3])
            ->values()
            ->toArray(preserve_keys: true);
        $this->assertSame([1, 2, 3], $values);
    }

    public function testValuesIterator(): void
    {
        $values = map(static function () {
            yield 5 => 'a';
            yield 6 => 'b';
            yield 5 => 'c';
            yield 6 => 'd';
        })->values()->toList();

        $this->assertSame(['a', 'b', 'c', 'd'], $values);
    }

    public function testValuesIteratorPreserveKeys(): void
    {
        $values = map(static function () {
            yield 'x';
            yield 'y';
        })->map(static function ($value) {
            yield 'c' => $value;
            yield 'd' => $value;
        })->values()->toArray(preserve_keys: true);

        $this->assertSame(['x', 'x', 'y', 'y'], $values);
    }

    public function testNonPrimedFlip(): void
    {
        $this->assertSame([], (new Standard())->values()->toList());
    }
}
