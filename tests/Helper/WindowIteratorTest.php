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

use ArrayIterator;

use function count;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pipeline\Helper\WindowIterator;
use ReflectionProperty;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(WindowIterator::class)]
final class WindowIteratorTest extends TestCase
{
    public function testTrimLoopTerminatesCorrectly(): void
    {
        $callCount = 0;

        $mock = $this->getMockBuilder(WindowIterator::class)
            ->setConstructorArgs([new ArrayIterator([1, 2, 3, 4, 5]), 3])
            ->onlyMethods(['count'])
            ->getMock();

        $mock->method('count')
            ->willReturnCallback(function () use (&$callCount, $mock): int {
                if (++$callCount > 50) {
                    throw new RuntimeException('count() called >50 times - infinite loop');
                }

                $buffer = (new ReflectionProperty(WindowIterator::class, 'buffer'))->getValue($mock);

                return count($buffer);
            });

        // Consume all elements - triggers trim operations
        foreach ($mock as $_) {
        }

        // With 5 elements and maxSize 3, count() calls should be bounded
        $this->assertLessThan(50, $callCount);
    }
}
