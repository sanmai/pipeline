<?php
/*
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

use PHPUnit\Framework\TestCase;
use Pipeline\Standard;

/**
 * @covers \Pipeline\Standard
 * @covers \Pipeline\Principal
 */
class EdgeCasesTest extends TestCase
{
    public function testStandardStringFunctions()
    {
        $pipeline = new Standard(new \ArrayIterator([1, 2, 'foo', 'bar']));
        $pipeline->filter('is_int');

        $this->assertSame([1, 2], iterator_to_array($pipeline));
    }

    public function testFilterAnyFalseValue()
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

    public function testNonUniqueKeys()
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

    public function testMapUnprimed()
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            return 1;
        });

        $this->assertSame([1], $pipeline->toArray());
    }

    public function testFilterUnprimed()
    {
        $pipeline = new Standard();
        $pipeline->filter()->unpack();

        $this->assertSame([], $pipeline->toArray());
    }

    public function testUnpackUnprimed()
    {
        $pipeline = new Standard();
        $pipeline->unpack(function () {
            return 1;
        });

        $this->assertSame([1], $pipeline->toArray());
    }

    public function testInitialInvokeReturnsScalar()
    {
        $pipeline = new Standard();
        $pipeline->map($this);

        $this->assertSame([null], iterator_to_array($pipeline));
    }

    private function firstValueFromIterator(\Iterator $iterator)
    {
        $iterator->rewind();

        return $iterator->current();
    }

    public function testIteratorIterator()
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            return 42;
        });

        $iterator = new \IteratorIterator($pipeline);
        /* @var $iterator \Iterator */
        $this->assertSame(42, $this->firstValueFromIterator($iterator));

        $pipeline = new Standard(new \ArrayIterator([42]));
        $iterator = new \IteratorIterator($pipeline);
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

    public function testIteratorToArrayWithSameKeys()
    {
        $this->assertSame([3, 4], iterator_to_array($this->pipelineWithNonUniqueKeys()));
    }

    public function testIteratorToArrayWithAllValues()
    {
        $this->assertSame([2, 3, 3, 4], $this->pipelineWithNonUniqueKeys()->toArray());
    }

    public function testPipelineInvokeReturnsGenerator()
    {
        $pipeline = new Standard();
        $this->assertInstanceOf(\Generator::class, $pipeline());
    }

    public function testInvokeMaps()
    {
        $pipeline = new Standard(new \ArrayIterator(range(1, 5)));
        $pipeline->map($this);

        $this->assertSame(range(1, 5), iterator_to_array($pipeline));
    }

    public function __invoke($default = null)
    {
        return $default;
    }
}
