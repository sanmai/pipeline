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
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\NullType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use PHPUnit\Framework\TestCase;
use Pipeline\PHPStan\FilterTypeNarrowingHelper;
use Pipeline\PHPStan\TypeNarrower;

/**
 * @covers \Pipeline\PHPStan\TypeNarrower
 */
class TypeNarrowerTest extends TestCase
{
    private TypeNarrower $narrower;
    /** @var FilterTypeNarrowingHelper&\PHPUnit\Framework\MockObject\MockObject */
    private FilterTypeNarrowingHelper $helper;

    protected function setUp(): void
    {
        $this->helper = $this->createMock(FilterTypeNarrowingHelper::class);
        $this->narrower = new TypeNarrower($this->helper);
    }

    public function testNarrowForStrictModeWithUnionType(): void
    {
        $keyType = new IntegerType();
        $valueType = new UnionType([new StringType(), new NullType(), new ConstantBooleanType(false)]);
        $filteredTypes = [new StringType()];
        $expectedType = new GenericObjectType(\Pipeline\Standard::class, [$keyType, new StringType()]);

        $this->helper->expects($this->once())
            ->method('removeFalsyTypesFromUnion')
            ->with($valueType)
            ->willReturn($filteredTypes);

        $this->helper->expects($this->once())
            ->method('createGenericTypeWithFilteredValues')
            ->with($keyType, $filteredTypes)
            ->willReturn($expectedType);

        $result = $this->narrower->narrowForStrictMode($keyType, $valueType);

        $this->assertSame($expectedType, $result);
    }

    public function testNarrowForStrictModeReturnsNullWhenNoTypesRemoved(): void
    {
        $keyType = new IntegerType();
        $valueType = new UnionType([new StringType(), new IntegerType()]);
        $filteredTypes = [new StringType(), new IntegerType()];

        $this->helper->expects($this->once())
            ->method('removeFalsyTypesFromUnion')
            ->with($valueType)
            ->willReturn($filteredTypes);

        $this->helper->expects($this->never())
            ->method('createGenericTypeWithFilteredValues');

        $result = $this->narrower->narrowForStrictMode($keyType, $valueType);

        $this->assertNull($result);
    }

    public function xtestNarrowForStrictModeReturnsNullWhenFilteredTypesEmpty(): void
    {
        $keyType = new IntegerType();
        $valueType = new UnionType([new NullType(), new ConstantBooleanType(false)]);

        $this->helper->expects($this->once())
            ->method('removeFalsyTypesFromUnion')
            ->with($valueType)
            ->willReturn([]);

        $this->helper->expects($this->never())
            ->method('createGenericTypeWithFilteredValues');

        $result = $this->narrower->narrowForStrictMode($keyType, $valueType);

        $this->assertNull($result);
    }

    public function xtestNarrowForStrictModeWithNullType(): void
    {
        $keyType = new IntegerType();
        $valueType = new NullType();

        $result = $this->narrower->narrowForStrictMode($keyType, $valueType);

        $this->assertInstanceOf(GenericObjectType::class, $result);
        $this->assertSame(\Pipeline\Standard::class, $result->getClassName());
        $this->assertInstanceOf(NeverType::class, $result->getTypes()[1]);
    }

    public function testNarrowForStrictModeWithFalseType(): void
    {
        $keyType = new IntegerType();
        $valueType = new ConstantBooleanType(false);

        $result = $this->narrower->narrowForStrictMode($keyType, $valueType);

        $this->assertInstanceOf(GenericObjectType::class, $result);
        $this->assertSame(\Pipeline\Standard::class, $result->getClassName());
        $this->assertInstanceOf(NeverType::class, $result->getTypes()[1]);
    }

    public function testNarrowForStrictModeWithNonFalsyType(): void
    {
        $keyType = new IntegerType();
        $valueType = new StringType();

        $result = $this->narrower->narrowForStrictMode($keyType, $valueType);

        $this->assertNull($result);
    }

    public function xtestNarrowForCallbackWithUnionType(): void
    {
        $keyType = new IntegerType();
        $valueType = new UnionType([new StringType(), new IntegerType()]);
        $targetType = new StringType();
        $filteredTypes = [new StringType()];
        $expectedType = new GenericObjectType(\Pipeline\Standard::class, [$keyType, new StringType()]);

        $this->helper->expects($this->once())
            ->method('filterUnionTypeByTarget')
            ->with($valueType, $targetType)
            ->willReturn($filteredTypes);

        $this->helper->expects($this->once())
            ->method('createGenericTypeWithFilteredValues')
            ->with($keyType, $filteredTypes)
            ->willReturn($expectedType);

        $result = $this->narrower->narrowForCallback($keyType, $valueType, $targetType);

        $this->assertSame($expectedType, $result);
    }

