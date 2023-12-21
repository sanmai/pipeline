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

use function Pipeline\map;
use function Pipeline\take;
use function range;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class RunningCountTest extends TestCase
{
    public function testRunningCount(): void
    {
        $countEven = 1;

        $pipeline = map(fn() => yield from range(0, 100))
            ->runningCount($countAll)
            ->filter(fn(int $n) => 0 === $n % 2)
            ->runningCount($countEven)
            ->filter(fn(int $n) => $n % 3);

        $this->assertSame(0, $countAll);
        $this->assertSame(1, $countEven);

        $this->assertSame(34, $pipeline->count());

        $this->assertSame(101, $countAll);
        $this->assertSame(51, $countEven - 1);
    }

    public function testRunningCountLazy(): void
    {
        $countEven = 1;

        $pipeline = map(fn() => yield from range(0, 100))
            ->runningCount($countAll)
            ->filter(fn(int $n) => 0 === $n % 2)
            ->runningCount($countEven)
            ->filter(fn(int $n) => $n % 3);

        $this->assertSame(0, $countAll);
        $this->assertSame(1, $countEven);

        foreach ($pipeline as $item) {
            $this->assertSame(2, $item);

            break;
        }

        // Because we need to inspect 3 numbers to get to 2: filtered out 0 and 1
        $this->assertSame(3, $countAll);
        $this->assertSame(3, $countEven);
    }

    public function testRunningCountArray(): void
    {
        $countEven = 1;

        $pipeline = take(range(0, 100))
            ->runningCount($countAll)
            ->filter(fn(int $n) => 0 === $n % 2)
            ->runningCount($countEven)
            ->filter(fn(int $n) => $n % 3);

        $this->assertSame(101, $countAll);
        $this->assertSame(51, $countEven - 1);

        $this->assertSame(34, $pipeline->count());
    }
}
