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
use function round;

/**
 * Tests for PHPStan FilterReturnTypeExtension type narrowing functionality.
 * These tests verify that PHPStan correctly understands type narrowing after filter operations.
 *
 * @coversNothing
 * @group integration
 *
 * @internal
 */
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
        $filtered = $pipeline->filter(is_string(...));

        $result = $filtered
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
        $filtered = $pipeline->filter('is_string');

        $result = $filtered
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
        $filtered = $pipeline->filter(strict: true);

        $result = $filtered
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

    /**
     * Tests callback filtering on already narrow (non-union) types.
     * This tests our assumption about whether non-union types need narrowing.
     */
    public function testFilterNonUnionTypeWithMatchingCallback(): void
    {
        /** @var Standard<int, string> $pipeline */
        $pipeline = take(['hello', 'world', 'test']);

        // Filter strings with is_string - should preserve all values
        $filtered = $pipeline->filter('is_string');

        $result = $filtered
            ->cast(fn(string $s) => strtoupper($s))
            ->toList();

        $this->assertSame(['HELLO', 'WORLD', 'TEST'], $result);
    }

    /**
     * Tests callback filtering on narrow types with non-matching callback.
     * This should result in an empty result set.
     */
    public function testFilterNonUnionTypeWithNonMatchingCallback(): void
    {
        /** @var Standard<int, string> $pipeline */
        $pipeline = take(['hello', 'world']);

        // Filter strings with is_int - should result in empty
        $filtered = $pipeline->filter('is_int');

        $result = $filtered->toList();
        $this->assertSame([], $result);
    }

    /**
     * Tests that first-class callables work with non-union types.
     */
    public function testFilterNonUnionTypeWithFirstClassCallable(): void
    {
        /** @var Standard<int, float> $pipeline */
        $pipeline = take([1.5, 2.7, 3.14]);

        $filtered = $pipeline->filter(is_float(...));

        $result = $filtered
            ->map(fn(float $f) => yield round($f))
            ->toList();

        $this->assertSame([2.0, 3.0, 3.0], $result);
    }

    /**
     * Tests edge case: filtering with a callback that could match some values.
     * This tests the boundary between our assumptions.
     */
    public function testFilterMixedTypesWithSpecificCallback(): void
    {
        /** @var Standard<int, int|float|string> $pipeline */
        $pipeline = take([1, 2.5, 'hello', 42, 3.14, 'world']);

        // Filter for only numeric types (int|float)
        $numericOnly = $pipeline->filter('is_numeric');
        // Should narrow to approximately int|float, though PHPStan might not be that precise

        $result = $numericOnly->toList();
        $this->assertSame([1, 2.5, 42, 3.14], $result);

        // Then filter the numeric for just ints
        $intOnly = $pipeline->filter(is_int(...));

        $intResult = $intOnly->toList();
        $this->assertSame([1, 42], $intResult);
    }

    /**
     * Tests strict mode behavior with already narrow types.
     */
    public function testStrictModeWithNonUnionTypes(): void
    {
        /** @var Standard<int, string|null> $pipeline */
        $pipeline = take(['hello', null, 'world']);

        $strict = $pipeline->filter(strict: true);

        $result = $strict->toList();
        $this->assertSame(['hello', 'world'], $result);
    }

    /**
     * Tests that our extension handles callback precedence over strict mode.
     */
    public function testCallbackPrecedenceOverStrictMode(): void
    {
        /** @var Standard<int, string|null|false> $pipeline */
        $pipeline = take(['hello', null, false, 'world', '']);

        // Even though strict: true would remove null and false,
        // the callback should take precedence and keep empty string
        $result = $pipeline
            ->filter('is_string', strict: true)
            ->toList();

        // Should keep all strings including empty string
        $this->assertSame(['hello', 'world', ''], $result);
    }

    /**
     * Tests the assumption that default filter behavior works correctly.
     */
    public function testDefaultFilterWithVariousFalsyValues(): void
    {
        /** @var Standard<int, mixed> $pipeline */
        $pipeline = take([
            0,          // falsy int
            0.0,        // falsy float
            '',         // falsy string
            '0',        // falsy string
            [],         // falsy array
            false,      // falsy boolean
            null,       // falsy null
            'hello',    // truthy string
            1,          // truthy int
            [1],        // truthy array
            true,        // truthy boolean
        ]);

        // Default filter should remove all falsy values
        $result = $pipeline->filter()->toList();

        $this->assertSame(['hello', 1, [1], true], $result);
    }

    /**
     * Tests assumption about key preservation during filtering.
     */
    public function testKeyPreservationDuringFiltering(): void
    {
        /** @var Standard<string, int|null> $pipeline */
        $pipeline = take(['a' => 1, 'b' => null, 'c' => 2, 'd' => null, 'e' => 3]);

        $result = $pipeline
            ->filter(strict: true)
            ->toAssoc();

        $this->assertSame(['a' => 1, 'c' => 2, 'e' => 3], $result);
    }

    /**
     * Tests our assumption about nested generics and complex types.
     */
    public function testComplexGenericTypeNarrowing(): void
    {
        /** @var Standard<int, array<string>|null> $pipeline */
        $pipeline = take([
            ['hello', 'world'],
            null,
            ['foo', 'bar'],
            null,
            ['test'],
        ]);

        $filtered = $pipeline->filter('is_array');
        // Should narrow to Standard<int, array<string>>

        $result = $filtered
            ->map(fn(array $arr) => yield count($arr))
            ->toList();

        $this->assertSame([2, 2, 1], $result);
    }
}
