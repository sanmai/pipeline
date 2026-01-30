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
use EmptyIterator;

use function iterator_count;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pipeline\Helper\WindowBuffer;
use Pipeline\Helper\WindowIterator;

/**
 * @internal
 */
#[CoversClass(WindowIterator::class)]
final class WindowIteratorTest extends TestCase
{
    public function testEmptyIteratorBehavior(): void
    {
        $window = new WindowIterator(new EmptyIterator(), 10);

        $this->assertFalse($window->valid());
        $this->assertNull($window->current());
        $this->assertNull($window->key());
    }

    public function testInitializationAndFetching(): void
    {
        $buffer = new WindowBuffer();
        $window = new WindowIterator(new ArrayIterator(['a' => 1, 'b' => 2, 'c' => 3]), 10, $buffer);

        $this->assertCount(0, $buffer, 'Buffer must be empty before valid() is called');

        $this->assertTrue($window->valid(), 'valid() must trigger initialization');
        $this->assertCount(1, $buffer, 'First element must now be in buffer');
        $this->assertSame('a', $window->key());
        $this->assertSame(1, $window->current());

        $window->next();
        $this->assertCount(2, $buffer, 'First next() must fetch second element');
        $this->assertSame('b', $window->key());
        $this->assertSame(2, $window->current());

        $window->next();
        $this->assertCount(3, $buffer, 'Second next() must fetch third element');
        $this->assertSame('c', $window->key());
        $this->assertSame(3, $window->current());
    }

    public function testRewindAndBufferReuse(): void
    {
        $buffer = new WindowBuffer();
        $window = new WindowIterator(new ArrayIterator([1, 2, 3]), 10, $buffer);

        $this->assertSame(3, iterator_count($window));

        $this->assertCount(3, $buffer, 'Buffer must have 3 elements after consuming all');

        $window->rewind();
        $this->assertSame(1, $window->current(), 'Rewind must reset to first element');

        $window->next();
        $this->assertCount(3, $buffer, 'next() must stay within buffer, not fetch');
        $this->assertSame(2, $window->current());
    }
}
