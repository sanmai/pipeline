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

use PHPUnit\Framework\TestCase;
use Pipeline\Standard;

/**
 * @covers \Pipeline\Principal
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class StandardTest extends TestCase
{
    public function testEmpty(): void
    {
        $this->assertSame([], \iterator_to_array(new Standard()));

        $pipeline = new Standard();
        $this->assertSame([], $pipeline->toArray());

        $this->assertSame(0, \iterator_count(new Standard()));
    }

    public function testEmptyPHPUnitWise(): void
    {
        try {
            $this->assertCount(0, new Standard());
        } catch (\BadMethodCallException $e) {
            $this->markTestIncomplete($e->getMessage());
        }
    }

    public function testSingle(): void
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            foreach (\range(1, 3) as $i) {
                yield $i;
            }
        });

        $this->assertSame([1, 2, 3], \iterator_to_array($pipeline));
    }

    public function testDouble(): void
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            foreach (\range(1, 3) as $i) {
                yield $i;
            }
        });

        $pipeline->map(function ($i) {
            yield $i * 10;
            yield $i * 100;
            yield $i * 1000;
        });

        $this->assertSame([10, 100, 1000, 20, 200, 2000, 30, 300, 3000], $pipeline->toArray());
    }

    public function testTriple(): void
    {
        $pipeline = new Standard(new \ArrayIterator(\range(1, 3)));

        $pipeline->map(function ($i) {
            yield $i ** 2;
            yield $i ** 3;
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

        $this->assertSame([52, 104], $pipeline->toArray());
    }

    public function testFilter(): void
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            foreach (\range(1, 100) as $i) {
                yield $i;
            }
        });

        $pipeline->filter(function ($i) {
            return 0 === $i % 7;
        });

        $pipeline->map(function ($i) {
            yield \max(0, $i - 50);
        });

        $pipeline->filter();

        $this->assertSame([6, 13, 20, 27, 34, 41, 48], $pipeline->toArray());
    }

    public function testReduce(): void
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            foreach (\range(1, 10) as $i) {
                yield $i;
            }
        });

        $result = $pipeline->reduce();

        $this->assertSame(55, $result);
    }

    public function testReduceFloat(): void
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            foreach (\range(1, 10) as $i) {
                yield $i * 1.05;
            }
        });

        $result = $pipeline->reduce();

        $this->assertSame(55 * 1.05, $result);
    }

    public function testReduceArrays(): void
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            yield [0 => 1];
            yield [1 => 2];
            yield [0 => 3];
        });

        $result = $pipeline->reduce(null, []);

        $this->assertSame([1, 2], $result);
    }

    public function testReduceToArray(): void
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            foreach (\range(1, 10) as $i) {
                yield $i;
            }
        });

        $pipeline->map(function ($i) {
            yield $i ** 2;
            yield $i ** 3;
        });

        $pipeline->filter(function ($i) {
            return 0 === $i % 3;
        });

        // just what iterator_to_array does
        $result = $pipeline->reduce(function ($sum, $i) {
            $sum[] = $i;

            return $sum;
        }, []);

        $this->assertSame([9, 27, 36, 216, 81, 729], $result);
    }

    public function testReduceEmpty(): void
    {
        $pipeline = new Standard();

        $this->assertSame(0, $pipeline->reduce());
    }

    public function testMeaningless(): void
    {
        $pipeline = new Standard(new \ArrayIterator([]));

        $pipeline->map(function ($i) {
            $this->fail();
            // never gets called
            yield $i + 1;
        });

        $this->assertSame(0, $pipeline->reduce());
    }

    public function testPipelineInPipeline(): void
    {
        $pipeline1 = new Standard(new \ArrayIterator([2, 3, 5, 7, 11]));
        $pipeline1->map(function ($prime) {
            yield $prime;
            yield $prime * 2;
        });

        $pipeline2 = new Standard($pipeline1);
        $pipeline2->filter(function ($i) {
            return 0 !== $i % 2;
        });

        $this->assertSame(3 + 5 + 7 + 11, $pipeline2->reduce());
    }

    public function testFiltersPipeline(): void
    {
        $input = new Standard(new \ArrayIterator([2, 3, 5, 7, 11]));
        $input->map(function ($prime) {
            yield $prime;
            yield $prime * 2;
        });

        $output = new Standard($input);
        $output->filter(function ($i) {
            return 0 !== $i % 2;
        });

        $this->assertSame(3 + 5 + 7 + 11, $output->reduce());
    }

    public function testPipelineReadsFromPipeline(): void
    {
        $foo = new Standard();
        $foo->map(function () {
            yield 1;
            yield 2;
        });

        $bar = new Standard($foo);
        $this->assertSame(3, $bar->reduce());
    }

    private $double;
    private $plusone;

    public function testTestableGenerator(): void
    {
        $this->double = function ($value) {
            return $value * 2;
        };

        $this->assertSame(8, \call_user_func($this->double, 4));

        $this->plusone = function ($value) {
            yield $value;
            yield $value + 1;
        };

        $this->assertSame([4, 5], \iterator_to_array(\call_user_func($this->plusone, 4)));

        // initial generator
        $sourceData = new \ArrayIterator(\range(1, 5));

        $pipeline = new \Pipeline\Standard($sourceData);
        $pipeline->map($this->double);
        $pipeline->map($this->double);
        $pipeline->map($this->plusone);

        $this->assertSame([4, 5, 8, 9, 12, 13, 16, 17, 20, 21], $pipeline->toArray());
    }

    public function testMethodChaining(): void
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            return \range(1, 3);
        })->unpack(function ($a, $b, $c) {
            yield $a;
            yield $b;
            yield $c;
        })->map(function ($i) {
            yield $i ** 2;
            yield $i ** 3;
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

        $this->assertSame([52, 104], \iterator_to_array($pipeline));
    }

    public function testMapNoop(): void
    {
        $pipeline = new Standard();

        $pipeline->map(function () {
            return \range(1, 3);
        })->unpack()->map()->map()->map();

        $this->assertSame(\range(1, 3), \iterator_to_array($pipeline));
    }

    public function testFinal(): void
    {
        $reflector = new \ReflectionClass(Standard::class);
        $this->assertTrue($reflector->isFinal());
    }
}
