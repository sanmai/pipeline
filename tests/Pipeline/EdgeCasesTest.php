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

namespace Pipeline;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Pipeline\Standard
 * @covers \Pipeline\Principal
 */
class EdgeCasesTest extends TestCase
{
    public function testInitialCallbackNotGenerator()
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            return PHP_INT_MAX;
        });

        $this->assertEquals([PHP_INT_MAX], iterator_to_array($pipeline));
    }

    public function testStandardStringFunctions()
    {
        $pipeline = new Standard(new \ArrayIterator([1, 2, 'foo', 'bar']));
        $pipeline->filter('is_int');

        $this->assertEquals([1, 2], iterator_to_array($pipeline));
    }

    public function testMapUnprimed()
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            return 1;
        });

        $this->assertEquals([1], $pipeline->toArray());
    }

    public function testFilterUnprimed()
    {
        $pipeline = new Standard();
        $pipeline->filter()->unpack();

        $this->assertEquals([], $pipeline->toArray());
    }

    public function testUnpackUnprimed()
    {
        $pipeline = new Standard();
        $pipeline->unpack(function () {
            return 1;
        });

        $this->assertEquals([1], $pipeline->toArray());
    }

    public function testInitialInvokeReturnsScalar()
    {
        $pipeline = new Standard();
        $pipeline->map($this);

        $this->assertEquals([null], iterator_to_array($pipeline));
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
        $this->assertEquals(42, $this->firstValueFromIterator($iterator));

        $pipeline = new Standard(new \ArrayIterator([42]));
        $iterator = new \IteratorIterator($pipeline);
        $this->assertEquals(42, $this->firstValueFromIterator($iterator));
    }

    public function testIteratorToArrayWithSameKeys()
    {
        $pipeline = new \Pipeline\Standard();
        $pipeline->map(function () {
            yield 1;
            yield 2;
        });

        $pipeline->map(function ($i) {
            yield $i + 1;
            yield $i + 2;
        });

        $this->assertEquals([3, 4], iterator_to_array($pipeline));
    }

    public function testPointlessReplace()
    {
        $pipeline = new Standard(new \ArrayIterator([1, 2]));

        $pipeline->map(function () {
            yield from new Standard(new \ArrayIterator([3, 4]));
        });

        $this->assertSame([3, 4, 3, 4], $pipeline->toArray());
    }

    public function testNormalReplace()
    {
        $pipeline = new Standard(new \ArrayIterator([1, 2]));
        $pipeline->map(new Standard(new \ArrayIterator([3, 4])));
        $this->assertSame([3, 4], $pipeline->toArray());
    }

    public function testArrayReduce()
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            return 3;
        });

        $this->assertSame(3, $pipeline->reduce());
    }

    public function testInvokeMaps()
    {
        $pipeline = new \Pipeline\Standard(new \ArrayIterator(range(1, 5)));
        $pipeline->map($this);

        $this->assertEquals(range(1, 5), iterator_to_array($pipeline));
    }

    public function __invoke($default = null)
    {
        return $default;
    }
}
