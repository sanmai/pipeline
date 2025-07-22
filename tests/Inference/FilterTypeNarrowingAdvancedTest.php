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

namespace Tests\Pipeline\Inference;

use PHPUnit\Framework\TestCase;
use Pipeline\Standard;
use DateTime;
use stdClass;

use function Pipeline\take;
use function PHPStan\Testing\assertType;

/**
 * Advanced tests for PHPStan FilterReturnTypeExtension to achieve 100% mutation coverage.
 *
 * @coversNothing
 * @group integration
 * @internal
 */
class FilterTypeNarrowingAdvancedTest extends TestCase
{
    public function testFilterWithIsArray(): void
    {
        /** @var Standard<int, string|array<mixed>|int> $pipeline */
        $pipeline = take(['hello', [1, 2], 42, ['a', 'b']]);
        assertType('Pipeline\Standard<int, array<mixed>|int|string>', $pipeline);

        $filtered = $pipeline->filter('is_array');
        assertType('Pipeline\Standard<int, array<mixed>>', $filtered);

        $result = $filtered->toList();
        $this->assertSame([[1, 2], ['a', 'b']], $result);
    }

}
