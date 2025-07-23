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

    public function testIsMethodSupportedReturnsTrueForFilter(): void
    {
        $methodReflection = $this->createMethodReflection('filter');
        $this->assertTrue($this->extension->isMethodSupported($methodReflection));
    }

    public function testIsMethodSupportedReturnsFalseForOtherMethods(): void
    {
        $methodReflection = $this->createMethodReflection('map');
        $this->assertFalse($this->extension->isMethodSupported($methodReflection));
    }

    public function xtestExtensionInstantiationWithAllParameters(): void
    {
        $helper = new FilterTypeNarrowingHelper();
        $argumentParser = new ArgumentParser();
        $strictModeDetector = new StrictModeDetector();
        $callbackResolver = new CallbackResolver($helper);
        $typeNarrower = new TypeNarrower($helper);

        $extension = new FilterReturnTypeExtension(
            $helper,
            $argumentParser,
            $strictModeDetector,
            $callbackResolver,
            $typeNarrower
        );

        $this->assertInstanceOf(FilterReturnTypeExtension::class, $extension);
        $this->assertSame(Standard::class, $extension->getClass());
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

    public function testGetTypeFromMethodCallWithInvalidReturnTypeStructure(): void
    {
        // Test line 80: when extractKeyAndValueTypes returns null
        $helper = $this->createMock(FilterTypeNarrowingHelper::class);
        $argumentParser = $this->createMock(ArgumentParser::class);

        $extension = new FilterReturnTypeExtension($helper, $argumentParser);

        $methodReflection = $this->createMethodReflection('filter');
        $methodCall = $this->createMock(\PhpParser\Node\Expr\MethodCall::class);
        $methodCall->args = [];
        $scope = $this->createMock(\PHPStan\Analyser\Scope::class);

        $parametersAcceptor = $this->createMock(\PHPStan\Reflection\ParametersAcceptor::class);
        $invalidReturnType = $this->createMock(\PHPStan\Type\Type::class);

        // Mock the method reflection to return the parameters acceptor
        $methodReflection->expects($this->once())
            ->method('getVariants')
            ->willReturn([$parametersAcceptor]);

        $parametersAcceptor->expects($this->any())
            ->method('getReturnType')
            ->willReturn($invalidReturnType);

        $argumentParser->expects($this->once())
            ->method('extractArgs')
            ->with($methodCall)
            ->willReturn([]);

        $helper->expects($this->once())
            ->method('extractKeyAndValueTypes')
            ->with($invalidReturnType)
            ->willReturn(null);

        $result = $extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);

        // Should return the original type when extraction fails
        $this->assertSame($invalidReturnType, $result);
    }

    public function testTryCallbackFilteringWithNullCallback(): void
    {
        // Test the tryCallbackFiltering method with null callback
        $helper = $this->createMock(FilterTypeNarrowingHelper::class);
        $argumentParser = $this->createMock(ArgumentParser::class);
        $callbackResolver = $this->createMock(CallbackResolver::class);
        $strictModeDetector = $this->createMock(StrictModeDetector::class);
        $typeNarrower = $this->createMock(TypeNarrower::class);

        $extension = new FilterReturnTypeExtension($helper, $argumentParser, $strictModeDetector, $callbackResolver, $typeNarrower);

        $methodReflection = $this->createMethodReflection('filter');
        $methodCall = $this->createMock(\PhpParser\Node\Expr\MethodCall::class);
        $methodCall->args = [];
        $scope = $this->createMock(\PHPStan\Analyser\Scope::class);

        $parametersAcceptor = $this->createMock(\PHPStan\Reflection\ParametersAcceptor::class);
        $returnType = $this->createMock(\PHPStan\Type\Type::class);
        $keyType = new \PHPStan\Type\IntegerType();
        $valueType = new \PHPStan\Type\StringType();

        $parametersAcceptor->method('getReturnType')->willReturn($returnType);
        $methodReflection->method('getVariants')->willReturn([$parametersAcceptor]);

        $argumentParser->method('extractArgs')->willReturn([]);
        $argumentParser->method('getStrictArg')->willReturn(null);
        $argumentParser->method('getCallbackArg')->willReturn(null);

        $helper->method('extractKeyAndValueTypes')->willReturn([$keyType, $valueType]);

        $strictModeDetector->method('isStrictMode')->willReturn(false);
        $typeNarrower->method('narrowForDefaultFilter')->willReturn(null);

        $result = $extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);

        $this->assertSame($returnType, $result);
    }


    public function testGetTypeFromMethodCallWithNoNarrowing(): void
    {
        // Test line 116: when no narrowing occurs in default filter
        $helper = $this->createMock(FilterTypeNarrowingHelper::class);
        $argumentParser = $this->createMock(ArgumentParser::class);
        $typeNarrower = $this->createMock(TypeNarrower::class);

        $extension = new FilterReturnTypeExtension($helper, $argumentParser, null, null, $typeNarrower);

        $methodReflection = $this->createMethodReflection('filter');
        $methodCall = $this->createMock(\PhpParser\Node\Expr\MethodCall::class);
        $methodCall->args = [];
        $scope = $this->createMock(\PHPStan\Analyser\Scope::class);

        $parametersAcceptor = $this->createMock(\PHPStan\Reflection\ParametersAcceptor::class);
        $returnType = $this->createMock(\PHPStan\Type\Type::class);
        $keyType = new \PHPStan\Type\IntegerType();
        $valueType = new \PHPStan\Type\StringType();

        // Mock the method reflection to return the parameters acceptor
        $methodReflection->expects($this->once())
            ->method('getVariants')
            ->willReturn([$parametersAcceptor]);

        $parametersAcceptor->expects($this->any())
            ->method('getReturnType')
            ->willReturn($returnType);

        $argumentParser->expects($this->any())
            ->method('extractArgs')
            ->willReturn([]);

        $argumentParser->expects($this->any())
            ->method('getStrictArg')
            ->willReturn(null);

        $argumentParser->expects($this->any())
            ->method('getCallbackArg')
            ->willReturn(null);

        $helper->expects($this->once())
            ->method('extractKeyAndValueTypes')
            ->with($returnType)
            ->willReturn([$keyType, $valueType]);

        $typeNarrower->expects($this->once())
            ->method('narrowForDefaultFilter')
            ->with($keyType, $valueType)
            ->willReturn(null); // No narrowing occurred

        $result = $extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);

        // Should return the original type when no narrowing occurs
        $this->assertSame($returnType, $result);
    }

    /**
     * Test callback handling precedence - ensures callbacks are processed when present.
     * This kills the NotIdentical mutation for callback check.
     */
    public function testCallbackPrecedenceOverStrictMode(): void
    {
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

        $methodReflection = $this->createMethodReflection('filter');
        $methodCall = $this->createMock(\PhpParser\Node\Expr\MethodCall::class);
        $scope = $this->createMock(\PHPStan\Analyser\Scope::class);

        $arg = new \PhpParser\Node\Arg(new \PhpParser\Node\Expr\ConstFetch(new \PhpParser\Node\Name('is_string')));
        $methodCall->args = [$arg];

        $argumentParser->expects($this->once())->method('extractArgs')->willReturn([$arg]);

        $returnType = new \PHPStan\Type\Generic\GenericObjectType(Standard::class, [
            new \PHPStan\Type\IntegerType(),
            new \PHPStan\Type\UnionType([new \PHPStan\Type\StringType(), new \PHPStan\Type\IntegerType()]),
        ]);

        $parametersAcceptor = $this->createMock(\PHPStan\Reflection\ParametersAcceptor::class);
        $parametersAcceptor->method('getReturnType')->willReturn($returnType);
        $methodReflection->method('getVariants')->willReturn([$parametersAcceptor]);

        $helper->expects($this->once())->method('extractKeyAndValueTypes')->willReturn([
            new \PHPStan\Type\IntegerType(),
            new \PHPStan\Type\UnionType([new \PHPStan\Type\StringType(), new \PHPStan\Type\IntegerType()]),
        ]);

        $argumentParser->expects($this->once())->method('getCallbackArg')->willReturn($arg);
        $argumentParser->expects($this->once())->method('getStrictArg')->willReturn($arg);

        // Callback resolution should be called because callback is present
        $callbackResolver->expects($this->once())->method('resolveCallbackType')->with($arg)->willReturn(new \PHPStan\Type\StringType());

        $narrowedType = new \PHPStan\Type\Generic\GenericObjectType(Standard::class, [
            new \PHPStan\Type\IntegerType(),
            new \PHPStan\Type\StringType(),
        ]);

        $typeNarrower->expects($this->once())->method('narrowForCallback')->willReturn($narrowedType);

        // Strict mode detector should NOT be called because callback takes precedence
        $strictModeDetector->expects($this->never())->method('isStrictMode');

        $result = $extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);
        $this->assertSame($narrowedType, $result);
    }

    /**
     * Test default filter handling when no callback is present.
     * This kills the Identical mutation for default filter check.
     */
    public function testDefaultFilterWhenNoCallback(): void
    {
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

        $methodReflection = $this->createMethodReflection('filter');
        $methodCall = $this->createMock(\PhpParser\Node\Expr\MethodCall::class);
        $scope = $this->createMock(\PHPStan\Analyser\Scope::class);

        $methodCall->args = [];

        $argumentParser->expects($this->once())->method('extractArgs')->willReturn([]);

        $returnType = new \PHPStan\Type\Generic\GenericObjectType(Standard::class, [
            new \PHPStan\Type\IntegerType(),
            new \PHPStan\Type\UnionType([new \PHPStan\Type\StringType(), new \PHPStan\Type\NullType()]),
        ]);

        $parametersAcceptor = $this->createMock(\PHPStan\Reflection\ParametersAcceptor::class);
        $parametersAcceptor->method('getReturnType')->willReturn($returnType);
        $methodReflection->method('getVariants')->willReturn([$parametersAcceptor]);

        $helper->expects($this->once())->method('extractKeyAndValueTypes')->willReturn([
            new \PHPStan\Type\IntegerType(),
            new \PHPStan\Type\UnionType([new \PHPStan\Type\StringType(), new \PHPStan\Type\NullType()]),
        ]);

        $argumentParser->expects($this->once())->method('getCallbackArg')->willReturn(null);
        $argumentParser->expects($this->once())->method('getStrictArg')->willReturn(null);

        // No callback, not strict mode, so should call default filter
        $strictModeDetector->expects($this->once())->method('isStrictMode')->with(null, $scope)->willReturn(false);

        $narrowedType = new \PHPStan\Type\Generic\GenericObjectType(Standard::class, [
            new \PHPStan\Type\IntegerType(),
            new \PHPStan\Type\StringType(),
        ]);

        $typeNarrower->expects($this->once())->method('narrowForDefaultFilter')->willReturn($narrowedType);

        $result = $extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);
        $this->assertSame($narrowedType, $result);
    }

    /**
     * @return MethodReflection&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMethodReflection(string $methodName): MethodReflection
    {
        $methodReflection = $this->createMock(MethodReflection::class);
        $methodReflection->method('getName')->willReturn($methodName);
        return $methodReflection;
    }
}
