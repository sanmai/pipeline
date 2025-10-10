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

use function Pipeline\take;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class PeekTest extends TestCase
{
    public function testPeekNonDestructiveFromArray(): void
    {
        $pipeline = take([1, 2, 3, 4, 5]);
        $peeked = $pipeline->peek(3);

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([1, 2, 3, 4, 5], $pipeline->toList());
    }

    public function testPeekDestructiveFromArray(): void
    {
        $pipeline = take([1, 2, 3, 4, 5]);
        $peeked = $pipeline->peek(3, consume: true);

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([4, 5], $pipeline->toList());
    }

    public function testPeekNonDestructiveFromGenerator(): void
    {
        $pipeline = take(self::xrange(1, 5));
        $peeked = $pipeline->peek(3);

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([1, 2, 3, 4, 5], $pipeline->toList());
    }

    public function testPeekDestructiveFromGenerator(): void
    {
        $pipeline = take(self::xrange(1, 5));
        $peeked = $pipeline->peek(3, consume: true);

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([4, 5], $pipeline->toList());
    }

    public function testPeekMoreThanAvailable(): void
    {
        $pipeline = take([1, 2, 3]);
        $peeked = $pipeline->peek(10);

        $this->assertSame([1, 2, 3], $peeked);
        $this->assertSame([1, 2, 3], $pipeline->toList());
    }

    public function testPeekFromEmptyPipeline(): void
    {
        $pipeline = take([]);
        $peeked = $pipeline->peek(5);

        $this->assertSame([], $peeked);
        $this->assertSame([], $pipeline->toList());
    }

    public function testPeekWithZeroCount(): void
    {
        $pipeline = take([1, 2, 3]);
        $peeked = $pipeline->peek(0);

        $this->assertSame([], $peeked);
        $this->assertSame([1, 2, 3], $pipeline->toList());
    }

    public function testPeekWithNegativeCount(): void
    {
        $pipeline = take([1, 2, 3]);
        $peeked = $pipeline->peek(-5);

        $this->assertSame([], $peeked);
        $this->assertSame([1, 2, 3], $pipeline->toList());
    }

    public function testPeekPreservesKeys(): void
    {
        $pipeline = take(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        $peeked = $pipeline->peek(2);

        $this->assertSame(['a' => 1, 'b' => 2], $peeked);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4], $pipeline->toAssoc());
    }

    public function testPeekPreservesKeysWhenConsuming(): void
    {
        $pipeline = take(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        $peeked = $pipeline->peek(2, consume: true);

        $this->assertSame(['a' => 1, 'b' => 2], $peeked);
        $this->assertSame(['c' => 3, 'd' => 4], $pipeline->toAssoc());
    }

    public function testMultipleSequentialPeeksNonDestructive(): void
    {
        $pipeline = take([1, 2, 3, 4, 5]);

        $first = $pipeline->peek(2);
        $this->assertSame([1, 2], $first);

        $second = $pipeline->peek(3);
        $this->assertSame([1, 2, 3], $second);

        $this->assertSame([1, 2, 3, 4, 5], $pipeline->toList());
    }

    public function testMultipleSequentialPeeksDestructive(): void
    {
        $pipeline = take([1, 2, 3, 4, 5]);

        $first = $pipeline->peek(2, consume: true);
        $this->assertSame([1, 2], $first);

        $second = $pipeline->peek(2, consume: true, preserve_keys: true);
        $this->assertSame([2 => 3, 3 => 4], $second);

        $this->assertSame([5], $pipeline->toList());
    }

    public function testPeekMixedConsume(): void
    {
        $pipeline = take([1, 2, 3, 4, 5]);

        $first = $pipeline->peek(2);
        $this->assertSame([1, 2], $first);

        $second = $pipeline->peek(2, consume: true);
        $this->assertSame([1, 2], $second);

        $this->assertSame([3, 4, 5], $pipeline->toList());
    }

    public function testPeekDestructivePreservesKeysInRemainingPipeline(): void
    {
        $pipeline = take(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        $peeked = $pipeline->peek(2, consume: true);

        $this->assertSame(['a' => 1, 'b' => 2], $peeked);
        $this->assertSame(['c' => 3, 'd' => 4], $pipeline->toAssoc());
    }

    private static function xrange(int $start, int $end): iterable
    {
        for ($i = $start; $i <= $end; $i++) {
            yield $i;
        }
    }
}
