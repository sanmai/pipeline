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
class StandardTest extends TestCase
{
    public function testEmpty()
    {
        $this->assertEquals([], iterator_to_array(new Standard()));

        $pipeline = new Standard();
        $this->assertEquals([], $pipeline->toArray());
    }

    public function testSingle()
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            foreach (range(1, 3) as $i) {
                yield $i;
            }
        });

        $this->assertEquals([1, 2, 3], iterator_to_array($pipeline));
    }

    public function testDouble()
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            foreach (range(1, 3) as $i) {
                yield $i;
            }
        });

        $pipeline->map(function ($i) {
            yield $i * 10;
            yield $i * 100;
            yield $i * 1000;
        });

        $this->assertEquals([10, 100, 1000, 20, 200, 2000, 30, 300, 3000], $pipeline->toArray());
    }

    public function testTriple()
    {
        $pipeline = new Standard(new \ArrayIterator(range(1, 3)));

        $pipeline->map(function ($i) {
            yield pow($i, 2);
            yield pow($i, 3);
        });

        $pipeline->map(function ($i) {
            return $i - 1;
        });

        $pipeline->map(function ($i) {
            yield $i * 2;
            yield $i * 4;
        });

        $pipeline->map(function ($i) {
            if ($i > 50) {
                yield $i;
            }
        });

        $this->assertEquals([52, 104], $pipeline->toArray());
    }

    public function testFilter()
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            foreach (range(1, 100) as $i) {
                yield $i;
            }
        });

        $pipeline->filter(function ($i) {
            return $i % 7 == 0;
        });

        $pipeline->map(function ($i) {
            yield max(0, $i - 50);
        });

        $pipeline->filter();

        $this->assertEquals([6, 13, 20, 27, 34, 41, 48], $pipeline->toArray());
    }

    public function testReduce()
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            foreach (range(1, 10) as $i) {
                yield $i;
            }
        });

        $result = $pipeline->reduce();

        $this->assertEquals(55, $result);
    }

    public function testReduceFloat()
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            foreach (range(1, 10) as $i) {
                yield $i * 1.05;
            }
        });

        $result = $pipeline->reduce();

        $this->assertEquals(55 * 1.05, $result);
    }

    public function testReduceArrays()
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            yield [0 => 1];
            yield [1 => 2];
            yield [0 => 3];
        });

        $result = $pipeline->reduce(null, []);

        $this->assertEquals([1, 2], $result);
    }

    public function testReduceToArray()
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            foreach (range(1, 10) as $i) {
                yield $i;
            }
        });

        $pipeline->map(function ($i) {
            yield pow($i, 2);
            yield pow($i, 3);
        });

        $pipeline->filter(function ($i) {
            return $i % 3 == 0;
        });

        // just what iterator_to_array does
        $result = $pipeline->reduce(function ($sum, $i) {
            $sum[] = $i;

            return $sum;
        }, []);

        $this->assertEquals([9, 27, 36, 216, 81, 729], $result);
    }

    public function testMeaningless()
    {
        $pipeline = new Standard(new \ArrayIterator([]));

        $pipeline->map(function ($i) {
            $this->fail();
            // never gets called
            yield $i + 1;
        });

        $this->assertEquals(0, $pipeline->reduce());
    }

    public function testPipelineInPipeline()
    {
        $pipeline1 = new Standard(new \ArrayIterator([2, 3, 5, 7, 11]));
        $pipeline1->map(function ($prime) {
            yield $prime;
            yield $prime * 2;
        });

        $pipeline2 = new Standard();
        $pipeline2->map($pipeline1)->filter(function ($i) {
            return $i % 2 != 0;
        });

        $this->assertEquals(3 + 5 + 7 + 11, $pipeline2->reduce());
    }

    public function testFiltersPipeline()
    {
        $input = new Standard(new \ArrayIterator([2, 3, 5, 7, 11]));
        $input->map(function ($prime) {
            yield $prime;
            yield $prime * 2;
        });

        $output = new Standard($input);
        $output->filter(function ($i) {
            return $i % 2 != 0;
        });

        $this->assertEquals(3 + 5 + 7 + 11, $output->reduce());
    }

    public function testPipelineReadsFromPipeline()
    {
        $foo = new Standard();
        $foo->map(function () {
            yield 1;
            yield 2;
        });

        $bar = new Standard();
        $bar->map($foo);
        $this->assertEquals(3, $bar->reduce());
    }

    private $double;
    private $plusone;

    public function testTestableGenerator()
    {
        $this->double = function ($value) {
            return $value * 2;
        };

        $this->assertSame(8, call_user_func($this->double, 4));

        $this->plusone = function ($value) {
            yield $value;
            yield $value + 1;
        };

        $this->assertSame([4, 5], iterator_to_array(call_user_func($this->plusone, 4)));

        // initial generator
        $sourceData = new \ArrayIterator(range(1, 5));

        $pipeline = new \Pipeline\Standard($sourceData);
        $pipeline->map($this->double);
        $pipeline->map($this->double);
        $pipeline->map($this->plusone);

        $this->assertSame([4, 5, 8, 9, 12, 13, 16, 17, 20, 21], $pipeline->toArray());
    }

    public function testMethodChaining()
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            return range(1, 3);
        })->unpack(function ($a, $b, $c) {
            yield $a;
            yield $b;
            yield $c;
        })->map(function ($i) {
            yield pow($i, 2);
            yield pow($i, 3);
        })->map(function ($i) {
            return $i - 1;
        })->map(function ($i) {
            yield $i * 2;
            yield $i * 4;
        })->filter(function ($i) {
            return $i > 50;
        })->map(function ($i) {
            return $i;
        });

        $this->assertEquals([52, 104], iterator_to_array($pipeline));
    }

    public function testMapNoop()
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            return range(1, 3);
        })->unpack()->map()->map()->map();

        $this->assertEquals(range(1, 3), iterator_to_array($pipeline));
    }

    public function testFinal()
    {
        $reflector = new \ReflectionClass(Standard::class);
        $this->assertTrue($reflector->isFinal());
    }
}
