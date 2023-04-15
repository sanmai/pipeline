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

use PHPUnit\Framework\TestCase;
use Pipeline\Helper\RunningVariance;
use function abs;
use function array_sum;
use function cos;
use function count;
use function log;
use function mt_getrandmax;
use function mt_rand;
use function Pipeline\take;
use function sin;
use function sqrt;

/**
 * @internal
 *
 * @covers \Pipeline\Helper\RunningVariance
 */
final class RunningVarianceTest extends TestCase
{
    public function testEmpty(): void
    {
        $variance = new RunningVariance();

        $this->assertSame(0, $variance->getCount());
        $this->assertNan($variance->getMean());
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
        $this->assertNan($variance->getVariance());
        $this->assertNan($variance->getStandardDeviation());
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
        $this->assertEqualsWithDelta(5.0, $variance->getVariance(), 0.0001);
        $this->assertEqualsWithDelta(sqrt(5.0), $variance->getStandardDeviation(), 0.0001);
    }

    public function testFiveMergedThrice(): void
    {
        $varianceA = new RunningVariance();
        $varianceA->observe(5.0);
        $varianceA->observe(8.0);
        $varianceA->observe(6.0);

        $varianceB = new RunningVariance();
        $varianceB->observe(4.0);

        $varianceC = new RunningVariance();
        $varianceC->observe(2.0);

        $variance = new RunningVariance($varianceA, $varianceB, $varianceC);

        $this->assertSame(5, $variance->getCount());
        $this->assertSame(5.0, $variance->getMean());
        $this->assertEqualsWithDelta(5.0, $variance->getVariance(), 0.0001);
        $this->assertEqualsWithDelta(sqrt(5.0), $variance->getStandardDeviation(), 0.0001);
    }

    public static function provideRandomNumberCounts(): iterable
    {
        yield ['count' => 900, 'mean' => 8.1, 'sigma' => 1.9];

        yield ['count' => 1190, 'mean' => 729.4, 'sigma' => 4.2];

        yield ['count' => 1500, 'mean' => 3698.41, 'sigma' => 12.9];

        yield ['count' => 25000, 'mean' => 2.34E+21, 'sigma' => 111111001.1];
    }

    /**
     * @coversNothing
     *
     * @dataProvider provideRandomNumberCounts
     */
    public function testNumericStability(int $count, float $mean, float $sigma): void
    {
        $numbers = take(self::getRandomNumbers($mean, $sigma))
            ->slice(0, $count)->toArray();

        $benchmark = self::standard_deviation($numbers);

        $variance = take($numbers)->finalVariance();

        $benchmarkError = abs($sigma - $benchmark);
        $onlineError = abs($sigma - $variance->getStandardDeviation());

        $this->assertLessThanOrEqual(
            $sigma / 50,
            $onlineError - $benchmarkError,
            "Online algorithm deviated for more than 2% from the textbook computation on $count samples"
        );

        $this->assertEqualsWithDelta(
            $sigma,
            $variance->getStandardDeviation(),
            $sigma / 10,
            "Online algorithm deviated from the expected value beyond the expected 10% on $count samples"
        );
    }

    /**
     * @coversNothing
     *
     * @dataProvider provideRandomNumberCounts
     */
    public function testMullerTransform(int $count, float $mean, float $sigma): void
    {
        $numbers = take(self::getRandomNumbers($mean, $sigma))
            ->slice(0, $count)
            ->toArray();

        try {
            $this->assertEqualsWithDelta($sigma, self::standard_deviation(
                $numbers
            ), $sigma / 10);
        } catch (\Throwable $e) {
            if ($mean > 1E10) {
                $this->markTestSkipped($e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * @see https://en.wikipedia.org/wiki/Box%E2%80%93Muller_transform
     *
     * @param float $mean  The target average/mean
     * @param float $sigma The target standard deviation
     *
     * @return iterable<float>
     */
    private static function getRandomNumbers(float $mean, float $sigma): iterable
    {
        $two_pi = 2 * M_PI;
        $epsilon = 1E-6; // Arbitrary number

        while (true) {
            do {
                $u1 = mt_rand() / mt_getrandmax();
            } while ($u1 <= $epsilon);

            $mag = $sigma * sqrt(-2.0 * log($u1));

            $u2 = mt_rand() / mt_getrandmax();

            yield $mag * cos($two_pi * $u2) + $mean;
            yield $mag * sin($two_pi * $u2) + $mean;
        }
    }

    private static function standard_deviation(array $input)
    {
        $mean = array_sum($input) / count($input);

        $carry = take($input)->cast(fn (float $val) => ($val - $mean) ** 2)->reduce();

        return sqrt($carry / count($input));
    }
}
