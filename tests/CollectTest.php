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

use function implode;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Pipeline\map;

use Pipeline\Standard;

use function Pipeline\take;

/**
 * @internal
 */
#[CoversMethod(Standard::class, 'collect')]
final class CollectTest extends TestCase
{
    public static function provideInputs(): iterable
    {
        yield 'array' => [['a' => 1, 'b' => 2, 'c' => 3]];
        yield 'iterator' => [new ArrayIterator(['a' => 1, 'b' => 2, 'c' => 3])];
    }

    #[DataProvider('provideInputs')]
    public function testCollectWithoutCallback(iterable $input): void
    {
        $this->assertSame([1, 2, 3], take($input)->collect());
    }

    #[DataProvider('provideInputs')]
    public function testCollectWithCallback(iterable $input): void
    {
        $this->assertSame('1,2,3', take($input)->collect(fn(array $values) => implode(',', $values)));
    }

    public function testCollectPassesListWithDuplicateKeys(): void
    {
        $pipeline = map(static function () {
            yield 'a' => 1;
            yield 'a' => 2;
        });

        $this->assertSame([1, 2], $pipeline->collect(fn(array $values) => $values), 'Callback must receive every value as a list');
    }

    public function testCollectEmpty(): void
    {
        $this->assertSame([], (new Standard())->collect());
        $this->assertSame('', (new Standard())->collect(fn(array $values) => implode(',', $values)));
    }
}
