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

namespace Tests\Pipeline\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pipeline\Helper\Enums;

use function Pipeline\take;

use Tests\Pipeline\Fixtures\Direction;
use Tests\Pipeline\Fixtures\Suit;
use TypeError;

/**
 * @internal
 */
#[CoversClass(Enums::class)]
final class EnumsTest extends TestCase
{
    public function testValue(): void
    {
        $this->assertSame('H', Enums::value(Suit::Hearts));
    }

    public function testName(): void
    {
        $this->assertSame('Hearts', Enums::name(Suit::Hearts));
        $this->assertSame('Up', Enums::name(Direction::Up), 'A pure enum has a name');
    }

    public function testCastToValues(): void
    {
        $this->assertSame(
            ['H', 'S'],
            take(Suit::cases())->cast(Enums::value(...))->toList()
        );
    }

    public function testCastToNames(): void
    {
        $this->assertSame(
            ['Up', 'Down'],
            take(Direction::cases())->cast(Enums::name(...))->toList()
        );
    }

    public function testValueRejectsPureEnum(): void
    {
        $this->expectException(TypeError::class);

        take(Direction::cases())->cast(Enums::value(...));
    }
}
