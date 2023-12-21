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
use Pipeline\Helper\RunningVariance;

use function Pipeline\fromArray;
use function Pipeline\map;

use const NAN;

/**
 * @covers \Pipeline\Standard::feedRunningVariance()
 * @covers \Pipeline\Standard::finalVariance()
 * @covers \Pipeline\Standard::runningVariance()
 *
 * @internal
 */
final class VarianceTest extends TestCase
{
    public function testVarianceUnitinialized(): void
    {
        $pipeline = new \Pipeline\Standard();

        $this->assertSame(0, $pipeline->finalVariance()->getCount());
    }

    public function testVarianceEmptyArray(): void
    {
        $this->assertSame(0, fromArray([])->finalVariance()->getCount());
    }

    public function testVarianceNANPassThrough(): void
    {
        $this->assertNan(fromArray([1.0, 2.0, 3.0, NAN])->finalVariance()->getStandardDeviation());
    }

    public function testVarianceArray(): void
    {
        $this->assertEqualsWithDelta(
            2.2913,
            fromArray([5, 5, 9, 9, 9, 10, 5, 10, 10])->finalVariance()->getStandardDeviation(),
            0.0001
        );
    }

    public function testVarianceIterable(): void
    {
        $pipeline = map(fn() => yield from [5, 5, 9, 9, 9, 10, 5, 10, 10]);

        $this->assertEqualsWithDelta(
            2.2913,
            $pipeline->finalVariance()->getStandardDeviation(),
            0.0001
        );
    }

    public function testFinalVarianceReuse(): void
    {
        $a = map(fn() => yield from [5, 5, 9, 9]);
        $variance = $a->finalVariance();

        $b = map(fn() => yield from [9, 10, 5, 10, 10]);
        $variance = $b->finalVariance(null, $variance);

        $this->assertEqualsWithDelta(
            2.2913,
            $variance->getStandardDeviation(),
            0.0001
        );
    }

    public function testRunningVarianceReuse(): void
    {
        $a = map(fn() => yield from [5, 5, 9, 9]);
        $this->assertSame(28, $a->runningVariance($variance)->fold(0));

        $b = map(fn() => yield from [9, 10, 5, 10, 10]);
        $this->assertSame(44, $b->runningVariance($variance)->fold(0));

        $this->assertEqualsWithDelta(
            2.2913,
            $variance->getStandardDeviation(),
            0.0001
        );
    }

    public function testVarianceCast(): void
    {
        $pipeline = map(fn() => yield from [-10, -20, 5, 5, 9, 9, 9, 10, 5, 10, 10, 100, 200]);

        $variance = $pipeline->finalVariance(static function (int $number): ?float {
            if ($number < 0 || $number > 10) {
                return null;
            }

            return (float) $number;
        });

        $this->assertEqualsWithDelta(
            2.2913,
            $variance->getStandardDeviation(),
            0.0001
        );
    }

    public function testOnlineVariance(): void
    {
        $pipeline = map(fn() => yield from [-10, -20, 5, 5, 9, 9, 9, 10, 5, 10, 10, 100, 200]);

        $pipeline->runningVariance($variance, static function (int $number): ?float {
            if ($number < 0 || $number > 10) {
                return null;
            }

            return (float) $number;
        });

        $this->assertSame(0, $variance->getCount());

        // Now, count all values
        $this->assertSame(13, $pipeline->count());

        // Only valid values are accounted for variance
        $this->assertSame(9, $variance->getCount());

        $this->assertEqualsWithDelta(
            2.2913,
            $variance->getStandardDeviation(),
            0.0001
        );
    }

    public function testFeedVariance(): void
    {
        $pipeline = map(fn() => yield from [5, 5, 9, 9, 9, 10, 5, 10, 10]);

        $variance = new RunningVariance();

        $pipeline->runningVariance($variance, 'floatval');

        $this->assertSame(0, $variance->getCount());

        $this->assertSame(9, $pipeline->count());
        $this->assertSame(9, $variance->getCount());

        $this->assertEqualsWithDelta(
            2.2913,
            $variance->getStandardDeviation(),
            0.0001
        );
    }

    public function testFeedVarianceArray(): void
    {
        $pipeline = fromArray([5, 5, 9, 9, 9, 10, 5, 10, 10]);

        $variance = new RunningVariance();

        $pipeline->runningVariance($variance, 'floatval');

        // Arrays are eagerly processed
        $this->assertSame(9, $variance->getCount());
        $this->assertSame(9, $pipeline->count());
        $this->assertSame(9, $variance->getCount());

        $this->assertEqualsWithDelta(
            2.2913,
            $variance->getStandardDeviation(),
            0.0001
        );
    }
}
