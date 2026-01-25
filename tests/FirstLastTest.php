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

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

use function Pipeline\fromArray;
use function Pipeline\map;

use Pipeline\Standard;

use function Pipeline\take;

/**
 * @internal
 */
#[CoversMethod(Standard::class, 'first')]
#[CoversMethod(Standard::class, 'last')]
final class FirstLastTest extends TestCase
{
    public function testFirstWithArray(): void
    {
        $pipeline = fromArray([1, 2, 3, 4, 5]);
        $this->assertSame(1, $pipeline->first());
    }

    public function testLastWithArray(): void
    {
        $pipeline = fromArray([1, 2, 3, 4, 5]);
        $this->assertSame(5, $pipeline->last());
    }

    public function testFirstWithGenerator(): void
    {
        $pipeline = map(function () {
            yield 'a';
            $this->fail('Should never reach this');
        });
        $this->assertSame('a', $pipeline->first());
    }

    public function testLastWithGenerator(): void
    {
        $pipeline = map(function () {
            yield 'a';
            yield 'b';
            yield 'c';
        });
        $this->assertSame('c', $pipeline->last());
    }

    public function testFirstWithEmptyPipeline(): void
    {
        $pipeline = new Standard();
        $this->assertNull($pipeline->first());
    }

    public function testLastWithEmptyPipeline(): void
    {
        $pipeline = new Standard();
        $this->assertNull($pipeline->last());
    }

    public function testFirstWithEmptyArray(): void
    {
        $pipeline = fromArray([]);
        $this->assertNull($pipeline->first());
    }

    public function testLastWithEmptyArray(): void
    {
        $pipeline = fromArray([]);
        $this->assertNull($pipeline->last());
    }

    public function testFirstWithEmptyGenerator(): void
    {
        $pipeline = map(function () {
            return;
            yield;
        });
        $this->assertNull($pipeline->first());
    }

    public function testLastWithEmptyGenerator(): void
    {
        $pipeline = map(function () {
            return;
            yield;
        });
        $this->assertNull($pipeline->last());
    }

    public function testFirstWithSingleElement(): void
    {
        $pipeline = fromArray(['only']);
        $this->assertSame('only', $pipeline->first());
    }

    public function testLastWithSingleElement(): void
    {
        $pipeline = fromArray(['only']);
        $this->assertSame('only', $pipeline->last());
    }

    public function testFirstPreservesKeys(): void
    {
        $pipeline = fromArray(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertSame(1, $pipeline->first());
    }

    public function testLastPreservesKeys(): void
    {
        $pipeline = fromArray(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertSame(3, $pipeline->last());
    }

    public function testFirstWithFalsyValues(): void
    {
        $pipeline = fromArray([false, 0, '', null, 'truthy']);
        $this->assertFalse($pipeline->first());
    }

    public function testLastWithFalsyValues(): void
    {
        $pipeline = fromArray([false, 0, '', null, 'truthy']);
        $this->assertSame('truthy', $pipeline->last());
    }

    public function testFirstWithOnlyFalse(): void
    {
        $pipeline = fromArray([false]);
        $this->assertFalse($pipeline->first());
    }

    public function testLastWithOnlyFalse(): void
    {
        $pipeline = fromArray([false]);
        $this->assertFalse($pipeline->last());
    }

    public function testFirstAfterOperations(): void
    {
        $pipeline = take([1, 2, 3, 4, 5])
            ->filter(fn($x) => $x > 2)
            ->map(fn($x) => $x * 10);

        $this->assertSame(30, $pipeline->first());
    }

    public function testLastAfterOperations(): void
    {
        $pipeline = take([1, 2, 3, 4, 5])
            ->filter(fn($x) => $x > 2)
            ->map(fn($x) => $x * 10);

        $this->assertSame(50, $pipeline->last());
    }
}
