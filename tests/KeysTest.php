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

use function Pipeline\fromArray;
use function Pipeline\map;

use Pipeline\Standard;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class KeysTest extends TestCase
{
    public function testKeysArray(): void
    {
        $keys = fromArray(['a' => 1, 'b' => 2, 'c' => 3])
            ->keys()
            ->toAssoc();
        $this->assertSame(['a', 'b', 'c'], $keys);
    }

    public function testKeysIterator(): void
    {
        $keys = map(static function () {
            yield 5 => 'a';
            yield 6 => 'b';
            yield 5 => 'c';
            yield 6 => 'a';
        })->keys()->toList();

        $this->assertSame([5, 6, 5, 6], $keys);
    }

    public function testKeysIteratorPreserveKeys(): void
    {
        $keys = map(static function () {
            yield 'x';
            yield 'y';
        })->map(static function ($value) {
            yield 'c' => $value;
            yield 'd' => $value;
        })->keys()->toArray(preserve_keys: true);

        $this->assertSame(['c', 'd', 'c', 'd'], $keys);
    }

    public function testNonPrimed(): void
    {
        $this->assertSame([], (new Standard())->keys()->toList());
    }
}
