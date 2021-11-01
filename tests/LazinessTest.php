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
use Exception;
use PHPUnit\Framework\TestCase;
use Pipeline\Standard;
use Traversable;

/**
 * @covers \Pipeline\Principal
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class LazinessTest extends TestCase
{
    private function yieldFail(): bool
    {
        $this->fail();

        return false;
    }

    public function testEagerReturn(): void
    {
        $this->expectException(Exception::class);

        $pipeline = new Standard();
        $pipeline->map(function (): void {
            // Executed on spot
            throw new Exception();
        });
    }

    private function veryExpensiveMethod(): bool
    {
        throw new Exception();

        return true;
    }

    public function testExpensiveMethod(): void
    {
        $this->expectException(Exception::class);

        $pipeline = new Standard();
        $pipeline->map(function () {
            // Executed on spot
            return $this->veryExpensiveMethod();
        });
    }

    private function failingGenerator()
    {
        // Should never throw if used lazily
        throw new Exception();
        yield;
    }

    public function testGeneratorReturn(): void
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            // Executed on spot
            return $this->failingGenerator();
        })->map(function ($value) {
            return $value;
        })->filter();

        // All good, no exceptions were thrown
        $this->addToAssertionCount(1);
    }

    public function testGeneratorYieldFrom(): void
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            throw new Exception();
            yield from $this->failingGenerator();
        })->map(function ($value) {
            return $value;
        })->filter();

        // All good, no exceptions were thrown
        $this->addToAssertionCount(1);
    }

    public function testLazyIterator(): void
    {
        $spy = $this->createMock(ArrayIterator::class);
        $spy
            ->expects($this->never())
            ->method('rewind')
        ;

        $pipeline = new Standard($spy);
        $pipeline->map(function ($value) {
            yield $value;
        })->map(function ($value) {
            return $value;
        })->filter();
    }

    public function testLazyIteratorYieldFrom(): void
    {
        $spy = $this->createMock(ArrayIterator::class);
        $spy
            ->expects($this->never())
            ->method('rewind')
        ;

        $pipeline = new Standard();
        $pipeline->map(function () use ($spy) {
            yield from $spy;
        })->map(function ($value) {
            yield $value;
        })->map(function ($value) {
            return $value;
        })->filter();
    }

    public function testLazyIteratorReturn(): void
    {
        $spy = $this->createMock(ArrayIterator::class);
        $spy
            ->expects($this->never())
            ->method('rewind')
        ;

        $pipeline = new Standard();
        $pipeline->map(function () use ($spy) {
            return $spy;
        })->map(function ($value) {
            yield $value;
        })->map(function ($value) {
            return $value;
        })->filter();
    }

    public function testMapLazyOnce(): void
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            yield true;
            yield $this->yieldFail();
        });

        $this->assertInstanceOf(Traversable::class, $pipeline);

        foreach ($pipeline as $value) {
            $this->assertTrue($value);

            break;
        }
    }

    public function testFilterLazyOnce(): void
    {
        $pipeline = new Standard(new ArrayIterator([true]));
        $pipeline->filter(function (): void {
            $this->fail();
        });

        $iterator = $pipeline->getIterator();
        $this->assertInstanceOf(Traversable::class, $iterator);
    }

    public function testUnpackLazyOnce(): void
    {
        $pipeline = new Standard();
        $pipeline->unpack(function () {
            yield true;
            yield $this->yieldFail();
        });

        $this->assertInstanceOf(Traversable::class, $pipeline);

        foreach ($pipeline as $value) {
            $this->assertTrue($value);

            break;
        }
    }
}
