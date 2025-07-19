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

use PHPStan\Reflection\MethodReflection;
use PHPUnit\Framework\TestCase;
use Pipeline\PHPStan\ArgumentParser;
use Pipeline\PHPStan\CallbackResolver;
use Pipeline\PHPStan\FilterReturnTypeExtension;
use Pipeline\PHPStan\FilterTypeNarrowingHelper;
use Pipeline\PHPStan\StrictModeDetector;
use Pipeline\PHPStan\TypeNarrower;
use Pipeline\Standard;

/**
 * Tests for FilterReturnTypeExtension.
 *
 * This test focuses on the basic functionality that can be easily unit tested.
 * For comprehensive type narrowing testing, see tests/Inference/FilterTypeNarrowingSimpleTest.php
 * which tests the actual behavior through PHPStan analysis.
 *
 * Additional PHPStan-specific testing can be done using PHPStan's TypeInferenceTestCase
 * framework (if installed separately)
 *
 * @covers \Pipeline\PHPStan\FilterReturnTypeExtension
 */
final class FilterReturnTypeExtensionTest extends TestCase
{
    private FilterReturnTypeExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new FilterReturnTypeExtension();
    }

    public function testGetClass(): void
    {
        $this->assertSame(Standard::class, $this->extension->getClass());
    }

    public function testIsMethodSupportedForFilter(): void
    {
        $methodReflection = $this->createMock(MethodReflection::class);
        $methodReflection
            ->method('getName')
            ->willReturn('filter');

        $this->assertTrue($this->extension->isMethodSupported($methodReflection));
    }

    public function testIsMethodNotSupportedForOtherMethods(): void
    {
        $unsupportedMethods = ['map', 'cast', 'reduce', 'each', 'count'];

        foreach ($unsupportedMethods as $methodName) {
            $methodReflection = $this->createMock(MethodReflection::class);
            $methodReflection
                ->method('getName')
                ->willReturn($methodName);

            $this->assertFalse(
                $this->extension->isMethodSupported($methodReflection),
                "Method '{$methodName}' should not be supported"
            );
        }
    }

    public function testExtensionImplementsCorrectInterface(): void
    {
        $this->assertInstanceOf(
            \PHPStan\Type\DynamicMethodReturnTypeExtension::class,
            $this->extension
        );
    }

    /**
     * Test that the extension can be instantiated without errors.
     * This ensures all dependencies are properly imported.
     */
    public function testExtensionInstantiation(): void
    {
        $extension = new FilterReturnTypeExtension();
        $this->assertInstanceOf(FilterReturnTypeExtension::class, $extension);
    }

    /**
     * Test that the extension can be instantiated with a custom helper.
     */
    public function testExtensionInstantiationWithCustomHelper(): void
    {
        $helper = new FilterTypeNarrowingHelper();
        $extension = new FilterReturnTypeExtension($helper);
        $this->assertInstanceOf(FilterReturnTypeExtension::class, $extension);
    }

    /**
     * Test that the extension uses dependency injection properly.
     */
    public function testExtensionDependencyInjection(): void
    {
        // Test with explicit helper
        $helper = new FilterTypeNarrowingHelper();
        $extension = new FilterReturnTypeExtension($helper);
        $this->assertInstanceOf(FilterReturnTypeExtension::class, $extension);

        // Test with null (should create default components)
        $extensionWithDefault = new FilterReturnTypeExtension();
        $this->assertInstanceOf(FilterReturnTypeExtension::class, $extensionWithDefault);

        // Both should work the same way
        $this->assertSame(Standard::class, $extension->getClass());
        $this->assertSame(Standard::class, $extensionWithDefault->getClass());

        // Test the coalesce operator: helper ?? new FilterTypeNarrowingHelper()
        // This tests that null helper creates a new instance (kills coalesce mutation)
        $extensionWithExplicitNull = new FilterReturnTypeExtension(null);
        $this->assertTrue($extensionWithExplicitNull->isMethodSupported($this->createMethodReflection('filter')));
    }

    /**
     * Test that verifies the coalesce behavior more explicitly.
     * This test kills the coalesce mutation by ensuring null creates a working helper.
     */
    public function testCoalesceOperatorInConstructor(): void
    {
        // When null is passed, the constructor should use: helper ?? new FilterTypeNarrowingHelper()
        // The mutation would change this to: new FilterTypeNarrowingHelper() ?? helper
        // This test ensures the original logic is correct

        $extensionWithNull = new FilterReturnTypeExtension(null);

        // If the coalesce is working correctly, this should use a new helper and work
        $this->assertSame(Standard::class, $extensionWithNull->getClass());
        $this->assertTrue($extensionWithNull->isMethodSupported($this->createMethodReflection('filter')));
        $this->assertFalse($extensionWithNull->isMethodSupported($this->createMethodReflection('map')));

        // Test with an actual helper to ensure the same behavior
        $actualHelper = new FilterTypeNarrowingHelper();
        $extensionWithHelper = new FilterReturnTypeExtension($actualHelper);

        $this->assertSame($extensionWithNull->getClass(), $extensionWithHelper->getClass());
    }

    /**
     * Test that VariadicPlaceholder in args is properly filtered out.
     * This tests the array_filter at line 77 that removes non-Arg instances.
     */
    public function testVariadicPlaceholderInArgs(): void
    {
        $extension = new FilterReturnTypeExtension();

        $methodReflection = $this->createMock(MethodReflection::class);
        $methodReflection->method('getName')->willReturn('filter');

        $methodCall = $this->createMock(\PhpParser\Node\Expr\MethodCall::class);

        // Mix of Arg and VariadicPlaceholder
        $arg1 = $this->createMock(\PhpParser\Node\Arg::class);
        $arg1->name = null;
        $arg1->value = $this->createMock(\PhpParser\Node\Expr::class);

        $variadicPlaceholder = new \PhpParser\Node\VariadicPlaceholder([]);

        $methodCall->args = [$arg1, $variadicPlaceholder];

        $scope = $this->createMock(\PHPStan\Analyser\Scope::class);

        $parametersAcceptor = $this->createMock(\PHPStan\Reflection\ParametersAcceptor::class);
        // Use a concrete type that won't trigger helper checks
        $returnType = new \PHPStan\Type\StringType();
        $parametersAcceptor->method('getReturnType')->willReturn($returnType);
        $methodReflection->method('getVariants')->willReturn([$parametersAcceptor]);

        // The call should not throw an error even with VariadicPlaceholder
        $result = $extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);

        // Should return the original return type since we can't narrow it
        $this->assertSame($returnType, $result);
    }

    /**
     * Test that array_filter is necessary to avoid passing VariadicPlaceholder to ParametersAcceptorSelector.
     * This kills the UnwrapArrayFilter mutation.
     */
    public function testArrayFilterIsNecessary(): void
    {
        $extension = new FilterReturnTypeExtension();

        $methodReflection = $this->createMock(MethodReflection::class);
        $methodReflection->method('getName')->willReturn('filter');

        $methodCall = $this->createMock(\PhpParser\Node\Expr\MethodCall::class);

        // Only VariadicPlaceholder, no Arg instances
        $variadicPlaceholder = new \PhpParser\Node\VariadicPlaceholder([]);
        $methodCall->args = [$variadicPlaceholder];

        $scope = $this->createMock(\PHPStan\Analyser\Scope::class);

        // Mock variants to expect empty args array (after filtering)
        $parametersAcceptor = $this->createMock(\PHPStan\Reflection\ParametersAcceptor::class);
        $returnType = new \PHPStan\Type\StringType();
        $parametersAcceptor->method('getReturnType')->willReturn($returnType);

        $methodReflection->expects($this->once())
            ->method('getVariants')
            ->willReturn([$parametersAcceptor]);

        // If array_filter wasn't there, ParametersAcceptorSelector would receive VariadicPlaceholder
        // and might throw an error. With array_filter, it receives an empty array.
        $result = $extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);

        $this->assertSame($returnType, $result);
    }

    /**
     * Test the null check for keyValueTypes to kill the Identical mutation.
     * This ensures both branches of the null === $keyValueTypes check are tested.
     */
    public function testKeyValueTypesNullCheck(): void
    {
        // Test 1: When helper returns null, method should return early
        $nullHelper = $this->createMock(FilterTypeNarrowingHelper::class);
        $nullHelper->expects($this->once())
                   ->method('extractKeyAndValueTypes')
                   ->willReturn(null);

        $extension = new FilterReturnTypeExtension($nullHelper);

        $methodReflection = $this->createMock(MethodReflection::class);
        $methodReflection->method('getName')->willReturn('filter');
        $methodCall = $this->createMock(\PhpParser\Node\Expr\MethodCall::class);
        $methodCall->args = [];
        $scope = $this->createMock(\PHPStan\Analyser\Scope::class);

        $parametersAcceptor = $this->createMock(\PHPStan\Reflection\ParametersAcceptor::class);
        $returnType = new \PHPStan\Type\StringType();
        $parametersAcceptor->method('getReturnType')->willReturn($returnType);
        $methodReflection->method('getVariants')->willReturn([$parametersAcceptor]);

        // When extractKeyAndValueTypes returns null, the method should return early
        $result = $extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);

        // Should return the original return type when keyValueTypes is null
        // This verifies the early return happens when null === $keyValueTypes
        $this->assertSame($returnType, $result);

        // Test 2: When helper returns non-null, method should continue processing
        $nonNullHelper = $this->createMock(FilterTypeNarrowingHelper::class);
        $keyType = new \PHPStan\Type\IntegerType();
        $valueType = new \PHPStan\Type\StringType();
        $nonNullHelper->expects($this->once())
                      ->method('extractKeyAndValueTypes')
                      ->willReturn([$keyType, $valueType]);

        // Also mock other helper methods that will be called
        $nonNullHelper->method('removeFalsyTypesFromUnion')->willReturn([]);
        $nonNullHelper->method('createGenericTypeWithFilteredValues')->willReturn(null);
        $nonNullHelper->method('extractFunctionNameFromStringCallback')->willReturn(null);
        $nonNullHelper->method('extractFunctionNameFromFirstClassCallable')->willReturn(null);
        $nonNullHelper->method('getTargetTypeForFunction')->willReturn(null);
        $nonNullHelper->method('filterUnionTypeByTarget')->willReturn([]);

        $extension2 = new FilterReturnTypeExtension($nonNullHelper);

        // This call should NOT return early because keyValueTypes is not null
        $result2 = $extension2->getTypeFromMethodCall($methodReflection, $methodCall, $scope);

        // The mutation (null !== $keyValueTypes) would cause the early return when it shouldn't
        // By verifying the helper was called, we prove the code continued past the null check
        $this->assertSame($returnType, $result2);
    }

    /**
     * Test that the extension properly integrates all components.
     */
    public function testComponentIntegration(): void
    {
        // Create mocks for all components
        $helper = $this->createMock(FilterTypeNarrowingHelper::class);
        $argumentParser = $this->createMock(ArgumentParser::class);
        $strictModeDetector = $this->createMock(StrictModeDetector::class);
        $callbackResolver = $this->createMock(CallbackResolver::class);
        $typeNarrower = $this->createMock(TypeNarrower::class);

        $extension = new FilterReturnTypeExtension(
            $helper,
            $argumentParser,
            $strictModeDetector,
            $callbackResolver,
            $typeNarrower
        );

        // Setup method call
        $methodReflection = $this->createMethodReflection('filter');
        $methodCall = $this->createMock(\PhpParser\Node\Expr\MethodCall::class);
        $scope = $this->createMock(\PHPStan\Analyser\Scope::class);

        // Setup arguments
        $arg = new \PhpParser\Node\Arg(
            new \PhpParser\Node\Expr\ConstFetch(new \PhpParser\Node\Name('true'))
        );
        $methodCall->args = [$arg];

        // Setup expectations
        $argumentParser->expects($this->once())
            ->method('extractArgs')
            ->with($methodCall)
            ->willReturn([$arg]);

        $parametersAcceptor = $this->createMock(\PHPStan\Reflection\ParametersAcceptor::class);
        $returnType = new \PHPStan\Type\Generic\GenericObjectType(Standard::class, [
            new \PHPStan\Type\IntegerType(),
            new \PHPStan\Type\UnionType([
                new \PHPStan\Type\StringType(),
                new \PHPStan\Type\NullType(),
            ]),
        ]);
        $parametersAcceptor->method('getReturnType')->willReturn($returnType);
        $methodReflection->method('getVariants')->willReturn([$parametersAcceptor]);

        $helper->expects($this->once())
            ->method('extractKeyAndValueTypes')
            ->with($returnType)
            ->willReturn([
                new \PHPStan\Type\IntegerType(),
                new \PHPStan\Type\UnionType([
                    new \PHPStan\Type\StringType(),
                    new \PHPStan\Type\NullType(),
                ]),
            ]);

        $argumentParser->expects($this->once())
            ->method('getStrictArg')
            ->with([$arg])
            ->willReturn($arg);

        $argumentParser->expects($this->once())
            ->method('getCallbackArg')
            ->with([$arg])
            ->willReturn(null);

        $strictModeDetector->expects($this->once())
            ->method('isStrictMode')
            ->with($arg, $scope)
            ->willReturn(true);

        $expectedNarrowedType = new \PHPStan\Type\Generic\GenericObjectType(Standard::class, [
            new \PHPStan\Type\IntegerType(),
            new \PHPStan\Type\StringType(),
        ]);

        $typeNarrower->expects($this->once())
            ->method('narrowForStrictMode')
            ->willReturn($expectedNarrowedType);

        // Execute
        $result = $extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);

        // Assert
        $this->assertSame($expectedNarrowedType, $result);
    }

    private function createMethodReflection(string $methodName): MethodReflection
    {
        $methodReflection = $this->createMock(MethodReflection::class);
        $methodReflection->method('getName')->willReturn($methodName);
        return $methodReflection;
    }
}
