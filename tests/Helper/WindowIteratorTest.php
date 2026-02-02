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
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pipeline\Helper\WindowIterator;

/**
 * @internal
 */
#[CoversClass(WindowIterator::class)]
final class WindowIteratorTest extends TestCase
{
    public function testEmptyIterator(): void
    {
        $window = new WindowIterator(new EmptyIterator(), 10);

        $this->assertFalse($window->valid(), 'Empty iterator should not be valid');
        $this->assertNull($window->current(), 'Empty iterator current should be null');
        $this->assertNull($window->key(), 'Empty iterator key should be null');
        $this->assertSame(0, $window->count(), 'Empty iterator count should be 0');
    }

    public function testForwardIteration(): void
    {
        $window = new WindowIterator(new ArrayIterator(['a' => 1, 'b' => 2, 'c' => 3]), 10);

        $result = [];
        foreach ($window as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $result);
    }

    public function testRewindAndReplay(): void
    {
        $window = new WindowIterator(new ArrayIterator([1, 2, 3]), 10);

        // First pass
        $first = [];
        foreach ($window as $value) {
            $first[] = $value;
        }

        // Rewind and second pass
        $window->rewind();
        $second = [];
        foreach ($window as $value) {
            $second[] = $value;
        }

        $this->assertSame([1, 2, 3], $first);
        $this->assertSame([1, 2, 3], $second);
    }

    public function testWindowTrimsOldElements(): void
    {
        $window = new WindowIterator(new ArrayIterator([1, 2, 3, 4, 5]), 3);

        // Consume all
        foreach ($window as $_) {
        }

        // Buffer should only have last 3 elements
        $this->assertSame(3, $window->count(), 'Buffer should be trimmed to maxSize');

        // Rewind should give us elements 3, 4, 5
        $window->rewind();
        $result = [];
        foreach ($window as $value) {
            $result[] = $value;
        }

        $this->assertSame([3, 4, 5], $result, 'After trim, only last 3 elements should remain');
    }

    public function testStartedGeneratorCapturesCurrent(): void
    {
        $generator = (static function (): Generator {
            yield 'first' => 100;
            yield 'second' => 200;
        })();

        // Advance generator to first element
        $generator->current();

        $window = new WindowIterator($generator, 10);

        $result = [];
        foreach ($window as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertSame(['first' => 100, 'second' => 200], $result);
    }

    public function testForeachAlwaysRewinds(): void
    {
        $window = new WindowIterator(new ArrayIterator([1, 2, 3, 4, 5]), 10);

        // Partial iteration
        foreach ($window as $value) {
            if (3 === $value) {
                break;
            }
        }

        // foreach always rewinds - this is expected for window()
        $result = [];
        foreach ($window as $value) {
            $result[] = $value;
        }

        $this->assertSame([1, 2, 3, 4, 5], $result, 'foreach should rewind and replay all buffered elements');
    }
}
