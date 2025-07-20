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

use PHPUnit\Framework\TestCase;
use Pipeline\PHPStan\FilterReturnTypeExtension;
use Pipeline\PHPStan\FilterTypeNarrowingHelper;

/**
 * Integration tests for FilterReturnTypeExtension.
 *
 * These tests focus on the interaction between the extension and helper,
 * exercising the Pipeline-Helper Pattern implementation.
 *
 * @covers \Pipeline\PHPStan\FilterReturnTypeExtension
 * @covers \Pipeline\PHPStan\FilterTypeNarrowingHelper
 */
final class FilterExtensionIntegrationTest extends TestCase
{
    /**
     * Test that the extension and helper work together correctly.
     * This tests the orchestration pattern.
     */
    public function xtestExtensionHelperIntegration(): void
    {
        $helper = new FilterTypeNarrowingHelper();
        $extension = new FilterReturnTypeExtension($helper);

        // Test that type map is available through helper
        $typeMap = $helper->getTypeMap();
        $this->assertArrayHasKey('is_string', $typeMap);

        // Test that extension uses the helper's type checking
        $targetType = $helper->getTargetTypeForFunction('is_string');
        $this->assertNotNull($targetType);

        // Test that all supported functions are available
        $supportedFunctions = ['is_string', 'is_int', 'is_float', 'is_bool', 'is_array', 'is_object'];
        foreach ($supportedFunctions as $function) {
            $this->assertNotNull(
                $helper->getTargetTypeForFunction($function),
                "Function '{$function}' should be supported"
            );
        }
    }

    /**
     * Test the sequence of operations (Pipeline-Helper Pattern).
     * This verifies the "what" (orchestration) works with the "how" (implementation).
     */
    public function testOperationSequence(): void
    {
        $helper = new FilterTypeNarrowingHelper();

        // Simulate the sequence: extract function -> get target type -> filter
        $functionName = $helper->extractFunctionNameFromStringCallback(
            new \PhpParser\Node\Scalar\String_('is_string')
        );
        $this->assertSame('is_string', $functionName);

        $targetType = $helper->getTargetTypeForFunction($functionName);
        $this->assertNotNull($targetType);
        $this->assertInstanceOf(\PHPStan\Type\StringType::class, $targetType);
    }

    /**
     * Test that unsupported operations return null appropriately.
     */
    public function testUnsupportedOperations(): void
    {
        $helper = new FilterTypeNarrowingHelper();

        // Unsupported function name
        $result = $helper->extractFunctionNameFromStringCallback(
            new \PhpParser\Node\Scalar\String_('custom_function')
        );
        $this->assertNull($result);

        // Unsupported target type
        $targetType = $helper->getTargetTypeForFunction('custom_function');
        $this->assertNull($targetType);
    }

    /**
     * Test error handling in the helper methods.
     */
    public function xtestHelperErrorHandling(): void
    {
        $helper = new FilterTypeNarrowingHelper();

        // Test with empty filtered types
        $result = $helper->createGenericTypeWithFilteredValues(
            new \PHPStan\Type\IntegerType(),
            []
        );
        $this->assertNull($result);

        // Test invalid return type structure
        $invalidType = $this->createMock(\PHPStan\Type\Type::class);
        $invalidType->method('isObject')->willReturn(\PHPStan\TrinaryLogic::createNo());

        $result = $helper->extractKeyAndValueTypes($invalidType);
        $this->assertNull($result);
    }

    /**
     * Test the dependency injection works correctly.
     */
    public function testDependencyInjectionPattern(): void
    {
        // Test with injected helper
        $helper = new FilterTypeNarrowingHelper();
        $extension = new FilterReturnTypeExtension($helper);
        $this->assertInstanceOf(FilterReturnTypeExtension::class, $extension);

        // Test with default helper (null injection)
        $extensionWithDefault = new FilterReturnTypeExtension();
        $this->assertInstanceOf(FilterReturnTypeExtension::class, $extensionWithDefault);

        // Both should behave the same for basic operations
        $this->assertSame($extension->getClass(), $extensionWithDefault->getClass());
    }
}
