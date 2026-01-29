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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pipeline\Helper\WindowBuffer;
use Pipeline\Helper\WindowIterator;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(WindowIterator::class)]
#[CoversClass(WindowBuffer::class)]
final class WindowIteratorTest extends TestCase
{
    public function testTrimLoopTerminatesCorrectly(): void
    {
        $callCount = 0;

        $buffer = $this->getMockBuilder(WindowBuffer::class)
            ->onlyMethods(['count'])
            ->getMock();

        $buffer->method('count')
            ->willReturnCallback(function () use (&$callCount, $buffer): int {
                if (++$callCount > 50) {
                    throw new RuntimeException('count() called >50 times - infinite loop');
                }

                return parent::count();
            });

        $window = new WindowIterator(new ArrayIterator([1, 2, 3, 4, 5]), 3, $buffer);

        // Consume all elements - triggers trim operations
        foreach ($window as $_) {
        }

        // With 5 elements and maxSize 3, count() calls should be bounded
        $this->assertLessThan(50, $callCount);
    }
}