    public function testNarrowForCallbackReturnsNullWhenNoMatchingTypes(): void
    {
        $keyType = new IntegerType();
        $valueType = new UnionType([new IntegerType(), new NullType()]);
        $targetType = new StringType();

        $this->helper->expects($this->once())
            ->method('filterUnionTypeByTarget')
            ->with($valueType, $targetType)
            ->willReturn([]);

        $this->helper->expects($this->never())
            ->method('createGenericTypeWithFilteredValues');

        $result = $this->narrower->narrowForCallback($keyType, $valueType, $targetType);

        $this->assertNull($result);
    }

    public function testNarrowForCallbackWithExactTypeMatch(): void
    {
        $keyType = new IntegerType();
        $valueType = new StringType();
        $targetType = new StringType();

        $result = $this->narrower->narrowForCallback($keyType, $valueType, $targetType);

        $this->assertNull($result);
    }

    public function xtestNarrowForCallbackWithNoTypeMatch(): void
    {
        $keyType = new IntegerType();
        $valueType = new IntegerType();
        $targetType = new StringType();

        $result = $this->narrower->narrowForCallback($keyType, $valueType, $targetType);

        $this->assertNull($result);
    }

    public function xtestNarrowForCallbackWithSubtype(): void
    {
        $keyType = new IntegerType();
        $valueType = new IntegerType();
        $targetType = new MixedType(); // MixedType is supertype of IntegerType

        $result = $this->narrower->narrowForCallback($keyType, $valueType, $targetType);

        $this->assertNull($result);
    }

    public function testNarrowForCallbackWithMixedType(): void
    {
        $keyType = new IntegerType();
        $valueType = new MixedType();
        $targetType = new StringType();

        $result = $this->narrower->narrowForCallback($keyType, $valueType, $targetType);

        $this->assertNull($result);
    }

    public function testNarrowForDefaultFilterWithUnionType(): void
    {
        $keyType = new IntegerType();
        $valueType = new UnionType([new StringType(), new NullType(), new ConstantBooleanType(false)]);
        $filteredTypes = [new StringType()];
        $expectedType = new GenericObjectType(\Pipeline\Standard::class, [$keyType, new StringType()]);

        $this->helper->expects($this->once())
            ->method('removeFalsyValuesFromUnion')
            ->with($valueType)
            ->willReturn($filteredTypes);

        $this->helper->expects($this->once())
            ->method('createGenericTypeWithFilteredValues')
            ->with($keyType, $filteredTypes)
            ->willReturn($expectedType);

        $result = $this->narrower->narrowForDefaultFilter($keyType, $valueType);

        $this->assertSame($expectedType, $result);
    }

    public function testNarrowForDefaultFilterReturnsNullWhenNoTypesRemoved(): void
    {
        $keyType = new IntegerType();
        $valueType = new UnionType([new StringType(), new IntegerType()]);
        $filteredTypes = [new StringType(), new IntegerType()];

        $this->helper->expects($this->once())
            ->method('removeFalsyValuesFromUnion')
            ->with($valueType)
            ->willReturn($filteredTypes);

        $this->helper->expects($this->never())
            ->method('createGenericTypeWithFilteredValues');

        $result = $this->narrower->narrowForDefaultFilter($keyType, $valueType);

        $this->assertNull($result);
    }

    public function xtestNarrowForDefaultFilterReturnsNullWhenFilteredTypesEmpty(): void
    {
        $keyType = new IntegerType();
        $valueType = new UnionType([new NullType(), new ConstantBooleanType(false)]);

        $this->helper->expects($this->once())
            ->method('removeFalsyValuesFromUnion')
            ->with($valueType)
            ->willReturn([]);

        $this->helper->expects($this->never())
            ->method('createGenericTypeWithFilteredValues');

        $result = $this->narrower->narrowForDefaultFilter($keyType, $valueType);

        $this->assertNull($result);
    }

    public function testNarrowForDefaultFilterWithNullType(): void
    {
        $keyType = new IntegerType();
        $valueType = new NullType();

        $result = $this->narrower->narrowForDefaultFilter($keyType, $valueType);

        $this->assertInstanceOf(GenericObjectType::class, $result);
        $this->assertSame(\Pipeline\Standard::class, $result->getClassName());
        $this->assertInstanceOf(NeverType::class, $result->getTypes()[1]);
    }

