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

namespace Tests\Pipeline\Helper;

use const M_PI;
use const NAN;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pipeline\Helper\RunningVariance;

use function sqrt;

/**
 * @internal
 */
#[CoversClass(RunningVariance::class)]
final class RunningVarianceTest extends TestCase
{
    public function testEmpty(): void
    {
        $variance = new RunningVariance();

        $this->assertSame(0, $variance->getCount());
        $this->assertNan($variance->getMean());
        $this->assertNan($variance->getMin());
        $this->assertNan($variance->getMax());
        $this->assertNan($variance->getVariance());
        $this->assertNan($variance->getStandardDeviation());
    }

    public function testEmptyPlusEmpty(): void
    {
        $variance = new RunningVariance(new RunningVariance());

        $this->assertSame(0, $variance->getCount());
        $this->assertNan($variance->getMean());
        $this->assertNan($variance->getVariance());
        $this->assertNan($variance->getStandardDeviation());
    }

    public function testOne(): void
    {
        $variance = new RunningVariance();
        $variance->observe(M_PI);

        $this->assertSame(1, $variance->getCount());
        $this->assertSame(M_PI, $variance->getMean());
        $this->assertSame(M_PI, $variance->getMin());
        $this->assertSame(M_PI, $variance->getMax());
        $this->assertSame(0.0, $variance->getVariance());
        $this->assertSame(0.0, $variance->getStandardDeviation());
    }

    public function testOneNegative(): void
    {
        $variance = new RunningVariance();
        $variance->observe(-1.01);

        $this->assertSame(1, $variance->getCount());
        $this->assertSame(-1.01, $variance->getMean());
        $this->assertSame(-1.01, $variance->getMin());
        $this->assertSame(-1.01, $variance->getMax());
        $this->assertSame(0.0, $variance->getVariance());
        $this->assertSame(0.0, $variance->getStandardDeviation());
    }


    public function testTwo(): void
    {
        $variance = new RunningVariance();
        $variance->observe(M_PI);
        $variance->observe(M_PI);

        $this->assertSame(2, $variance->getCount());
        $this->assertSame(M_PI, $variance->getMean());
        $this->assertSame(M_PI, $variance->getMin());
        $this->assertSame(M_PI, $variance->getMax());
        $this->assertSame(0.0, $variance->getVariance());
        $this->assertSame(0.0, $variance->getStandardDeviation());
    }

    public function testNAN(): void
    {
        $variance = new RunningVariance();
        $variance->observe(M_PI);
        $variance->observe(NAN);

        $this->assertSame(2, $variance->getCount());
        $this->assertNan($variance->getMean());
        $this->assertSame(M_PI, $variance->getMin());
        $this->assertSame(M_PI, $variance->getMax());
        $this->assertNan($variance->getVariance());
        $this->assertNan($variance->getStandardDeviation());
    }

    /**
     * Regression test for https://github.com/php/php-src/issues/20880
     *
     * PHP JIT (tracing) incorrectly evaluates NAN > $float as TRUE.
     * This test warms up JIT and verifies NAN doesn't corrupt min/max.
     */
    public function testNANWithJITWarmup(): void
    {
        // Warm up JIT with normal float operations
        for ($i = 0; $i < 10000; $i++) {
            $v = new RunningVariance();
            $v->observe(1.0);
            $v->observe(2.0);
            $v->observe(3.0);
        }

        // Now test NAN handling - should not corrupt min/max
        $variance = new RunningVariance();
        $variance->observe(M_PI);
        $variance->observe(NAN);

        // Per IEEE 754, NAN > M_PI should be FALSE, so max stays M_PI
        $this->assertSame(M_PI, $variance->getMin(), 'NAN corrupted min value');
        $this->assertSame(M_PI, $variance->getMax(), 'NAN corrupted max value');

        // Test NAN as first observation (min/max are NAN initially)
        $variance2 = new RunningVariance();
        $variance2->observe(NAN);
        $this->assertNan($variance2->getMin(), 'First NAN observation should set min to NAN');
        $this->assertNan($variance2->getMax(), 'First NAN observation should set max to NAN');

        // With workaround: valid values update NAN min/max
        $variance2->observe(M_PI);
        $this->assertSame(M_PI, $variance2->getMin(), 'Valid value updates NAN min');
        $this->assertSame(M_PI, $variance2->getMax(), 'Valid value updates NAN max');

        // Test multiple NANs don't corrupt valid min/max
        $variance3 = new RunningVariance();
        $variance3->observe(2.0);
        $variance3->observe(NAN);
        $variance3->observe(NAN);
        $variance3->observe(4.0);
        $this->assertSame(2.0, $variance3->getMin(), 'Multiple NANs should not corrupt min');
        $this->assertSame(4.0, $variance3->getMax(), 'Valid value after NANs should update max');

        // Test merge() with NAN values
        $withNan = new RunningVariance();
        $withNan->observe(NAN);

        $withValid = new RunningVariance();
        $withValid->observe(5.0);

        // Merging NAN into valid should not corrupt
        $merged1 = new RunningVariance($withValid, $withNan);
        $this->assertSame(5.0, $merged1->getMin(), 'Merge: NAN should not corrupt valid min');
        $this->assertSame(5.0, $merged1->getMax(), 'Merge: NAN should not corrupt valid max');

        // Merging valid into NAN should update
        $merged2 = new RunningVariance($withNan, $withValid);
        $this->assertSame(5.0, $merged2->getMin(), 'Merge: valid should update NAN min');
        $this->assertSame(5.0, $merged2->getMax(), 'Merge: valid should update NAN max');
    }

