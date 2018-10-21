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
use Pipeline\Principal;
use function Pipeline\fromArray;
use function Pipeline\take;

/**
 * @covers \Pipeline\Principal
 */
class EagerWithArraysTest extends TestCase
{
    public function testEagerArrayFilter()
    {
        $reflectionClass = new \ReflectionClass(Principal::class);
        $reflectionProperty = $reflectionClass->getProperty('pipeline');
        $reflectionProperty->setAccessible(true);

        foreach ([
            take([0, 0, 1, 2, 3]),
            fromArray([0, 0, 1, 2, 3]),
        ] as $pipeline) {
            $pipeline->filter();
            // At this point $pipeline contains exactly [1, 2, 3]

            $this->assertSame([2 => 1, 2, 3], $reflectionProperty->getValue($pipeline));

            $this->assertSame([1, 2, 3], $pipeline->toArray());

            // This does nothing more
            $this->assertSame([1, 2, 3], $pipeline->filter()->toArray());
        }
    }
}
