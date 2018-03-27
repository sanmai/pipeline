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

/**
 * @covers \Pipeline\Standard
 * @covers \Pipeline\Principal
 */
class LazinessTest extends \Mockery\Adapter\Phpunit\MockeryTestCase
{
    private function yieldFail()
    {
        $this->fail();
    }

    public function testEagerReturn()
    {
        $this->expectException(\Exception::class);

        $pipeline = new Standard();
        $pipeline->map(function () {
            // Executed on spot
            throw new \Exception();
        });
    }

    private function veryExpensiveMethod()
    {
        throw new \Exception();
    }

    public function testExpensiveMethod()
    {
        $this->expectException(\Exception::class);

        $pipeline = new Standard();
        $pipeline->map(function () {
            // Executed on spot
            return $this->veryExpensiveMethod();
        });
    }

    private function failingGenerator()
    {
        // Should never throw if used lazily
        throw new \Exception();
        yield;
    }

    public function testGeneratorReturn()
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

    public function testGeneratorYieldFrom()
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            throw new \Exception();
            yield from $this->failingGenerator();
        })->map(function ($value) {
            return $value;
        })->filter();

        // All good, no exceptions were thrown
        $this->addToAssertionCount(1);
    }

    public function testLazyIterator()
    {
        $spy = \Mockery::spy(\ArrayIterator::class);

        $pipeline = new Standard($spy);
        $pipeline->map(function ($value) {
            yield $value;
        })->map(function ($value) {
            return $value;
        })->filter();

        $spy->shouldNotReceive('rewind');
    }

    public function testLazyIteratorYieldFrom()
    {
        $spy = \Mockery::spy(\ArrayIterator::class);

        $pipeline = new Standard();
        $pipeline->map(function () use ($spy) {
            yield from $spy;
        })->map(function ($value) {
            yield $value;
        })->map(function ($value) {
            return $value;
        })->filter();

        $spy->shouldNotReceive('rewind');
    }

    public function testLazyIteratorReturn()
    {
        $spy = \Mockery::spy(\ArrayIterator::class);

        $pipeline = new Standard();
        $pipeline->map(function () use ($spy) {
            return $spy;
        })->map(function ($value) {
            yield $value;
        })->map(function ($value) {
            return $value;
        })->filter();

        $spy->shouldNotReceive('rewind');
    }

    public function testMapLazyOnce()
    {
        $pipeline = new Standard();
        $pipeline->map(function () {
            yield true;
            yield $this->yieldFail();
        });

        $this->assertInstanceOf(\Traversable::class, $pipeline);

        foreach ($pipeline as $value) {
            $this->assertTrue($value);
            break;
        }
    }

    public function testFilterLazyOnce()
    {
        $pipeline = new Standard(new \ArrayIterator([true]));
        $pipeline->filter(function () {
            $this->fail();
        });

        $iterator = $pipeline->getIterator();
        $this->assertInstanceOf(\Traversable::class, $iterator);
    }

    public function testUnpackLazyOnce()
    {
        $pipeline = new Standard();
        $pipeline->unpack(function () {
            yield true;
            yield $this->yieldFail();
        });

        $this->assertInstanceOf(\Traversable::class, $pipeline);

        foreach ($pipeline as $value) {
            $this->assertTrue($value);
            break;
        }
    }
}