    public function testFive(): void
    {
        $variance = new RunningVariance();
        $variance->observe(4.0);
        $variance->observe(2.0);
        $variance->observe(5.0);
        $variance->observe(8.0);
        $variance->observe(6.0);

        $this->assertSame(5, $variance->getCount());
        $this->assertSame(5.0, $variance->getMean());
        $this->assertSame(2.0, $variance->getMin());
        $this->assertSame(8.0, $variance->getMax());
        $this->assertEqualsWithDelta(5.0, $variance->getVariance(), 0.0001);
        $this->assertEqualsWithDelta(sqrt(5.0), $variance->getStandardDeviation(), 0.0001);
    }

    public function testCopy(): void
    {
        $variance = new RunningVariance();
        $variance->observe(4.0);
        $variance->observe(2.0);
        $variance->observe(5.0);
        $variance->observe(8.0);
        $variance->observe(6.0);

        $variance = new RunningVariance($variance);

        $this->assertSame(5, $variance->getCount());
        $this->assertSame(5.0, $variance->getMean());
        $this->assertSame(2.0, $variance->getMin());
        $this->assertSame(8.0, $variance->getMax());
        $this->assertEqualsWithDelta(5.0, $variance->getVariance(), 0.0001);
        $this->assertEqualsWithDelta(sqrt(5.0), $variance->getStandardDeviation(), 0.0001);
    }

    public function testFiveMerged(): void
    {
        $variance = new RunningVariance();
        $variance->observe(4.0);
        $variance->observe(2.0);

        $variance = new RunningVariance($variance);
        $variance->observe(5.0);
        $variance->observe(8.0);
        $variance->observe(6.0);

        $this->assertSame(5, $variance->getCount());
        $this->assertSame(5.0, $variance->getMean());
        $this->assertSame(2.0, $variance->getMin());
        $this->assertSame(8.0, $variance->getMax());
        $this->assertEqualsWithDelta(5.0, $variance->getVariance(), 0.0001);
        $this->assertEqualsWithDelta(sqrt(5.0), $variance->getStandardDeviation(), 0.0001);
    }

    public function testFiveMergedTwice(): void
    {
        $varianceA = new RunningVariance();
        $varianceA->observe(5.0);
        $varianceA->observe(8.0);
        $varianceA->observe(6.0);

        $varianceB = new RunningVariance();
        $varianceB->observe(4.0);
        $varianceB->observe(2.0);

        $variance = new RunningVariance($varianceA, $varianceB);

        $this->assertSame(5, $variance->getCount());
        $this->assertSame(5.0, $variance->getMean());
        $this->assertSame(2.0, $variance->getMin());
        $this->assertSame(8.0, $variance->getMax());
        $this->assertEqualsWithDelta(5.0, $variance->getVariance(), 0.0001);
        $this->assertEqualsWithDelta(sqrt(5.0), $variance->getStandardDeviation(), 0.0001);
    }

    public function testFiveMergedThrice(): void
    {
        $varianceA = new RunningVariance();
        $varianceA->observe(5.0);
        $varianceA->observe(2.0);
        $varianceA->observe(6.0);

        $varianceB = new RunningVariance();
        $varianceB->observe(4.0);

        $varianceC = new RunningVariance();
        $varianceC->observe(8.0);

        $variance = new RunningVariance($varianceA, $varianceB, $varianceC);

        $this->assertSame(5, $variance->getCount());
        $this->assertSame(5.0, $variance->getMean());
        $this->assertSame(2.0, $variance->getMin());
        $this->assertSame(8.0, $variance->getMax());
        $this->assertEqualsWithDelta(5.0, $variance->getVariance(), 0.0001);
        $this->assertEqualsWithDelta(sqrt(5.0), $variance->getStandardDeviation(), 0.0001);
    }
}
