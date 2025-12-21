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

use PHPUnit\Framework\TestCase;
use Pipeline\Helper\FixedLengthList;

/**
 * @covers \Pipeline\Helper\FixedLengthList
 *
 * @internal
 */
final class FixedLengthListTest extends TestCase
{
    public function testUnlimitedPush(): void
    {
        $list = new FixedLengthList();

        $this->assertFalse($list->push(1));
        $this->assertFalse($list->push(2));
        $this->assertFalse($list->push(3));

        $this->assertSame(3, $list->count());
    }

    public function testFixedLengthTrims(): void
    {
        $list = new FixedLengthList(2);

        $this->assertFalse($list->push(1));
        $this->assertFalse($list->push(2));
        $this->assertTrue($list->push(3)); // Shifted!

        $this->assertSame(2, $list->count());
        $this->assertSame(2, $list[0]);
        $this->assertSame(3, $list[1]);
    }

    public function testArrayAccess(): void
    {
        $list = new FixedLengthList();

        $list->push('a');
        $list->push('b');

        $this->assertTrue(isset($list[0]));
        $this->assertTrue(isset($list[1]));
        $this->assertFalse(isset($list[2]));

        $this->assertSame('a', $list[0]);
        $this->assertSame('b', $list[1]);

        $list[0] = 'x';
        $this->assertSame('x', $list[0]);

        unset($list[0]);
        $this->assertSame(1, $list->count());
    }
}
