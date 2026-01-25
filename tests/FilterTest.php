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

use PHPUnit\Framework\Attributes\DataProvider;
use Pipeline\Standard;

/**
 * @covers \Pipeline\Standard::filter
 *
 * @internal
 */
final class FilterTest extends TestCase
{
    public function testFilterIsNotStrictByDefault(): void
    {
        $pipeline = $this->getMockBuilder(Standard::class)
            ->setConstructorArgs([[1]])
            ->onlyMethods(['select'])
            ->getMock();

        $pipeline->expects($this->once())
            ->method('select')
            ->with(null, false)
            ->willReturn($pipeline);

        $pipeline->filter();
    }

    public static function provideFilterIsEquivalentToSelect(): iterable
    {
        yield [null, false];
        yield [null, true];
        yield [fn($value) => true, false];
        yield [fn($value) => true, true];
    }

    #[DataProvider('provideFilterIsEquivalentToSelect')]
    public function testFilterIsEquivalentToSelect(?callable $func, bool $strict): void
    {
        $pipeline = $this->getMockBuilder(Standard::class)
            ->setConstructorArgs([[1]])
            ->onlyMethods(['select'])
            ->getMock();

        $pipeline->expects($this->once())
            ->method('select')
            ->with($func, $strict)
            ->willReturn($pipeline);

        $pipeline->filter($func, $strict);
    }
}
