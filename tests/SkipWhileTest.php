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

use function Pipeline\fromValues;
use function Pipeline\map;

use Pipeline\Standard;

use function Pipeline\take;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class SkipWhileTest extends TestCase
{
    public function testSkipEmpty(): void
    {
        $pipeline = new Standard();

        $result = $pipeline
            ->skipWhile(fn($number) => 1 === $number)
            ->toList();

        $this->assertSame([], $result);
    }

    public function testSkipNever(): void
    {
        $result = take([2])
            ->skipWhile(fn($number) => 1 === $number)
            ->toList();

        $this->assertSame([2], $result);
    }

    public function testSkipWhileOnce(): void
    {
        $result = take([1, 1, 1, 2, 3, 4, 1, 2, 3])
            ->skipWhile(fn($number) => 1 === $number)
            ->toList();

        $this->assertSame([2, 3, 4, 1, 2, 3], $result);
    }

    public function testSkipWhileTwice()
    {
        $result = map(fn() => yield from [1, 1, 1, 2, 2, 3, 4, 5, 1, 2])
            ->skipWhile(fn($number) => 1 === $number)
            ->skipWhile(fn($number) => 2 === $number)
            ->filter(fn($number) => 1 === $number % 2)
            ->toList();

        $this->assertSame([3, 5, 1], $result);
    }

    public function testDefaultCallback(): void
    {
        $result = fromValues(1, '0', null, false)
            ->skipWhile(fn($number) => $number)
            ->toList();

        $this->assertSame(['0', null, false], $result);
    }
}
