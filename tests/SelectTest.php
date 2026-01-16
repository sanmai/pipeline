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
use Pipeline\Standard;
use SplQueue;

use function iterator_to_array;
use function Pipeline\fromArray;
use function Pipeline\fromValues;
use function Pipeline\map;
use function Pipeline\take;

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

    private array $rejected;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rejected = [];
    }

    private function recordValue($value): void
    {
        $this->rejected[] = $value;
    }

    private function recordKeyValue($value, $key): void
    {
        $this->rejected[$key] = $value;
    }

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

    public function testSelectOnRejectCallback(): void
    {
        $pipeline = fromValues(1, 2, 3, 4, 5);
        $pipeline->select(
            fn($value) => 0 === $value % 2,
            onReject: $this->recordValue(...),
        );

        $this->assertSame([2, 4], $pipeline->toList());
        $this->assertSame([1, 3, 5], $this->rejected);
    }

    public function testSelectOnRejectCallbackWithKey(): void
    {
        $pipeline = take(new ArrayIterator(['a' => 1, 'b' => 2, 'c' => 3]));
        $pipeline->select(
            fn($value) => 2 === $value,
            onReject: $this->recordKeyValue(...),
        );

        $this->assertSame(['b' => 2], $pipeline->toAssoc());
        $this->assertSame(['a' => 1, 'c' => 3], $this->rejected);
    }

    public function testSelectOnRejectCallbackWithArrayInput(): void
    {
        $pipeline = fromArray([1, 2, 3]);
        $pipeline->select(
            fn($value) => $value > 2,
            onReject: $this->recordValue(...),
        );

        $this->assertSame([3], $pipeline->toList());
        $this->assertSame([1, 2], $this->rejected);
    }

    public function testSelectOnRejectCallbackWithStrictMode(): void
    {
        $pipeline = fromValues(null, false, 0, '', 'valid');
        $pipeline->select(
            onReject: $this->recordValue(...),
        );

        $this->assertSame([0, '', 'valid'], $pipeline->toList());
        $this->assertSame([null, false], $this->rejected);
    }

    public function testSelectOnRejectCallbackWithNonStrictMode(): void
    {
        $pipeline = fromValues(null, false, 0, '', 'valid');
        $pipeline->select(
            strict: false,
            onReject: $this->recordValue(...),
        );

        $this->assertSame(['valid'], $pipeline->toList());
        $this->assertSame([null, false, 0, ''], $this->rejected);
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
