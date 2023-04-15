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

/**
 * Computes statistics (such as standard deviation) in real time.
 *
 * @see https://en.wikipedia.org/wiki/Algorithms_for_calculating_variance#Welford's_online_algorithm
 *
 * @final
 */
class RunningVariance
{
    private const ZERO = 0;

    /** The number of observed values. */
    private int $count = 0;

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

        return $value;
    }

    /**
     * The number of observed values.
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Get the mean value.
     */
    public function getMean(): float
    {
        if (self::ZERO === $this->count) {
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
        if (self::ZERO === $this->count) {
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
        if (self::ZERO === $other->count) {
            return;
        }

        // Avoid division by zero by copying values
        if (self::ZERO === $this->count) {
            $this->count = $other->count;
            $this->mean = $other->mean;
            $this->m2 = $other->m2;

            return;
        }

        $count = $this->count + $other->count;
        $delta = $other->mean - $this->mean;

        $this->mean = ($this->count * $this->mean) / $count + ($other->count * $other->mean) / $count;
        $this->m2 = $this->m2 + $other->m2 + ($delta * $delta * $this->count * $other->count / $count);
        $this->count = $count;
    }
}
