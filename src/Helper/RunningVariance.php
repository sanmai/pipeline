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

namespace Pipeline\Helper;

use function sqrt;

use const NAN;

/**
 * Computes statistics (such as standard deviation) in real time.
 *
 * @see https://en.wikipedia.org/wiki/Algorithms_for_calculating_variance#Welford's_online_algorithm
 *
 * @final
 */
class RunningVariance
{
    /**
     * The number of observed values.
     *
     * @var int<0, max>
     */
    private int $count = 0;

    /** The smallest observed value */
    private float $min = NAN;

    /** The largest observed value */
    private float $max = NAN;

    /** First moment: the mean value. */
    private float $mean = 0.0;

    /** Second moment: the aggregated squared distance from the mean. */
    private float $m2 = 0.0;

    public function __construct(self ...$spiesToMerge)
    {
        foreach ($spiesToMerge as $spy) {
            $this->merge($spy);
        }
    }

    public function observe(float $value): float
    {
        ++$this->count;

        $delta = $value - $this->mean;

        $this->mean += $delta / $this->count;
        $this->m2 += $delta * ($value - $this->mean);

        if (1 === $this->count) {
            $this->min = $value;
            $this->max = $value;
        } else {
            if ($value < $this->min) {
                $this->min = $value;
            }

            if ($value > $this->max) {
                $this->max = $value;
            }
        }

        return $value;
    }

    /**
     * The number of observed values.
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /** The smallest observed value */
    public function getMin(): float
    {
        return $this->min;
    }

    /** The largest observed value */
    public function getMax(): float
    {
        return $this->max;
    }

    /**
     * Get the mean value.
     */
    public function getMean(): float
    {
        if (0 === $this->count) {
            // For no values the variance is undefined.
            return NAN;
        }

        return $this->mean;
    }

    /**
     * Get the variance.
     */
    public function getVariance(): float
    {
        if (0 === $this->count) {
            // For no values the variance is undefined.
            return NAN;
        }

        if (1 === $this->count) {
            // Avoiding division by zero: variance for one value is zero.
            return 0.0;
        }

        // https://en.wikipedia.org/wiki/Bessel%27s_correction
        return $this->m2 / ($this->count - 1);
    }

    /**
     * Compute the standard deviation.
     */
    public function getStandardDeviation(): float
    {
        return sqrt($this->getVariance());
    }

    /**
     * Merge another instance into this instance.
     *
     * @see https://en.wikipedia.org/wiki/Algorithms_for_calculating_variance#Parallel_algorithm
     */
    private function merge(self $other): void
    {
        // Shortcut a no-op
        if (0 === $other->count) {
            return;
        }

        // Avoid division by zero by copying values
        if (0 === $this->count) {
            $this->count = $other->count;
            $this->mean = $other->mean;
            $this->m2 = $other->m2;
            $this->min = $other->min;
            $this->max = $other->max;

            return;
        }

        $count = $this->count + $other->count;
        $delta = $other->mean - $this->mean;

        $this->mean = ($this->count * $this->mean) / $count + ($other->count * $other->mean) / $count;
        $this->m2 = $this->m2 + $other->m2 + ($delta ** 2 * $this->count * $other->count / $count);
        $this->count = $count;

        if ($other->min < $this->min) {
            $this->min = $other->min;
        }

        if ($other->max > $this->max) {
            $this->max = $other->max;
        }
    }
}
