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
use Iterator;
use function iterator_to_array;
use IteratorIterator;
use NoRewindIterator;
use PHPUnit\Framework\TestCase;
use function Pipeline\map;
use Pipeline\Standard;
use function range;

/**
 * @covers \Pipeline\Principal
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class EdgeCasesTest extends TestCase
{
    public function testStandardStringFunctions(): void
    {
        $pipeline = new Standard(new ArrayIterator([1, 2, 'foo', 'bar']));
        $pipeline->filter('is_int');

        $this->assertSame([1, 2], iterator_to_array($pipeline));
    }

    /**
     * @covers \Pipeline\Standard::filter()
     */
    public function testFilterAnyFalseValueDefaultCallback(): void
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            yield false;
            yield 0;
            yield 0.0;
            yield '';
            yield '0';
            yield [];
            yield null;
        });

        $pipeline->filter();

        $this->assertCount(0, $pipeline->toArray());
    }

    /**
     * @covers \Pipeline\Standard::filter()
     */
    public function testFilterAnyFalseValueCustomCallback(): void
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            yield false;
            yield 0;
            yield 0.0;
            yield '';
            yield '0';
            yield [];
            yield null;
        });

        $pipeline->filter('intval');

        $this->assertCount(0, $pipeline->toArray());
    }

    public function testNonUniqueKeys(): void
    {
        $pipeline = \Pipeline\map(function () {
            yield 'foo' => 'bar';
            yield 'foo' => 'baz';
        });

        $this->assertSame([
            'foo' => 'baz',
        ], iterator_to_array($pipeline));

        $pipeline = \Pipeline\map(function () {
            yield 'foo' => 'bar';
            yield 'foo' => 'baz';
        });

        $this->assertSame([
            'bar',
            'baz',
        ], $pipeline->toArray());
    }

    public function testMapUnprimed(): void
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            return 1;
        });

        $this->assertSame([1], $pipeline->toArray());
    }

    public function testFilterUnprimed(): void
    {
        $pipeline = new Standard();
        $pipeline->filter()->unpack();

        $this->assertSame([], $pipeline->toArray());
    }

    public function testUnpackUnprimed(): void
    {
        $pipeline = new Standard();
        $pipeline->unpack(function () {
            return 1;
        });

        $this->assertSame([1], $pipeline->toArray());
    }

    public function testInitialInvokeReturnsScalar(): void
    {
        $pipeline = new Standard();
        $pipeline->map($this);

        $this->assertSame([null], iterator_to_array($pipeline));
    }

    private function firstValueFromIterator(Iterator $iterator)
    {
        $iterator->rewind();

        return $iterator->current();
    }

    public function testIteratorIterator(): void
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            return 42;
        });

        $iterator = new IteratorIterator($pipeline);
        // @var $iterator \Iterator
        $this->assertSame(42, $this->firstValueFromIterator($iterator));

        $pipeline = new Standard(new ArrayIterator([42]));
        $iterator = new IteratorIterator($pipeline);
        $this->assertSame(42, $this->firstValueFromIterator($iterator));
    }

    private function pipelineWithNonUniqueKeys(): Standard
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            yield 1;
            yield 2;
        });

        $pipeline->map(function ($value) {
            yield $value + 1;
            yield $value + 2;
        });

        return $pipeline;
    }

    public function testIteratorToArrayWithSameKeys(): void
    {
        $this->assertSame([3, 4], iterator_to_array($this->pipelineWithNonUniqueKeys()));
    }

    public function testIteratorToArrayWithAllValues(): void
    {
        $this->assertSame([2, 3, 3, 4], $this->pipelineWithNonUniqueKeys()->toArray());
    }

    public function testInvokeMaps(): void
    {
        $pipeline = new Standard(new ArrayIterator(range(1, 5)));
        $pipeline->map($this);

        $this->assertSame(range(1, 5), iterator_to_array($pipeline));
    }

    public function __invoke($default = null)
    {
        return $default;
    }

    public function testIterateAgainDoesNotFail(): void
    {
        $pipeline = new Standard(new ArrayIterator(range(1, 5)));
        $pipeline->map(function ($value) {
            yield $value;
        });

        // NoRewindIterator works around the exception: Cannot traverse an already closed generator
        $iterator = new NoRewindIterator($pipeline->getIterator());

        $this->assertSame(range(1, 5), iterator_to_array($iterator, false));

        $this->assertSame([], iterator_to_array($iterator, false));
    }

    public function testReplaceGenerator(): void
    {
        $actual = map(static function (): iterable {
            $generator = static function (): iterable {
                yield 1;
            };

            // Pipeline does not check if a callback is a generator, only return value is checked.
            return $generator();
        })->reduce();

        $this->assertSame(1, $actual);
    }

    public function testNotReplaceGenerator(): void
    {
        $actual = map(static function (): iterable {
            $generator = static function (): iterable {
                yield 1;
            };

            yield $generator();
        })->map(static function (iterable $value): iterable {
            yield from $value;
        })->reduce();

        $this->assertSame(1, $actual);
    }
}
