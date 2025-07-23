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
use Generator;

use function Pipeline\take;
use function fclose;
use function fopen;
use function is_resource;
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
        $filtered = $pipeline->filter('is_array');

        $result = $filtered->toList();
        $this->assertSame([[1, 2], ['a', 'b']], $result);
    }

    /**
     * Tests filtering with object types - a case we might not handle properly.
     */
    public function testFilterWithComplexObjectTypes(): void
    {
        $dt1 = new DateTime('2023-01-01');
        $dt2 = new DateTime('2023-12-31');
        $obj = new stdClass();

        /** @var Standard<int, DateTime|string|stdClass> $pipeline */
        $pipeline = take([$dt1, 'hello', $obj, $dt2, 'world']);

        // Filter for objects - should keep all objects (DateTime and stdClass)
        $filtered = $pipeline->filter('is_object');

        // PHPStan should understand $filtered contains only objects
        assertType('Pipeline\\Standard<int, DateTime|stdClass>', $filtered);

        $result = $filtered->toList();
        $this->assertCount(3, $result);
        $this->assertSame([$dt1, $obj, $dt2], $result);
    }

    /**
     * Tests our assumption about resource types and other exotic types.
     */
    public function testFilterWithExoticTypes(): void
    {
        // Create a resource (file handle)
        $resource = fopen('php://memory', 'r');
        $this->assertIsResource($resource, 'Failed to create memory resource');

        /** @var Standard<int, mixed> $pipeline */
        $pipeline = take(['string', 42, $resource, 3.14, true]);

        // Test filtering for resources - not directly supported by our type map
        $resourceFiltered = $pipeline->filter(fn($value) => is_resource($value));

        // PHPStan can't narrow to resource type with custom callback
        assertType('Pipeline\\Standard<int, mixed>', $resourceFiltered);

        $result = $resourceFiltered->toList();
        $this->assertSame([$resource], $result);

        fclose($resource);
    }

    /**
     * Tests assumption about callback vs strict mode precedence with edge cases.
     */
    public function testCallbackStrictModePrecedenceWithEdgeCases(): void
    {
        /** @var Standard<int, string|int|null|false> $pipeline */
        $pipeline = take(['', '0', 0, null, false, 'hello', 42]);

        // Callback should take precedence - keep all strings (including falsy ones)
        $result = $pipeline
            ->filter('is_string', strict: true)
            ->toList();

        // Should include '', '0' but not 0, null, false
        $this->assertSame(['', '0', 'hello'], $result);
    }

    /**
     * Tests our assumption about how we handle unknown callbacks.
     */
    public function testFilterWithUnknownCallback(): void
    {
        /** @var Standard<int, string|int> $pipeline */
        $pipeline = take(['hello', 42, 'world', 13]);

        // Use a callback that's not in our type map
        $result = $pipeline
            ->filter('ctype_alpha')  // Not in our FilterTypeNarrowingHelper::getTypeMap()
            ->toList();

        // Should still work at runtime, but PHPStan won't narrow the type
        $this->assertSame(['hello', 'world'], $result);
    }

    /**
     * Tests our assumption about callable handling in various forms.
     */
    public function testFilterWithDifferentCallableFormats(): void
    {
        /** @var Standard<int, mixed> $pipeline */
        $pipeline = take([1, 'hello', [1, 2], 3.14, false]);

        // Test various callable formats
        $strings1 = $pipeline->filter('is_string')->toList();
        $strings2 = $pipeline->filter(is_string(...))->toList();

        // Both should produce the same result
        $this->assertSame(['hello'], $strings1);
        $this->assertSame(['hello'], $strings2);
    }

    /**
     * Tests assumption about how multiple type checks interact.
     */
    public function testMultipleTypeChecksInteraction(): void
    {
        /** @var Standard<int, int|float|string|null> $pipeline */
        $pipeline = take([1, 2.5, 'hello', null, 42, 3.14, 'world']);

        // Chain multiple specific filters
        $result = $pipeline
            ->filter('is_numeric')  // Should keep int, float (and numeric strings if any)
            ->filter(is_float(...)) // Then narrow to just float
            ->toList();

        $this->assertSame([2.5, 3.14], $result);
    }

    /**
     * Tests our assumption about empty pipeline behavior.
     */
    public function testFilterOnEmptyPipeline(): void
    {
        /** @var Standard<int, string> $pipeline */
        $pipeline = take([]);

        $result = $pipeline
            ->filter('is_string')
            ->toList();

        $this->assertSame([], $result);
    }

    /**
     * Tests assumption about how filtering interacts with generators.
     */
    public function testFilterWithGeneratorData(): void
    {
        $generator = function (): Generator {
            yield 'hello';
            yield 42;
            yield null;
            yield 'world';
            yield false;
        };

        /** @var Standard<int, mixed> $pipeline */
        $pipeline = take($generator());

        $result = $pipeline
            ->filter(strict: true)  // Remove null, false
            ->filter('is_string')   // Keep only strings
            ->toList();

        $this->assertSame(['hello', 'world'], $result);
    }

    /**
     * Tests our assumption about scalar type coercion edge cases.
     */
    public function testScalarTypeCoercionEdgeCases(): void
    {
        /** @var Standard<int, mixed> $pipeline */
        $pipeline = take([
            '123',      // numeric string
            '12.34',    // numeric string with decimal
            'abc123',   // non-numeric string
            '',         // empty string
            '0',        // zero string
            0,          // zero int
            0.0,         // zero float
        ]);

        // Test is_numeric vs is_string behavior
        $numeric = $pipeline->filter('is_numeric')->toList();
        $this->assertSame(['123', '12.34', '0', 0, 0.0], $numeric);
    }

    /**
     * Tests assumption about intersection with strict mode and complex unions.
     */
    public function testComplexUnionWithStrictModeInteraction(): void
    {
        /** @var Standard<int, int|float|string|array<mixed>|null|false> $pipeline */
        $pipeline = take([
            1,              // int (truthy)
            0,              // int (falsy)
            2.5,            // float (truthy)
            0.0,            // float (falsy)
            'hello',        // string (truthy)
            '',             // string (falsy)
            [1, 2],         // array (truthy)
            [],             // array (falsy)
            null,           // null (falsy)
            false,           // false (falsy)
        ]);

        // Strict mode should remove only: null, false (not other falsy values)
        $result = $pipeline
            ->filter(strict: true)
            ->toList();

        $this->assertSame([1, 0, 2.5, 0.0, 'hello', '', [1, 2], []], $result);
    }

    /**
     * Tests our assumption about how we handle type narrowing with inheritance.
     */
    public function testFilterWithInheritanceHierarchy(): void
    {
        $dt = new DateTime('2023-01-01');
        $obj = new stdClass();

        /** @var Standard<int, DateTime|stdClass|string> $pipeline */
        $pipeline = take([$dt, $obj, 'hello', $dt]);

        // Both DateTime and stdClass are objects
        $objects = $pipeline->filter('is_object')->toList();
        $this->assertCount(3, $objects);

        // But we can't narrow further to specific object types with our current implementation
        $this->assertSame([$dt, $obj, $dt], $objects);
    }

}
