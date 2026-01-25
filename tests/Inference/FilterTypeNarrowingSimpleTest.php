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

use function count;
use function Pipeline\take;
use function strlen;
use function strtoupper;

/**
 * Tests for PHPStan FilterReturnTypeExtension type narrowing functionality.
 * These tests verify that PHPStan correctly understands type narrowing after filter operations.
 *
 *
 * @internal
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
#[\PHPUnit\Framework\Attributes\Group('integration')]
class FilterTypeNarrowingSimpleTest extends TestCase
{
    /**
     * Tests that is_string(...) first-class callable narrows types correctly.
     */
    public function testFilterWithIsStringFirstClassCallable(): void
    {
        /** @var Standard<int, array<mixed>|string|false> $pipeline */
        $pipeline = take(['hello', ['array'], false, 'world']);

        // After filter(is_string(...)), PHPStan should know this contains only strings
        $result = $pipeline
            ->filter(is_string(...))
            ->cast(fn(string $s) => strtoupper($s))
            ->toList();

        $this->assertSame(['HELLO', 'WORLD'], $result);
    }

    /**
     * Tests that string callback 'is_string' narrows types correctly.
     */
    public function testFilterWithIsStringCallback(): void
    {
        /** @var Standard<int, int|string|null> $pipeline */
        $pipeline = take([1, 'hello', null, 'world', 42]);

        // After filter('is_string'), PHPStan should know this contains only strings
        $result = $pipeline
            ->filter('is_string')
            ->cast(fn(string $s) => strlen($s))
            ->toList();

        $this->assertSame([5, 5], $result);
    }

    /**
     * Tests that filter(strict: true) removes only null and false values.
     */
    public function testFilterStrictModeRemovesNullAndFalse(): void
    {
        /** @var Standard<int, string|null|false> $pipeline */
        $pipeline = take(['hello', null, false, 'world', '']);

        // After filter(strict: true), PHPStan should know null and false are removed
        $result = $pipeline
            ->filter(strict: true)
            ->map(fn(string $s) => yield strlen($s))
            ->toList();

        $this->assertSame([5, 5, 0], $result);
    }

    /**
     * Tests that filter(strict: true) keeps falsy values that aren't null or false.
     */
    public function testFilterStrictModeKeepsFalsyValues(): void
    {
        $pipeline = take([null, 0, '', '0', 'hello', false, 0.0, []]);

        // Strict mode should only remove null and false
        $result = $pipeline
            ->filter(strict: true)
            ->toList();

        $this->assertSame([0, '', '0', 'hello', 0.0, []], $result);
    }

    /**
     * Tests that is_int(...) narrows types correctly.
     */
    public function testFilterWithIsInt(): void
    {
        /** @var Standard<int, int|string|float> $pipeline */
        $pipeline = take([1, 'hello', 3.14, 42, 'world', 2.71]);

        // After filter(is_int(...)), PHPStan should know this contains only ints
        $result = $pipeline
            ->filter(is_int(...))
            ->map(fn(int $n) => yield $n * $n)
            ->toList();

        $this->assertSame([1, 1764], $result);
    }

    /**
     * Tests that 'is_array' string callback narrows types correctly.
     */
    public function testFilterWithIsArrayCallback(): void
    {
        /** @var Standard<int, array<mixed>|string|null> $pipeline */
        $pipeline = take([[1, 2], 'hello', null, [3, 4, 5]]);

        // After filter('is_array'), PHPStan should know this contains only arrays
        $result = $pipeline
            ->filter('is_array')
            ->cast(fn(array $arr) => count($arr))
            ->toList();

        $this->assertSame([2, 3], $result);
    }

    /**
     * Tests that multiple filters work together for type narrowing.
     */
    public function testChainedFilters(): void
    {
        /** @var Standard<int, int|string|null> $pipeline */
        $pipeline = take([1, 'hello', null, 42, 'world']);

        // Chain filters: first remove nulls, then keep only strings
        $result = $pipeline
            ->filter(strict: true)  // Removes null
            ->filter(is_string(...))  // Keeps only strings
            ->cast(fn(string $s) => strtoupper($s))
            ->toList();

        $this->assertSame(['HELLO', 'WORLD'], $result);
    }

    /**
     * Tests that the extension works with complex union types.
     */
    public function testComplexUnionTypeNarrowing(): void
    {
        /** @var Standard<int, int|string|false|null> $pipeline */
        $pipeline = take([1, 'hello', false, null, 42, 'world']);

        // First remove null and false, then keep only strings
        $result = $pipeline
            ->filter(strict: true)
            ->filter(is_string(...))
            ->toList();

        $this->assertSame(['hello', 'world'], $result);
    }
}
