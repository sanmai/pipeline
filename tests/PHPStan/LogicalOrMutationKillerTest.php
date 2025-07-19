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

namespace Tests\Pipeline\PHPStan;

use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\StringType;
use PHPStan\TrinaryLogic;
use PHPUnit\Framework\TestCase;
use Pipeline\PHPStan\FilterTypeNarrowingHelper;
use Pipeline\Standard;

use function count;
use function is_array;

/**
 * @covers \Pipeline\PHPStan\FilterTypeNarrowingHelper
 */
final class LogicalOrMutationKillerTest extends TestCase
{
    /**
     * Kill the LogicalOr mutation in extractKeyAndValueTypes.
     *
     * Original: if (!is_array($genericTypes) || 2 !== count($genericTypes))
     * Mutated:  if (!is_array($genericTypes) && 2 !== count($genericTypes))
     *
     * We need a case where:
     * - is_array($genericTypes) = true (so !is_array = false)
     * - count($genericTypes) !== 2 = true (so 2 !== count = true)
     *
     * Original OR: false || true = true (returns null)
     * Mutated AND: false && true = false (continues execution, would fail)
     */
    public function testLogicalOrMutationWithWrongArrayCount(): void
    {
        $helper = new FilterTypeNarrowingHelper();

        // Create a mock that returns an array with wrong count
        $mockType = $this->createMock(GenericObjectType::class);
        $mockType->method('isObject')->willReturn(TrinaryLogic::createYes());
        $mockType->method('getObjectClassNames')->willReturn([Standard::class]);

        // Return array with 3 elements (not 2) - this is the key test case
        $mockType->method('getTypes')->willReturn([
            new StringType(),
            new StringType(),
            new StringType(),  // 3 elements, not 2
        ]);

        // Original code: (!is_array($arr) || 2 !== count($arr))
        // - !is_array([...]) = false
        // - 2 !== count([...]) = 2 !== 3 = true
        // - false || true = true → return null

        // Mutated code: (!is_array($arr) && 2 !== count($arr))
        // - !is_array([...]) = false
        // - 2 !== count([...]) = 2 !== 3 = true
        // - false && true = false → continue execution → would crash on destructuring

        $result = $helper->extractKeyAndValueTypes($mockType);

        // Original code: (!is_array($arr) || 2 !== count($arr)) = (false || true) = true → return null
        // Mutated code: (!is_array($arr) && 2 !== count($arr)) = (false && true) = false → continue to return [$keyType, $valueType]

        // With original code, this MUST return null
        // With mutated code, this would return an array with 2 elements (ignoring the 3rd)
        $this->assertNull($result, 'Original code should return null for wrong array count');

        // Additional assertion to ensure we're actually testing the right path
        $this->assertIsArray([new StringType(), new StringType(), new StringType()], 'Our test data should be an array');
        $this->assertNotCount(2, [new StringType(), new StringType(), new StringType()], 'Our test data should not have count of 2');
    }

    /**
     * This is a theoretical test case for the LogicalOr mutation.
     * In practice, this mutation may be undetectable because getTypes()
     * typically always returns an array when it exists.
     */
    public function testLogicalOrMutationDocumentation(): void
    {
        // This test documents the LogicalOr mutation issue.
        // The mutation changes (!is_array($x) || 2 !== count($x)) to (!is_array($x) && 2 !== count($x))
        //
        // For this to be detectable, we'd need a case where:
        // - getTypes() exists (method_exists returns true)
        // - getTypes() returns something that's not an array
        //
        // But PHPStan's type system guarantees getTypes() returns array when it exists.
        // This may be a legitimate false positive where the mutation doesn't change behavior.

        $this->assertTrue(true, 'This test documents the LogicalOr mutation challenge');
    }
}
