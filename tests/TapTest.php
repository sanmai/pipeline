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

namespace Tests\Pipeline;

use ArgumentCountError;
use ArrayIterator;
use LogicException;
use PHPUnit\Framework\TestCase;
use Pipeline\Standard;
use SplQueue;
use Tests\Pipeline\Fixtures\CallableThrower;

use function Pipeline\fromArray;
use function Pipeline\map;
use function Pipeline\take;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class TapTest extends TestCase
{
    private array $sideEffects;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sideEffects = [];
    }

    private function recordValue($value): void
    {
        $this->sideEffects[] = $value;
    }

    private function recordKeyValue($value, $key): void
    {
        $this->sideEffects[$key] = $value;
    }

    public function testUninitialized(): void
    {
        $pipeline = new Standard();

        $result = $pipeline->tap($this->recordValue(...));

        $this->assertSame([], $result->toList());
        $this->assertSame([], $this->sideEffects);
    }

    public function testEmpty(): void
    {
        $pipeline = take([]);

        $result = $pipeline->tap($this->recordValue(...));

        $this->assertSame([], $result->toList());
        $this->assertSame([], $this->sideEffects);
    }

    public function testEmptyGenerator(): void
    {
        $pipeline = map(static fn() => yield from []);

        $result = $pipeline->tap($this->recordValue(...));

        $this->assertSame([], $result->toList());
        $this->assertSame([], $this->sideEffects);
    }

    public function testBasicTap(): void
    {
        $pipeline = take([1, 2, 3, 4]);

        $result = $pipeline->tap($this->recordValue(...))->toList();

        $this->assertSame([1, 2, 3, 4], $result);
        $this->assertSame([1, 2, 3, 4], $this->sideEffects);
    }

    public function testTapWithKeys(): void
    {
        $pipeline = take(['a' => 1, 'b' => 2, 'c' => 3]);

        $result = $pipeline->tap(fn(int $value, string $key) => $this->recordKeyValue($value, $key))->toAssoc();

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $result);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $this->sideEffects);
    }

    public function testTapDoesNotChangeValues(): void
    {
        $pipeline = take([1, 2, 3]);

        $result = $pipeline
            ->tap(fn($value) => $this->sideEffects[] = $value * 10)
            ->map(fn($value) => $value * 2)
            ->toList();

        $this->assertSame([2, 4, 6], $result);
        $this->assertSame([10, 20, 30], $this->sideEffects);
    }

    public function testTapChaining(): void
    {
        $firstTap = [];
        $secondTap = [];

        $result = take([1, 2, 3])
            ->tap(function ($value) use (&$firstTap): void {
                $firstTap[] = $value * 2;
            })
            ->tap(function ($value) use (&$secondTap): void {
                $secondTap[] = $value * 3;
            })
            ->toList();

        $this->assertSame([1, 2, 3], $result);
        $this->assertSame([2, 4, 6], $firstTap);
        $this->assertSame([3, 6, 9], $secondTap);
    }

    public function testTapWithArray(): void
    {
        $pipeline = fromArray([10, 20, 30]);

        $result = $pipeline->tap($this->recordValue(...))->toList();

        $this->assertSame([10, 20, 30], $result);
        $this->assertSame([10, 20, 30], $this->sideEffects);
    }

    public function testTapWithIterator(): void
    {
        $pipeline = take(new ArrayIterator([5, 15, 25]));

        $result = $pipeline->tap($this->recordValue(...))->toList();

        $this->assertSame([5, 15, 25], $result);
        $this->assertSame([5, 15, 25], $this->sideEffects);
    }


    public function testLaziness(): void
    {
        $pipeline = take([1, 2, 3, 4, 5]);

        $result = $pipeline
            ->tap($this->recordValue(...))
            ->filter(fn($value) => $value <= 3);

        $this->assertSame([], $this->sideEffects, 'Tap should not execute until consumed');

        $final = $result->toList();

        $this->assertSame([1, 2, 3], $final);
        $this->assertSame([1, 2, 3, 4, 5], $this->sideEffects);
    }

    public function testTapWithException(): void
    {
        $pipeline = take([1, 2, 3]);

        $this->expectException(LogicException::class);

        $pipeline
            ->tap(function ($value): void {
                $this->recordValue($value);
                if (2 === $value) {
                    throw new LogicException('Test exception');
                }
            })
            ->toList();
    }

    public function testTapStrictArity(): void
    {
        $queue = new SplQueue();
        $pipeline = fromArray([1, 2, 3]);

        $result = $pipeline->tap($queue->enqueue(...))->toList();

        $this->assertSame([1, 2, 3], $result);
        $this->assertSame([1, 2, 3], take($queue)->toList());
    }

    public function testTapVariadicInternal(): void
    {
        $this->expectOutputString("123");

        $pipeline = fromArray(['1', '2', '3']);
        $pipeline->tap(printf(...))->toList();
    }

    public function testTapArgumentCountError(): void
    {
        $pipeline = fromArray(['1', '2', '3']);

        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage('Too few arguments');

        $pipeline->tap(static function ($a, $b, $c): void {})->toList();
    }

    public function testTapCallableReassigned(): void
    {
        $callback = new CallableThrower();

        $pipeline = fromArray(['1', '2', '3']);
        $result = $pipeline->tap($callback)->toList();

        $this->assertSame(['1', '2', '3'], $result);
        $this->assertSame(4, $callback->callCount, 'Expected 1 initial call that throws + 3 successful calls after wrapping');

        $this->assertSame([
            ['1', 0],
            ['1'],
            ['2'],
            ['3'],
        ], $callback->args);
    }

    public function testTapReturnsSameInstance(): void
    {
        $pipeline = take([1, 2, 3]);
        $result = $pipeline->tap($this->recordValue(...));

        $this->assertSame($pipeline, $result);
    }

    public function testTapInPipelineChain(): void
    {
        $debugValues = [];

        $result = take([1, 2, 3])
            ->tap(function ($value) use (&$debugValues): void {
                $debugValues[] = "before: $value";
            })
            ->map(fn($value) => $value * 2)
            ->tap(function ($value) use (&$debugValues): void {
                $debugValues[] = "after: $value";
            })
            ->toList();

        $this->assertSame([2, 4, 6], $result);
        $this->assertSame([
            'before: 1',
            'after: 2',
            'before: 2',
            'after: 4',
            'before: 3',
            'after: 6',
        ], $debugValues);
    }

    public function testTapOnArrayPipeline(): void
    {
        $pipeline = new Standard([1, 2, 3]);

        $result = $pipeline->tap($this->recordValue(...))->toList();

        $this->assertSame([1, 2, 3], $result);
        $this->assertSame([1, 2, 3], $this->sideEffects);
    }

    public function testTapIteratorPathCoverage(): void
    {
        $iterator = new ArrayIterator([10, 20, 30]);
        $pipeline = new Standard($iterator);

        $result = $pipeline->tap($this->recordValue(...))->toList();

        $this->assertSame([10, 20, 30], $result);
        $this->assertSame([10, 20, 30], $this->sideEffects);
    }
}
