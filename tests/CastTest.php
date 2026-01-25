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

use Generator;

use const M_PI;

use PHPUnit\Framework\TestCase;

use function Pipeline\map;

use Pipeline\Standard;

use function Pipeline\take;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class CastTest extends TestCase
{
    public function testCastGenerator(): void
    {
        $result = [];

        // @phpstan-ignore-next-line
        foreach (take([1, 2, 3])->cast(function (int $i) {
            yield $i;
        }) as $generator) {
            $this->assertInstanceOf(Generator::class, $generator);
            foreach ($generator as $item) {
                $result[] = $item;
            }
        }

        $this->assertSame([1, 2, 3], $result);
    }

    public function testCastNothing(): void
    {
        $this->assertSame([1, 2, 3], take([1, 2, 3])->cast()->toList());
    }

    public function testCastIterator(): void
    {
        $this->assertSame([2, 4, 6], map(function () {
            yield 1;
            yield 2;
            yield 3;
        })->cast(function (int $a) {
            return $a * 2;
        })->toList());
    }

    public function testCastSeedValue(): void
    {
        $pipeline = new Standard();

        $this->assertSame([M_PI], $pipeline->cast(function () {
            return M_PI;
        })->toList());
    }

    public function testCastUnpack(): void
    {
        $pipeline = take([1, 2, 3]);

        $pipeline->cast(function (int $i) {
            yield $i;
            yield $i * $i;
        });

        $pipeline->unpack(function (int $a, int $b) {
            return $b - $a;
        });

        $this->assertSame([0, 2, 6], $pipeline->toList());
    }

    public function testCastPreservesKeys(): void
    {
        $this->assertSame([
            'a' => 2,
            'b' => 6,
        ], map(function () {
            yield 'a' => 1;
            yield 'b' => 2;
            yield 'b' => 3;
        })->cast(function (int $a) {
            return $a * 2;
        })->toAssoc());
    }
}
