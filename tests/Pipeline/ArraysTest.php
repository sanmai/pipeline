<?php
/*
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

/**
 * @covers \Pipeline\Standard
 * @covers \Pipeline\Principal
 */
class ArraysTest extends TestCase
{
    public function testInitialCallbackNotGenerator()
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            return PHP_INT_MAX;
        });

        $this->assertEquals([PHP_INT_MAX], iterator_to_array($pipeline));
    }

    public function testArrayToArray()
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            return 42;
        });

        $this->assertEquals([42], $pipeline->toArray());
    }

    public function testArrayFilter()
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            return false;
        })->filter()->filter();

        $this->assertEquals([], $pipeline->toArray());
    }

    public function testArrayReduce()
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            return 3;
        });

        $this->assertSame(3, $pipeline->reduce());
    }
}
