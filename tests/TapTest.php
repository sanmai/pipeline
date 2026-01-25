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
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function Pipeline\fromArray;
use function Pipeline\map;

use Pipeline\Standard;

use function Pipeline\take;

use SplQueue;

/**
 * @internal
 */
#[CoversClass(Standard::class)]
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

        $result = $pipeline->tap($this->recordKeyValue(...))->toAssoc();

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $result);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $this->sideEffects);
    }

    public function testTapDoesNotChangeValues(): void
    {
        $pipeline = take([1, 2, 3]);

        $result = $pipeline
            ->tap(fn($value) => $value * 10)
            ->map(fn($value) => $value * 2)
            ->toList();

        $this->assertSame([2, 4, 6], $result);
    }

    public function testTapChaining(): void
    {
        $result = take([1, 2, 3])
            ->tap($this->recordValue(...))
            ->cast(fn($value) => $value * 2)
            ->tap($this->recordValue(...))
            ->toList();

        $this->assertSame([2, 4, 6], $result);
        $this->assertSame([1, 2, 2, 4, 3, 6], $this->sideEffects);
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
        $pipeline = take([1, 2, 3])
            ->tap(function ($value): void {
                $this->recordValue($value);
                if (2 === $value) {
                    throw new LogicException('Test exception');
                }
            });

        $this->expectException(LogicException::class);

        $pipeline->toList();
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
        $pipeline = fromArray(['1', '2', '3'])
            ->tap(static function ($a, $b, $c): void {});

        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage('Too few arguments');

        $pipeline->toList();
    }

    public function testTapReturnsSameInstance(): void
    {
        $pipeline = take([1, 2, 3]);
        $result = $pipeline->tap($this->recordValue(...));

        $this->assertSame($pipeline, $result);
    }
}
