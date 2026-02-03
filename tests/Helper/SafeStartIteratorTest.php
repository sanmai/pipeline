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
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pipeline\Helper\SafeStartIterator;

/**
 * @internal
 */
#[CoversClass(SafeStartIterator::class)]
final class SafeStartIteratorTest extends TestCase
{
    public function testEmptyIterator(): void
    {
        $safe = new SafeStartIterator(new EmptyIterator());

        $this->assertFalse($safe->valid(), 'Empty iterator should not be valid');
    }

    public function testUnstartedIterator(): void
    {
        $safe = new SafeStartIterator(new ArrayIterator(['a' => 1, 'b' => 2, 'c' => 3]));

        $result = [];
        foreach ($safe as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $result);
    }

    public function testStartedGeneratorCapturesCurrent(): void
    {
        $generator = (static function (): Generator {
            yield 'first' => 100;
            yield 'second' => 200;
        })();

        // Advance generator to first element
        $generator->current();

        $safe = new SafeStartIterator($generator);

        $result = [];
        foreach ($safe as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertSame(['first' => 100, 'second' => 200], $result);
    }

    public function testRewindOnlyOnce(): void
    {
        $rewindCount = 0;
        $generator = (static function () use (&$rewindCount): Generator {
            ++$rewindCount;
            yield 1;
            yield 2;
        })();

        $safe = new SafeStartIterator($generator);

        // First access
        $safe->rewind();
        $this->assertSame(1, $rewindCount, 'First rewind should trigger generator');

        // Second rewind should be no-op
        $safe->rewind();
        $this->assertSame(1, $rewindCount, 'Second rewind should be ignored');

        // Iterate
        $result = [];
        foreach ($safe as $value) {
            $result[] = $value;
        }
        $this->assertSame([1, 2], $result);
    }

    public function testValidAutoStarts(): void
    {
        $safe = new SafeStartIterator(new ArrayIterator([1, 2, 3]));

        // Calling valid() without rewind() should auto-start
        $this->assertTrue($safe->valid(), 'valid() should auto-start the iterator');
        $this->assertSame(1, $safe->current(), 'current() should return first element');
    }

    public function testNextDelegates(): void
    {
        $safe = new SafeStartIterator(new ArrayIterator([1, 2, 3]));

        $safe->rewind();
        $this->assertSame(1, $safe->current());

        $safe->next();
        $this->assertSame(2, $safe->current());

        $safe->next();
        $this->assertSame(3, $safe->current());

        $safe->next();
        $this->assertFalse($safe->valid());
    }

    public function testDoesNotRewindAlreadyStartedIterator(): void
    {
        $inner = new ArrayIterator([1, 2, 3]);
        $inner->next(); // Advance to second element

        $safe = new SafeStartIterator($inner);

        // Should not rewind - inner is already valid
        $safe->rewind();
        $this->assertSame(2, $safe->current(), 'Should preserve position of already-started iterator');
    }

    public function testRewindsUnstartedIteratorOnFirstAccess(): void
    {
        $rewindCalled = false;
        $generator = (static function () use (&$rewindCalled): Generator {
            $rewindCalled = true;
            yield 'a';
            yield 'b';
        })();

        $safe = new SafeStartIterator($generator);

        // Before any access, rewind should not have been called
        $this->assertFalse($rewindCalled, 'Generator should not be started before access');

        // Accessing valid() should trigger rewind for unstarted iterators
        $this->assertTrue($safe->valid(), 'Should be valid after auto-start');
        $this->assertTrue($rewindCalled, 'Generator should be started after valid() call');
        $this->assertSame('a', $safe->current(), 'Should be at first element');
    }

    public function testValidAutoStartOnlyCalledOnce(): void
    {
        $rewindCount = 0;
        $generator = (static function () use (&$rewindCount): Generator {
            ++$rewindCount;
            yield 1;
        })();

        $safe = new SafeStartIterator($generator);

        // Call valid() multiple times
        $safe->valid();
        $safe->valid();
        $safe->valid();

        $this->assertSame(1, $rewindCount, 'Rewind should only be called once even with multiple valid() calls');
    }

    public function testRewindCallsInnerRewindWhenNotValid(): void
    {
        /** @var Iterator<int, int>&MockObject $inner */
        $inner = $this->createMock(Iterator::class);

        // Inner is not valid (not started)
        $inner->method('valid')->willReturn(false);

        // rewind() MUST be called on inner
        $inner->expects($this->once())->method('rewind');

        $safe = new SafeStartIterator($inner);
        $safe->rewind();
    }

    public function testRewindSkipsInnerRewindWhenAlreadyValid(): void
    {
        /** @var Iterator<int, int>&MockObject $inner */
        $inner = $this->createMock(Iterator::class);

        // Inner is already valid (started)
        $inner->method('valid')->willReturn(true);

        // rewind() must NOT be called
        $inner->expects($this->never())->method('rewind');

        $safe = new SafeStartIterator($inner);
        $safe->rewind();
    }

    public function testValidTriggersRewindForUnstartedIterator(): void
    {
        /** @var Iterator<int, int>&MockObject $inner */
        $inner = $this->createMock(Iterator::class);

        $started = false;
        $inner->method('valid')->willReturnCallback(static function () use (&$started): bool {
            return $started;
        });

        // rewind() should be called exactly once when valid() is called on unstarted
        $inner->expects($this->once())->method('rewind')->willReturnCallback(static function () use (&$started): void {
            $started = true;
        });

        $safe = new SafeStartIterator($inner);

        // First valid() call should trigger rewind
        $this->assertTrue($safe->valid(), 'Should be valid after rewind');

        // Second valid() call should NOT trigger another rewind
        $this->assertTrue($safe->valid(), 'Should still be valid');
    }
}
