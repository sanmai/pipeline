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

use function ceil;
use function is_object;
use function log;
use function pack;
use function round;
use function serialize;
use function spl_object_hash;
use function str_split;
use function unpack;
use function chr;
use function exp;
use function hash;
use function ord;
use function str_repeat;

/**
 * Bloom filter implementation for probabilistic duplicate detection.
 *
 * This implementation uses a bit array and multiple hash functions to provide
 * space-efficient membership testing with configurable false positive rates.
 */
final class BloomFilter
{
    /**
     * The bit array storing the filter state.
     */
    private string $bits;

    /**
     * Number of bits in the filter.
     */
    private int $size;

    /**
     * Number of hash functions to use.
     */
    private int $hashCount;

    /**
     * Function to convert values to hashable strings.
     *
     * @var callable(mixed): string
     */
    private $keyFunc;

    /**
     * Creates a new Bloom filter with specified parameters.
     *
     * @param int $expectedItems Expected number of items to be added
     * @param float $falsePositiveRate Desired false positive rate (0.0 to 1.0)
     * @param ?callable $keyFunc Function to convert values to hashable strings
     */
    public function __construct(
        int $expectedItems = 1000,
        float $falsePositiveRate = 0.01,
        ?callable $keyFunc = null
    ) {
        // Calculate optimal size and hash count
        $this->size = self::optimalSize($expectedItems, $falsePositiveRate);
        $this->hashCount = self::optimalHashCount($this->size, $expectedItems);

        // Initialize bit array (8 bits per byte)
        $byteCount = (int) ceil($this->size / 8);
        $this->bits = str_repeat("\0", $byteCount);

        // Set key function with smart defaults
        $this->keyFunc = $keyFunc ?? static function ($value): string {
            if (is_object($value)) {
                return spl_object_hash($value);
            }

            return hash('xxh64', serialize($value));
        };
    }

    /**
     * Adds a value to the filter.
     *
     * @param mixed $value The value to add
     * @return bool Always returns true (for consistency with Set-like interfaces)
     */
    public function add($value): bool
    {
        $key = ($this->keyFunc)($value);

        foreach ($this->getHashPositions($key) as $position) {
            $this->setBit($position);
        }

        return true;
    }

    /**
     * Checks if a value might be in the filter.
     *
     * @param mixed $value The value to check
     * @return bool True if the value might be in the set (or definitely is), false if definitely not
     */
    public function mightContain($value): bool
    {
        $key = ($this->keyFunc)($value);

        foreach ($this->getHashPositions($key) as $position) {
            if (!$this->getBit($position)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Adds a value and returns whether it was possibly new.
     * Useful for filtering duplicates in a single operation.
     *
     * @param mixed $value The value to add and check
     * @return bool True if the value was possibly new, false if definitely seen before
     */
    public function addIfNew($value): bool
    {
        $key = ($this->keyFunc)($value);
        $positions = $this->getHashPositions($key);
        $wasNew = false;

        // Check all positions first
        foreach ($positions as $position) {
            if (!$this->getBit($position)) {
                $wasNew = true;
                break;
            }
        }

        // Set all bits
        foreach ($positions as $position) {
            $this->setBit($position);
        }

        return $wasNew;
    }

    /**
     * Calculates optimal bit array size for given parameters.
     * No artificial bounds - if you request impossible parameters,
     * PHP will fail naturally with memory limits.
     */
    private static function optimalSize(int $expectedItems, float $falsePositiveRate): int
    {
        // Formula: m = -n * ln(p) / (ln(2)^2)
        $size = -$expectedItems * log($falsePositiveRate) / (log(2) ** 2);

        return (int) round($size);
    }

    /**
     * Calculates optimal number of hash functions.
     */
    private static function optimalHashCount(int $size, int $expectedItems): int
    {
        // Formula: k = (m/n) * ln(2)
        $hashCount = ($size / $expectedItems) * log(2);

        return (int) round($hashCount);
    }

    /**
     * Generates hash positions for a given key.
     * Uses double hashing to simulate multiple hash functions.
     *
     * @return list<int>
     */
    private function getHashPositions(string $key): array
    {
        $positions = [];

        for ($i = 0; $i < $this->hashCount; $i++) {
            // Use sha1 with different seeds for multiple hash functions
            $hash = hash('sha1', $key . $i, true);
            // Take first 4 bytes as unsigned integer
            $position = unpack('N', $hash)[1];
            // Ensure positive modulo
            $positions[] = $position % $this->size;
        }

        return $positions;
    }

    /**
     * Sets a bit at the given position.
     */
    private function setBit(int $position): void
    {
        $byte = (int) ($position / 8);
        $bit = $position % 8;

        $currentByte = ord($this->bits[$byte]);
        $this->bits[$byte] = chr($currentByte | (1 << $bit));
    }

    /**
     * Gets a bit at the given position.
     */
    private function getBit(int $position): bool
    {
        $byte = (int) ($position / 8);
        $bit = $position % 8;

        return (ord($this->bits[$byte]) & (1 << $bit)) !== 0;
    }

    /**
     * Returns current false positive probability based on items added.
     * This is an estimate - actual rate depends on hash distribution.
     *
     * @param int $itemsAdded Approximate number of items added
     * @return float Estimated false positive rate
     */
    public function currentFalsePositiveRate(int $itemsAdded): float
    {
        // Formula: (1 - e^(-k*n/m))^k
        $exponent = -$this->hashCount * $itemsAdded / $this->size;

        return (1 - exp($exponent)) ** $this->hashCount;
    }
}
