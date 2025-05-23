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

use function ob_end_clean;
use function ob_start;
use function Pipeline\take;

/**
 * @coversNothing
 *
 * @internal
 */
final class ExampleTest extends TestCase
{
    public function testExample(): void
    {
        // These variables will be set inside example.php
        $value = null;
        $sum = null;
        $result = null;
        $arrayResult = null;

        ob_start();

        include 'example.php';
        ob_end_clean();

        $this->assertSame(104, $value);
        $this->assertSame(6, $sum);
        $this->assertSame([22, 42, 62], $result);
        $this->assertSame([3, 3], $arrayResult);
    }

    public function testReadmeExample()
    {
        function oneToMillion(): iterable
        {
            for ($i = 1; $i <= 1000000; $i++) {
                yield $i;
            }
        }

        $result = take(oneToMillion())
            ->map(fn($i) => $i * 2)
            ->filter(fn($i) => 0 === $i % 3)
            ->slice(0, 5)
            ->toArray();

        $this->assertSame([6, 12, 18, 24, 30], $result);
    }
}
