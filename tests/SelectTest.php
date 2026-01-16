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

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use Pipeline\Standard;
use PHPUnit\Framework\Attributes\DataProvider;
use SplQueue;

use function iterator_to_array;
use function Pipeline\fromValues;
use function Pipeline\map;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class SelectTest extends TestCase
{
    private const NON_STRICT_FALSE_VALUES = [
        0,
        0.0,
        '',
        '0',
        [],
    ];

    public function testStandardStringFunctions(): void
    {
        $pipeline = new Standard(new ArrayIterator([1, 2, 'foo', 'bar']));
        $pipeline->select('is_int');

        $this->assertSame([1, 2], iterator_to_array($pipeline));
    }

    public function testStandardFunctions(): void
    {
        $pipeline = new Standard(new ArrayIterator([1, 2, 'foo', 'bar']));
        $pipeline->select(is_int(...));

        $this->assertSame([1, 2], iterator_to_array($pipeline));
    }

    public function testSelectAnyFalseValueDefaultCallback(): void
    {
        $pipeline = map(function () {
            yield false;
            yield 0;
            yield 0.0;
            yield '';
            yield '0';
            yield [];
            yield null;
        });

        $pipeline->select(strict: false);

        $this->assertCount(0, $pipeline->toList());
    }

    public function testSelectAnyFalseValueDefaultCallbackStrict(): void
    {
        $pipeline = map(function () {
            yield false;
            yield 0;
            yield 0.0;
            yield '';
            yield '0';
            yield [];
            yield null;
        });

        $pipeline->select();

        $this->assertCount(5, $pipeline->toList());
    }

    public function testSelectAnyFalseValueCustomCallback(): void
    {
        $pipeline = map(function () {
            yield false;
            yield 0;
            yield 0.0;
            yield '';
            yield '0';
            yield [];
            yield null;
            yield 1;
        });

        $pipeline->select('intval', strict: false);

        $this->assertSame([1], $pipeline->toList());
    }

    public function testSelectStrictMode(): void
    {
        $pipeline = map(function () {
            yield false;
            yield null;

            yield from self::NON_STRICT_FALSE_VALUES;
        });

        $pipeline->select(strict: true);

        $this->assertSame(self::NON_STRICT_FALSE_VALUES, $pipeline->toList());
    }

    public function testSelectStrictModeWithPredicate(): void
    {
        $pipeline = map(function () {
            yield false;
            yield null;

            yield from self::NON_STRICT_FALSE_VALUES;
        });

        $pipeline->select(fn($value) => $value, strict: true);

        $this->assertSame(self::NON_STRICT_FALSE_VALUES, $pipeline->toList());
    }

    public function testSelectNonStrictMode(): void
    {
        $pipeline = fromValues(false, null, '');
        $pipeline->select(strict: false);
        $this->assertCount(0, $pipeline);
    }

    public function testSelectUnprimed(): void
    {
        $pipeline = new Standard();
        $pipeline->select()->unpack();

        $this->assertSame([], $pipeline->toList());
    }

    public function testFilterIsNotStrictByDefault(): void
    {
        $pipeline = $this->getMockBuilder(Standard::class)
            ->setConstructorArgs([[1]])
            ->onlyMethods(['select'])
            ->getMock();

        $pipeline->expects($this->once())
            ->method('select')
            ->with(null, false)
            ->willReturn($pipeline);

        $pipeline->filter();
    }

    public static function provideFilterIsEquivalentToSelect(): iterable
    {
        yield [null, false];
        yield [null, true];
        yield [fn($value) => true, false];
        yield [fn($value) => true, true];
    }

    #[DataProvider('provideFilterIsEquivalentToSelect')]
    public function testFilterIsEquivalentToSelect(?callable $func, bool $strict): void
    {
        $pipeline = $this->getMockBuilder(Standard::class)
            ->setConstructorArgs([[1]])
            ->onlyMethods(['select'])
            ->getMock();

        $pipeline->expects($this->once())
            ->method('select')
            ->with($func, $strict)
            ->willReturn($pipeline);

        $pipeline->filter($func, $strict);
    }

    public function testSelectOnRejectCallback(): void
    {
        $rejected = [];

        $pipeline = fromValues(1, 2, 3, 4, 5);
        $pipeline->select(
            fn($value) => 0 === $value % 2,
            onReject: function ($value) use (&$rejected) {
                $rejected[] = $value;
            },
        );

        $this->assertSame([2, 4], $pipeline->toList());
        $this->assertSame([1, 3, 5], $rejected);
    }

    public function testSelectOnRejectCallbackWithKey(): void
    {
        $rejected = [];

        $pipeline = new Standard(new ArrayIterator(['a' => 1, 'b' => 2, 'c' => 3]));
        $pipeline->select(
            fn($value) => 2 === $value,
            onReject: function ($value, $key) use (&$rejected) {
                $rejected[$key] = $value;
            },
        );

        $this->assertSame(['b' => 2], $pipeline->toAssoc());
        $this->assertSame(['a' => 1, 'c' => 3], $rejected);
    }

    public function testSelectOnRejectCallbackWithArrayInput(): void
    {
        $rejected = [];

        $pipeline = new Standard([1, 2, 3]);
        $pipeline->select(
            fn($value) => $value > 2,
            onReject: function ($value) use (&$rejected) {
                $rejected[] = $value;
            },
        );

        $this->assertSame([3], $pipeline->toList());
        $this->assertSame([1, 2], $rejected);
    }

    public function testSelectOnRejectCallbackWithStrictMode(): void
    {
        $rejected = [];

        $pipeline = fromValues(null, false, 0, '', 'valid');
        $pipeline->select(
            onReject: function ($value) use (&$rejected) {
                $rejected[] = $value;
            },
        );

        $this->assertSame([0, '', 'valid'], $pipeline->toList());
        $this->assertSame([null, false], $rejected);
    }

    public function testSelectOnRejectCallbackWithNonStrictMode(): void
    {
        $rejected = [];

        $pipeline = fromValues(null, false, 0, '', 'valid');
        $pipeline->select(
            strict: false,
            onReject: function ($value) use (&$rejected) {
                $rejected[] = $value;
            },
        );

        $this->assertSame(['valid'], $pipeline->toList());
        $this->assertSame([null, false, 0, ''], $rejected);
    }

    public function testSelectOnRejectCallbackWithInternalCallable(): void
    {
        $queue = new SplQueue();

        $pipeline = fromValues(1, 2, 3);
        $pipeline->select(
            fn($value) => 2 === $value,
            onReject: $queue->enqueue(...),
        );

        $this->assertSame([2], $pipeline->toList());
        $this->assertSame([1, 3], iterator_to_array($queue));
    }
}
