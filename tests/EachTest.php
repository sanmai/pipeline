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
use PHPUnit\Framework\TestCase;
use Pipeline\Standard;
use ArrayIterator;
use SplQueue;
use Tests\Pipeline\Fixtures\CallableThrower;

use function Pipeline\map;
use function Pipeline\take;
use function Pipeline\fromArray;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class EachTest extends TestCase
{
    private array $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->output = [];
    }

    protected function observeValue($value): void
    {
        $this->output[] = $value;
    }

    protected function observeKeyValue($key, $value): void
    {
        $this->output[$key] = $value;
    }

    public function testUninitialized(): void
    {
        $pipeline = new Standard();

        $pipeline->each($this->observeValue(...));

        $this->assertSame([], $this->output);
    }

    public function testEmpty(): void
    {
        $pipeline = take([]);

        $pipeline->each($this->observeValue(...));

        $this->assertSame([], $this->output);
    }

    public function testEmptyGenerator(): void
    {
        $pipeline = map(static fn() => yield from []);

        $pipeline->each($this->observeValue(...));

        $this->assertSame([], $this->output);
    }

    public function testNotEmpty(): void
    {
        $pipeline = take([1, 2, 3, 4]);

        $pipeline->each($this->observeValue(...));

        $this->assertSame([1, 2, 3, 4], $this->output);
    }

    public function testKeyValue(): void
    {
        $pipeline = take([5 => 1, 2, 3, 4]);

        $pipeline->each(fn(int $value, int $key) => $this->observeKeyValue($key, $value));

        $this->assertSame([5 => 1, 2, 3, 4], $this->output);
    }


    public static function provideInterrupt(): iterable
    {
        yield [map(fn() => yield from [1, 2, 3, 4])];
        yield [take(new ArrayIterator([1, 2, 3, 4]))];
    }

    /**
     * @dataProvider provideInterrupt
     */
    public function testInterrupt(Standard $pipeline): void
    {
        $pipeline->cast(function (int $value) {
            if (3 === $value) {
                throw new LogicException();
            }

            return $value;
        });

        try {
            $pipeline->each($this->observeValue(...));
        } catch (LogicException $_) {
            $this->assertSame([1, 2], $this->output);
        }

        $pipeline->each(function (int $value): void {
            $this->fail();
        });

        $this->assertSame([1, 2], $this->output);
        $this->assertSame([], $pipeline->toList());
    }

    public function testNoDiscard(): void
    {
        $pipeline = fromArray([1, 2, 3]);

        $pipeline->each($this->observeValue(...), false);

        $pipeline->each($this->observeValue(...));

        $this->assertSame([1, 2, 3, 1, 2, 3], $this->output);
        $this->assertSame([], $pipeline->toList());
    }

    public function testNotVoidReturn(): void
    {
        $pipeline = fromArray([1, 2, 3]);
        $pipeline->each(intval(...));

        $this->addToAssertionCount(1);
    }

    public function testStrictArity(): void
    {
        $queue = new SplQueue();
        $pipeline = fromArray([1, 2, 3]);
        $pipeline->each($queue->enqueue(...));

        $this->assertSame([1, 2, 3], take($queue)->toList());
    }

    public function testVariadicInternal(): void
    {
        $this->expectOutputString("123");

        $pipeline = fromArray(['1', '2', '3']);
        $pipeline->each(printf(...));
    }

    public function testVariadicInternalOnIterator(): void
    {
        $this->expectOutputString("123");

        $pipeline = take(new ArrayIterator(['1', '2', '3']));
        $pipeline->each(printf(...));
    }

    public function testArgumentCountError(): void
    {
        $pipeline = fromArray(['1', '2', '3']);

        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage('Too few arguments');
        $pipeline->each(static function ($a, $b, $c): void {});
    }

    /**
     * Test that the reassignment of the callable inside the loop will affect all iterations.
     */
    public function testCallableReassigned(): void
    {
        $callback = new CallableThrower();

        $pipeline = fromArray(['1', '2', '3']);
        $pipeline->each($callback);

        $this->assertSame([
            ['1', 0],
            ['1'],
            ['2'],
            ['3'],
        ], $callback->args);
    }
}
