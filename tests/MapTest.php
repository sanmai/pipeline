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

/**
 * @covers \Pipeline\Principal
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class MapTest extends TestCase
{
    public function testMapPreservesKeys(): void
    {
        $this->assertSame([
            'a' => 2,
            'b' => 6,
        ], map(function () {
            yield 'a' => 1;
            yield 'b' => 2;
            yield 'b' => 3;
        })->map(function (int $a) {
            return $a * 2;
        })->toArray(true));
    }
}