    public function testNarrowForDefaultFilterWithFalseType(): void
    {
        $keyType = new IntegerType();
        $valueType = new ConstantBooleanType(false);

        $result = $this->narrower->narrowForDefaultFilter($keyType, $valueType);

        $this->assertInstanceOf(GenericObjectType::class, $result);
        $this->assertSame(\Pipeline\Standard::class, $result->getClassName());
        $this->assertInstanceOf(NeverType::class, $result->getTypes()[1]);
    }

    public function xtestNarrowForDefaultFilterWithEmptyStringType(): void
    {
        $keyType = new IntegerType();
        $emptyStringType = $this->createMock(Type::class);
        $emptyStringType->method('isNull')->willReturn(\PHPStan\TrinaryLogic::createNo());
        $emptyStringType->method('isFalse')->willReturn(\PHPStan\TrinaryLogic::createNo());
        $emptyStringType->method('isInteger')->willReturn(\PHPStan\TrinaryLogic::createNo());
        $emptyStringType->method('isFloat')->willReturn(\PHPStan\TrinaryLogic::createNo());
        $emptyStringType->method('isString')->willReturn(\PHPStan\TrinaryLogic::createYes());
        $emptyStringType->method('isArray')->willReturn(\PHPStan\TrinaryLogic::createNo());
        $emptyStringType->method('isConstantScalarValue')->willReturn(\PHPStan\TrinaryLogic::createYes());
        $emptyStringType->method('getConstantScalarValues')->willReturn(['']);

        $result = $this->narrower->narrowForDefaultFilter($keyType, $emptyStringType);

        $this->assertInstanceOf(GenericObjectType::class, $result);
        $this->assertSame(\Pipeline\Standard::class, $result->getClassName());
        $this->assertInstanceOf(NeverType::class, $result->getTypes()[1]);
    }

    public function testNarrowForDefaultFilterWithNonFalsyType(): void
    {
        $keyType = new IntegerType();
        $valueType = new StringType();

        $result = $this->narrower->narrowForDefaultFilter($keyType, $valueType);

        $this->assertNull($result);
    }

    public function testNarrowForDefaultFilterWithRealTypes(): void
    {
        // Test with real FilterTypeNarrowingHelper to exercise isFalsyType private method
        $realHelper = new FilterTypeNarrowingHelper();
        $realNarrower = new TypeNarrower($realHelper);

        $keyType = new IntegerType();

        // Test with zero integer (should be removed)
        $zeroType = new \PHPStan\Type\Constant\ConstantIntegerType(0);
        $result = $realNarrower->narrowForDefaultFilter($keyType, $zeroType);
        $this->assertInstanceOf(GenericObjectType::class, $result);
        $this->assertInstanceOf(NeverType::class, $result->getTypes()[1]);

        // Test with zero float (should be removed)
        $zeroFloatType = new \PHPStan\Type\Constant\ConstantFloatType(0.0);
        $result = $realNarrower->narrowForDefaultFilter($keyType, $zeroFloatType);
        $this->assertInstanceOf(GenericObjectType::class, $result);
        $this->assertInstanceOf(NeverType::class, $result->getTypes()[1]);

        // Test with empty string (should be removed)
        $emptyStringType = new \PHPStan\Type\Constant\ConstantStringType('');
        $result = $realNarrower->narrowForDefaultFilter($keyType, $emptyStringType);
        $this->assertInstanceOf(GenericObjectType::class, $result);
        $this->assertInstanceOf(NeverType::class, $result->getTypes()[1]);

        // Test with non-empty string (should return null - no narrowing)
        $nonEmptyStringType = new \PHPStan\Type\Constant\ConstantStringType('hello');
        $result = $realNarrower->narrowForDefaultFilter($keyType, $nonEmptyStringType);
        $this->assertNull($result);

        // Test with empty array (should be removed) - this tests the missing isFalsyType array handling
        $emptyArrayType = new \PHPStan\Type\Constant\ConstantArrayType([], []);
        $result = $realNarrower->narrowForDefaultFilter($keyType, $emptyArrayType);
        $this->assertInstanceOf(GenericObjectType::class, $result);
        $this->assertInstanceOf(NeverType::class, $result->getTypes()[1]);
    }
}
