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

/**
 * @internal
 */
#[CoversClass(WindowBuffer::class)]
final class WindowBufferTest extends TestCase
{
    public function testEmptyBufferHasZeroCount(): void
    {
        $buffer = new WindowBuffer();

        $this->assertCount(0, $buffer);
    }
    public function testKeyAtReturnsCorrectKey(): void
    {
        $buffer = new WindowBuffer();
        $iterator = new ArrayIterator(['a' => 1, 'b' => 2, 'c' => 3]);

        $buffer->append($iterator);
        $this->assertCount(1, $buffer);

        $iterator->next();
        $buffer->append($iterator);
        $this->assertCount(2, $buffer);

        $iterator->next();
        $buffer->append($iterator);
        $this->assertCount(3, $buffer);

        $this->assertSame(1, $buffer->valueAt(0));
        $this->assertSame('a', $buffer->keyAt(0));

        $this->assertSame(2, $buffer->valueAt(1));
        $this->assertSame('b', $buffer->keyAt(1));

        $this->assertSame(3, $buffer->valueAt(2));
        $this->assertSame('c', $buffer->keyAt(2));

        $buffer->shift();
        $this->assertCount(2, $buffer);

        // Position 0 now refers to what was position 1
        $this->assertSame('b', $buffer->keyAt(0));
        $this->assertSame(2, $buffer->valueAt(0));
    }

    public function testMultipleShifts(): void
    {
        $buffer = new WindowBuffer();
        $iterator = new ArrayIterator([10, 20, 30, 40, 50]);

        foreach ($iterator as $_ => $__) {
            $buffer->append($iterator);
        }

        $this->assertCount(5, $buffer);

        $buffer->shift();
        $buffer->shift();

        $this->assertCount(3, $buffer);
        $this->assertSame(30, $buffer->valueAt(0));
        $this->assertSame(40, $buffer->valueAt(1));
        $this->assertSame(50, $buffer->valueAt(2));
    }
    public function testShiftThenAppend(): void
    {
        $buffer = new WindowBuffer();
        $iterator = new ArrayIterator(['x' => 100, 'y' => 200]);

        $buffer->append($iterator);
        $iterator->next();
        $buffer->append($iterator);

        $buffer->shift();
        $this->assertCount(1, $buffer);
        $this->assertSame('y', $buffer->keyAt(0));

        // Append more after shifting
        $newIterator = new ArrayIterator(['z' => 300]);
        $buffer->append($newIterator);

        $this->assertCount(2, $buffer);
        $this->assertSame('y', $buffer->keyAt(0));
        $this->assertSame('z', $buffer->keyAt(1));
    }
}
