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

    public function testNarrowForStrictModeReturnsNullWhenFilteredTypesEmpty(): void
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

    public function testNarrowForStrictModeWithNullType(): void
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

    public function testNarrowForCallbackWithUnionType(): void
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

    public function testNarrowForCallbackWithNoTypeMatch(): void
    {
        $keyType = new IntegerType();
        $valueType = new IntegerType();
        $targetType = new StringType();

        $result = $this->narrower->narrowForCallback($keyType, $valueType, $targetType);

        $this->assertNull($result);
    }

    public function testNarrowForCallbackWithSubtype(): void
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
}
