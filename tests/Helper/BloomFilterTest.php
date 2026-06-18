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
use Pipeline\Helper\BloomFilter;
use stdClass;

/**
 * @covers \Pipeline\Helper\BloomFilter
 *
 * @internal
 */
final class BloomFilterTest extends TestCase
{
    public function testBasicFunctionality(): void
    {
        $filter = new BloomFilter(100, 0.01);

        // Test with strings
        $this->assertTrue($filter->add('hello'));
        $this->assertTrue($filter->mightContain('hello'));
        $this->assertFalse($filter->mightContain('world'));

        // Add more items
        $filter->add('world');
        $this->assertTrue($filter->mightContain('world'));
    }

    public function testAddIfNew(): void
    {
        $filter = new BloomFilter(100, 0.01);

        // First addition should return true (possibly new)
        $this->assertTrue($filter->addIfNew('test'));

        // Second addition should return false (definitely seen)
        $this->assertFalse($filter->addIfNew('test'));
    }

    public function testWithObjects(): void
    {
        $filter = new BloomFilter(100, 0.01);

        $obj1 = new stdClass();
        $obj1->id = 1;

        $obj2 = new stdClass();
        $obj2->id = 2;

        $filter->add($obj1);
        $this->assertTrue($filter->mightContain($obj1));
        $this->assertFalse($filter->mightContain($obj2));
    }

    public function testCustomKeyFunction(): void
    {
        // Use a key function that extracts the 'id' property
        $keyFunc = static fn($obj) => (string) $obj->id;
        $filter = new BloomFilter(100, 0.01, $keyFunc);

        $user1 = new stdClass();
        $user1->id = 123;
        $user1->name = 'Alice';

        $user2 = new stdClass();
        $user2->id = 123;
        $user2->name = 'Bob';

        $user3 = new stdClass();
        $user3->id = 456;
        $user3->name = 'Charlie';

        $filter->add($user1);

        // Same ID, different object - should be detected
        $this->assertTrue($filter->mightContain($user2));

        // Different ID - should not be detected
        $this->assertFalse($filter->mightContain($user3));
    }

    public function testNoFalseNegatives(): void
    {
        $filter = new BloomFilter(1000, 0.01);
        $items = [];

        // Add 100 items
        for ($i = 0; $i < 100; $i++) {
            $item = "item_$i";
            $items[] = $item;
            $filter->add($item);
        }

        // All added items must be detected (no false negatives)
        foreach ($items as $item) {
            $this->assertTrue(
                $filter->mightContain($item),
                "Item '$item' should be detected (no false negatives allowed)"
            );
        }
    }

    public function testFalsePositiveRate(): void
    {
        $expectedItems = 1000;
        $targetFPR = 0.01;
        $filter = new BloomFilter($expectedItems, $targetFPR);

        // Add exactly the expected number of items
        for ($i = 0; $i < $expectedItems; $i++) {
            $filter->add("item_$i");
        }

        // Test false positive rate with items that were NOT added
        $falsePositives = 0;
        $testCount = 10000;

        for ($i = $expectedItems; $i < $expectedItems + $testCount; $i++) {
            if ($filter->mightContain("item_$i")) {
                $falsePositives++;
            }
        }

        $actualFPR = $falsePositives / $testCount;

        // Allow some variance (2x the target rate)
        $this->assertLessThan(
            $targetFPR * 2,
            $actualFPR,
            "False positive rate ($actualFPR) should be close to target ($targetFPR)"
        );
    }

    public function testCurrentFalsePositiveRate(): void
    {
        $filter = new BloomFilter(1000, 0.01);

        // Initially, FPR should be near 0
        $this->assertLessThan(0.001, $filter->currentFalsePositiveRate(0));

        // As we add items, FPR should increase
        $fpr100 = $filter->currentFalsePositiveRate(100);
        $fpr500 = $filter->currentFalsePositiveRate(500);
        $fpr1000 = $filter->currentFalsePositiveRate(1000);

        $this->assertLessThan($fpr500, $fpr100);
        $this->assertLessThan($fpr1000, $fpr500);
        $this->assertLessThan(0.02, $fpr1000); // Should be close to target
    }
}
