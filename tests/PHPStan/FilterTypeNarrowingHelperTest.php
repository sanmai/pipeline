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

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use PHPStan\TrinaryLogic;
use PHPUnit\Framework\TestCase;
use Pipeline\PHPStan\FilterTypeNarrowingHelper;
use Pipeline\Standard;

/**
 * @covers \Pipeline\PHPStan\FilterTypeNarrowingHelper
 */
final class FilterTypeNarrowingHelperTest extends TestCase
{
    private FilterTypeNarrowingHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new FilterTypeNarrowingHelper();
    }

    public function testGetTypeMap(): void
    {
        $typeMap = $this->helper->getTypeMap();

        $this->assertArrayHasKey('is_string', $typeMap);
        $this->assertArrayHasKey('is_int', $typeMap);
        $this->assertArrayHasKey('is_float', $typeMap);
        $this->assertArrayHasKey('is_bool', $typeMap);
        $this->assertArrayHasKey('is_array', $typeMap);
        $this->assertArrayHasKey('is_object', $typeMap);

        $this->assertInstanceOf(StringType::class, $typeMap['is_string']);
        $this->assertInstanceOf(IntegerType::class, $typeMap['is_int']);
        $this->assertInstanceOf(FloatType::class, $typeMap['is_float']);
        $this->assertInstanceOf(BooleanType::class, $typeMap['is_bool']);
        $this->assertInstanceOf(ArrayType::class, $typeMap['is_array']);
        $this->assertInstanceOf(ObjectType::class, $typeMap['is_object']);
    }

    public function testExtractFunctionNameFromStringCallback(): void
    {
        $validCallback = new String_('is_string');
        $invalidCallback = new String_('custom_function');

        $this->assertSame('is_string', $this->helper->extractFunctionNameFromStringCallback($validCallback));
        $this->assertNull($this->helper->extractFunctionNameFromStringCallback($invalidCallback));
    }

    public function testExtractFunctionNameFromFirstClassCallable(): void
    {
        // Valid first-class callable: is_string(...)
        $validFuncCall = new FuncCall(new Name('is_string'), [new VariadicPlaceholder()]);
        $this->assertSame('is_string', $this->helper->extractFunctionNameFromFirstClassCallable($validFuncCall));

        // Invalid function name
        $invalidFuncCall = new FuncCall(new Name('custom_function'), [new VariadicPlaceholder()]);
        $this->assertNull($this->helper->extractFunctionNameFromFirstClassCallable($invalidFuncCall));

        // Not a first-class callable (no VariadicPlaceholder)
        $notFirstClass = new FuncCall(new Name('is_string'), []);
        $this->assertNull($this->helper->extractFunctionNameFromFirstClassCallable($notFirstClass));

        // FuncCall with non-Name name (e.g., Variable or other expression)
        $nonNameFuncCall = new FuncCall(new Variable('func'), [new VariadicPlaceholder()]);
        $this->assertNull($this->helper->extractFunctionNameFromFirstClassCallable($nonNameFuncCall));
    }

    public function testIsFirstClassCallable(): void
    {
        $firstClassCallable = new FuncCall(new Name('is_string'), [new VariadicPlaceholder()]);
        $regularCall = new FuncCall(new Name('is_string'), []);

        $this->assertTrue($this->helper->isFirstClassCallable($firstClassCallable));
        $this->assertFalse($this->helper->isFirstClassCallable($regularCall));
    }

    public function testGetTargetTypeForFunction(): void
    {
        $this->assertInstanceOf(StringType::class, $this->helper->getTargetTypeForFunction('is_string'));
        $this->assertInstanceOf(IntegerType::class, $this->helper->getTargetTypeForFunction('is_int'));
        $this->assertInstanceOf(FloatType::class, $this->helper->getTargetTypeForFunction('is_float'));
        $this->assertInstanceOf(BooleanType::class, $this->helper->getTargetTypeForFunction('is_bool'));
        $this->assertInstanceOf(ArrayType::class, $this->helper->getTargetTypeForFunction('is_array'));
        $this->assertInstanceOf(ObjectType::class, $this->helper->getTargetTypeForFunction('is_object'));

        $this->assertNull($this->helper->getTargetTypeForFunction('custom_function'));
    }

    public function xtestRemoveFalsyTypesFromUnion(): void
    {
        $stringType = new StringType();
        $intType = new IntegerType();
        $nullType = $this->createMockNullType();
        $falseType = $this->createMockFalseType();

        $unionType = new UnionType([$stringType, $intType, $nullType, $falseType]);
        $filteredTypes = $this->helper->removeFalsyTypesFromUnion($unionType);

        $this->assertCount(2, $filteredTypes);
        $this->assertContains($stringType, $filteredTypes);
        $this->assertContains($intType, $filteredTypes);
        $this->assertNotContains($nullType, $filteredTypes);
        $this->assertNotContains($falseType, $filteredTypes);
    }

    public function testRemoveFalsyTypesWithOnlyNullAndFalse(): void
    {
        $nullType = $this->createMockNullType();
        $falseType = $this->createMockFalseType();

        $unionType = new UnionType([$nullType, $falseType]);
        $filteredTypes = $this->helper->removeFalsyTypesFromUnion($unionType);

        $this->assertCount(0, $filteredTypes);
    }

    public function testRemoveFalsyTypesWithMixedOrder(): void
    {
        $stringType = new StringType();
        $nullType = $this->createMockNullType();
        $intType = new IntegerType();
        $falseType = $this->createMockFalseType();

        // Different order to test continue vs break mutations
        $unionType = new UnionType([$nullType, $stringType, $falseType, $intType]);
        $filteredTypes = $this->helper->removeFalsyTypesFromUnion($unionType);

        $this->assertCount(2, $filteredTypes);
        $this->assertContains($stringType, $filteredTypes);
        $this->assertContains($intType, $filteredTypes);
    }

    public function testFilterUnionTypeByTarget(): void
    {
        $stringType = new StringType();
        $intType = new IntegerType();
        $floatType = new FloatType();
        $targetType = new StringType();

        $unionType = new UnionType([$stringType, $intType, $floatType]);
        $filteredTypes = $this->helper->filterUnionTypeByTarget($unionType, $targetType);

        $this->assertCount(1, $filteredTypes);
        $this->assertContains($stringType, $filteredTypes);
        $this->assertNotContains($intType, $filteredTypes);
        $this->assertNotContains($floatType, $filteredTypes);
    }

    public function testFilterUnionTypeByTargetWithMultipleMatches(): void
    {
        $stringType1 = new StringType();
        $stringType2 = new StringType();
        $intType = new IntegerType();
        $targetType = new StringType();

        $unionType = new UnionType([$stringType1, $intType, $stringType2]);
        $filteredTypes = $this->helper->filterUnionTypeByTarget($unionType, $targetType);

        // This test kills the ArrayOneItem mutation - we need ALL matching types, not just one
        $this->assertCount(2, $filteredTypes);
        $this->assertContains($stringType1, $filteredTypes);
        $this->assertContains($stringType2, $filteredTypes);
        $this->assertNotContains($intType, $filteredTypes);
    }

    public function testCreateGenericTypeWithFilteredValues(): void
    {
        $keyType = new IntegerType();
        $stringType = new StringType();
        $intType = new IntegerType();

        // Single type
        $result = $this->helper->createGenericTypeWithFilteredValues($keyType, [$stringType]);
        $this->assertInstanceOf(GenericObjectType::class, $result);
        $this->assertSame(Standard::class, $result->getClassName());

        $genericTypes = $result->getTypes();
        $this->assertCount(2, $genericTypes);
        $this->assertSame($keyType, $genericTypes[0]);
        $this->assertSame($stringType, $genericTypes[1]);

        // Multiple types (creates union)
        $result = $this->helper->createGenericTypeWithFilteredValues($keyType, [$stringType, $intType]);
        $this->assertInstanceOf(GenericObjectType::class, $result);

        $genericTypes = $result->getTypes();
        $this->assertCount(2, $genericTypes);
        $this->assertSame($keyType, $genericTypes[0]);
        $this->assertInstanceOf(UnionType::class, $genericTypes[1]);

        // Empty array
        $result = $this->helper->createGenericTypeWithFilteredValues($keyType, []);
        $this->assertNull($result);
    }

    public function testIsValidReturnTypeStructure(): void
    {
        // Valid structure
        $validType = new GenericObjectType(Standard::class, [new IntegerType(), new StringType()]);
        $this->assertTrue($this->helper->isValidReturnTypeStructure($validType));

        // Invalid: not an object
        $nonObjectType = $this->createMock(Type::class);
        $nonObjectType->method('isObject')->willReturn(TrinaryLogic::createNo());
        $this->assertFalse($this->helper->isValidReturnTypeStructure($nonObjectType));

        // Invalid: wrong class
        $wrongClassType = new GenericObjectType('SomeOtherClass', [new IntegerType(), new StringType()]);
        $this->assertFalse($this->helper->isValidReturnTypeStructure($wrongClassType));

        // Invalid: wrong number of generic types
        $wrongCountType = new GenericObjectType(Standard::class, [new IntegerType()]);
        $this->assertFalse($this->helper->isValidReturnTypeStructure($wrongCountType));

        // Invalid: no getTypes method
        $mockType = $this->createMock(Type::class);
        $mockType->method('isObject')->willReturn(TrinaryLogic::createYes());
        $mockType->method('getObjectClassNames')->willReturn([Standard::class]);
        // Don't mock getTypes since it doesn't exist on the base Type interface
        $this->assertFalse($this->helper->isValidReturnTypeStructure($mockType));
    }

    public function testIsValidReturnTypeStructureWithInvalidGenericTypes(): void
    {
        // Test that kills the LogicalAnd mutation: both keyType AND valueType must be Type instances
        $mockType = $this->createMock(GenericObjectType::class);
        $mockType->method('isObject')->willReturn(TrinaryLogic::createYes());
        $mockType->method('getObjectClassNames')->willReturn([Standard::class]);
        $mockType->method('getTypes')->willReturn([new StringType(), 'not_a_type_instance']);

        $this->assertFalse($this->helper->isValidReturnTypeStructure($mockType));
    }

    public function testExtractKeyAndValueTypesWithInvalidStructure(): void
    {
        // Test that kills the LogicalOr mutation in extractKeyAndValueTypes
        $mockType = $this->createMock(Type::class);
        $mockType->method('isObject')->willReturn(TrinaryLogic::createYes());
        $mockType->method('getObjectClassNames')->willReturn([Standard::class]);
        // This type doesn't have getTypes method, so the method_exists check will fail

        $result = $this->helper->extractKeyAndValueTypes($mockType);
        $this->assertNull($result);
    }

    public function xtestExtractKeyAndValueTypesWithWrongCountGetTypes(): void
    {
        // Test that kills the LogicalOr mutation: (!is_array($genericTypes) || 2 !== count($genericTypes))
        // We need to test the case where getTypes() returns an array with wrong count
        $mockType = $this->createMock(GenericObjectType::class);
        $mockType->method('isObject')->willReturn(TrinaryLogic::createYes());
        $mockType->method('getObjectClassNames')->willReturn([Standard::class]);
        $mockType->method('getTypes')->willReturn([new StringType()]); // Array with wrong count (1 instead of 2)

        $result = $this->helper->extractKeyAndValueTypes($mockType);
        $this->assertNull($result);
    }

    public function testExtractKeyAndValueTypes(): void
    {
        $keyType = new IntegerType();
        $valueType = new StringType();
        $validType = new GenericObjectType(Standard::class, [$keyType, $valueType]);

        $result = $this->helper->extractKeyAndValueTypes($validType);
        $this->assertNotNull($result);
        $this->assertCount(2, $result);
        $this->assertSame($keyType, $result[0]);
        $this->assertSame($valueType, $result[1]);

        // Invalid structure
        $invalidType = $this->createMock(Type::class);
        $invalidType->method('isObject')->willReturn(TrinaryLogic::createNo());
        $result = $this->helper->extractKeyAndValueTypes($invalidType);
        $this->assertNull($result);
    }

    public function testExtractKeyAndValueTypesMethodExistsEdgeCase(): void
    {
        // Test the method_exists check (line 222)
        $mockType = $this->createMock(Type::class);
        $mockType->method('isObject')->willReturn(TrinaryLogic::createYes());
        $mockType->method('getObjectClassNames')->willReturn([Standard::class]);
        // Don't mock getTypes - this should trigger the method_exists check

        $result = $this->helper->extractKeyAndValueTypes($mockType);
        $this->assertNull($result);
    }

    public function testExtractKeyAndValueTypesWrongGenericCount(): void
    {
        // Test the count check (line 228)
        $mockType = $this->createMock(GenericObjectType::class);
        $mockType->method('isObject')->willReturn(TrinaryLogic::createYes());
        $mockType->method('getObjectClassNames')->willReturn([Standard::class]);
        $mockType->method('getTypes')->willReturn([new StringType()]); // Wrong count - should be 2

        $result = $this->helper->extractKeyAndValueTypes($mockType);
        $this->assertNull($result);
    }

    private function createMockNullType(): Type
    {
        $mock = $this->createMock(Type::class);
        $mock->method('isNull')->willReturn(TrinaryLogic::createYes());
        $mock->method('isFalse')->willReturn(TrinaryLogic::createNo());
        return $mock;
    }

    private function createMockFalseType(): Type
    {
        $mock = $this->createMock(Type::class);
        $mock->method('isNull')->willReturn(TrinaryLogic::createNo());
        $mock->method('isFalse')->willReturn(TrinaryLogic::createYes());
        return $mock;
    }
}
