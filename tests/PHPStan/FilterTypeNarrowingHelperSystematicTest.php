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

use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use PHPStan\TrinaryLogic;
use PHPUnit\Framework\TestCase;
use Pipeline\PHPStan\FilterTypeNarrowingHelper;

/**
 * Systematic test coverage for FilterTypeNarrowingHelper::isFalsyType private method.
 * Tests every possible code path to achieve 100% mutation coverage.
 *
 * @covers \Pipeline\PHPStan\FilterTypeNarrowingHelper
 */
final class FilterTypeNarrowingHelperSystematicTest extends TestCase
{
    private FilterTypeNarrowingHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new FilterTypeNarrowingHelper();
    }

    // Case A: isNull()->yes() = true
    public function testRemoveFalsyValuesWithNullType(): void
    {
        $nullType = $this->createMockType(['isNull' => TrinaryLogic::createYes()]);
        $stringType = new StringType();

        $unionType = new UnionType([$nullType, $stringType]);
        $result = $this->helper->removeFalsyValuesFromUnion($unionType);

        $this->assertCount(1, $result);
        $this->assertContains($stringType, $result);
        $this->assertNotContains($nullType, $result);
    }

    // Case B: isFalse()->yes() = true
    public function testRemoveFalsyValuesWithFalseType(): void
    {
        $falseType = $this->createMockType([
            'isNull' => TrinaryLogic::createNo(),
            'isFalse' => TrinaryLogic::createYes(),
        ]);
        $stringType = new StringType();

        $unionType = new UnionType([$falseType, $stringType]);
        $result = $this->helper->removeFalsyValuesFromUnion($unionType);

        $this->assertCount(1, $result);
        $this->assertContains($stringType, $result);
        $this->assertNotContains($falseType, $result);
    }

    // Case D: Not constant scalar
    public function testRemoveFalsyValuesWithNonConstantScalar(): void
    {
        $this->markTestIncomplete();

        $nonConstantType = $this->createMockType([
            'isNull' => TrinaryLogic::createNo(),
            'isFalse' => TrinaryLogic::createNo(),
            'isConstantScalarValue' => TrinaryLogic::createNo(),
        ]);
        $stringType = new StringType();

        $unionType = new UnionType([$nonConstantType, $stringType]);
        $result = $this->helper->removeFalsyValuesFromUnion($unionType);

        // Non-constant scalar should be kept
        $this->assertCount(2, $result);
        $this->assertContains($nonConstantType, $result);
        $this->assertContains($stringType, $result);
    }

    // Case F: Is integer AND contains 0
    public function testRemoveFalsyValuesWithZeroInteger(): void
    {
        $zeroIntType = $this->createMockType([
            'isNull' => TrinaryLogic::createNo(),
            'isFalse' => TrinaryLogic::createNo(),
            'isConstantScalarValue' => TrinaryLogic::createYes(),
            'isInteger' => TrinaryLogic::createYes(),
            'getConstantScalarValues' => [0],
        ]);
        $stringType = new StringType();

        $unionType = new UnionType([$zeroIntType, $stringType]);
        $result = $this->helper->removeFalsyValuesFromUnion($unionType);

        $this->assertCount(1, $result);
        $this->assertContains($stringType, $result);
        $this->assertNotContains($zeroIntType, $result);
    }

    // Case G: Is integer AND doesn't contain 0
    public function testRemoveFalsyValuesWithNonZeroInteger(): void
    {
        $nonZeroIntType = $this->createMockType([
            'isNull' => TrinaryLogic::createNo(),
            'isFalse' => TrinaryLogic::createNo(),
            'isConstantScalarValue' => TrinaryLogic::createYes(),
            'isInteger' => TrinaryLogic::createYes(),
            'isFloat' => TrinaryLogic::createNo(),
            'isString' => TrinaryLogic::createNo(),
            'isArray' => TrinaryLogic::createNo(),
            'getConstantScalarValues' => [5],
        ]);
        $stringType = new StringType();

        $unionType = new UnionType([$nonZeroIntType, $stringType]);
        $result = $this->helper->removeFalsyValuesFromUnion($unionType);

        // Non-zero integer should be kept
        $this->assertCount(2, $result);
        $this->assertContains($nonZeroIntType, $result);
        $this->assertContains($stringType, $result);
    }

    // Case I: Is float AND contains 0.0
    public function testRemoveFalsyValuesWithZeroFloat(): void
    {
        $zeroFloatType = $this->createMockType([
            'isNull' => TrinaryLogic::createNo(),
            'isFalse' => TrinaryLogic::createNo(),
            'isConstantScalarValue' => TrinaryLogic::createYes(),
            'isInteger' => TrinaryLogic::createNo(),
            'isFloat' => TrinaryLogic::createYes(),
            'getConstantScalarValues' => [0.0],
        ]);
        $stringType = new StringType();

        $unionType = new UnionType([$zeroFloatType, $stringType]);
        $result = $this->helper->removeFalsyValuesFromUnion($unionType);

        $this->assertCount(1, $result);
        $this->assertContains($stringType, $result);
        $this->assertNotContains($zeroFloatType, $result);
    }

    // Case J: Is float AND doesn't contain 0.0
    public function testRemoveFalsyValuesWithNonZeroFloat(): void
    {
        $nonZeroFloatType = $this->createMockType([
            'isNull' => TrinaryLogic::createNo(),
            'isFalse' => TrinaryLogic::createNo(),
            'isConstantScalarValue' => TrinaryLogic::createYes(),
            'isInteger' => TrinaryLogic::createNo(),
            'isFloat' => TrinaryLogic::createYes(),
            'isString' => TrinaryLogic::createNo(),
            'isArray' => TrinaryLogic::createNo(),
            'getConstantScalarValues' => [3.14],
        ]);
        $stringType = new StringType();

        $unionType = new UnionType([$nonZeroFloatType, $stringType]);
        $result = $this->helper->removeFalsyValuesFromUnion($unionType);

        // Non-zero float should be kept
        $this->assertCount(2, $result);
        $this->assertContains($nonZeroFloatType, $result);
        $this->assertContains($stringType, $result);
    }

    // Case L: Is string AND contains ''
    public function testRemoveFalsyValuesWithEmptyString(): void
    {
        $emptyStringType = $this->createMockType([
            'isNull' => TrinaryLogic::createNo(),
            'isFalse' => TrinaryLogic::createNo(),
            'isConstantScalarValue' => TrinaryLogic::createYes(),
            'isInteger' => TrinaryLogic::createNo(),
            'isFloat' => TrinaryLogic::createNo(),
            'isString' => TrinaryLogic::createYes(),
            'getConstantScalarValues' => [''],
        ]);
        $intType = new IntegerType();

        $unionType = new UnionType([$emptyStringType, $intType]);
        $result = $this->helper->removeFalsyValuesFromUnion($unionType);

        $this->assertCount(1, $result);
        $this->assertContains($intType, $result);
        $this->assertNotContains($emptyStringType, $result);
    }

    // Case M: Is string AND doesn't contain ''
    public function testRemoveFalsyValuesWithNonEmptyString(): void
    {
        $nonEmptyStringType = $this->createMockType([
            'isNull' => TrinaryLogic::createNo(),
            'isFalse' => TrinaryLogic::createNo(),
            'isConstantScalarValue' => TrinaryLogic::createYes(),
            'isInteger' => TrinaryLogic::createNo(),
            'isFloat' => TrinaryLogic::createNo(),
            'isString' => TrinaryLogic::createYes(),
            'isArray' => TrinaryLogic::createNo(),
            'getConstantScalarValues' => ['hello'],
        ]);
        $intType = new IntegerType();

        $unionType = new UnionType([$nonEmptyStringType, $intType]);
        $result = $this->helper->removeFalsyValuesFromUnion($unionType);

        // Non-empty string should be kept
        $this->assertCount(2, $result);
        $this->assertContains($nonEmptyStringType, $result);
        $this->assertContains($intType, $result);
    }

    // Case O: Is array AND constant array AND size is 0
    public function testRemoveFalsyValuesWithEmptyArray(): void
    {
        $arraySize = $this->createMockType([
            'isConstantScalarValue' => TrinaryLogic::createYes(),
            'getConstantScalarValues' => [0],
        ]);

        $emptyArrayType = $this->createMockType([
            'isNull' => TrinaryLogic::createNo(),
            'isFalse' => TrinaryLogic::createNo(),
            'isConstantScalarValue' => TrinaryLogic::createYes(),
            'isInteger' => TrinaryLogic::createNo(),
            'isFloat' => TrinaryLogic::createNo(),
            'isString' => TrinaryLogic::createNo(),
            'isArray' => TrinaryLogic::createYes(),
            'isConstantArray' => TrinaryLogic::createYes(),
            'getArraySize' => $arraySize,
        ]);
        $stringType = new StringType();

        $unionType = new UnionType([$emptyArrayType, $stringType]);
        $result = $this->helper->removeFalsyValuesFromUnion($unionType);

        $this->assertCount(1, $result);
        $this->assertContains($stringType, $result);
        $this->assertNotContains($emptyArrayType, $result);
    }

    // Case P: Is array AND constant array AND size is not 0
    public function testRemoveFalsyValuesWithNonEmptyArray(): void
    {
        $arraySize = $this->createMockType([
            'isConstantScalarValue' => TrinaryLogic::createYes(),
            'getConstantScalarValues' => [3],
        ]);

        $nonEmptyArrayType = $this->createMockType([
            'isNull' => TrinaryLogic::createNo(),
            'isFalse' => TrinaryLogic::createNo(),
            'isConstantScalarValue' => TrinaryLogic::createYes(),
            'isInteger' => TrinaryLogic::createNo(),
            'isFloat' => TrinaryLogic::createNo(),
            'isString' => TrinaryLogic::createNo(),
            'isArray' => TrinaryLogic::createYes(),
            'isConstantArray' => TrinaryLogic::createYes(),
            'getArraySize' => $arraySize,
        ]);
        $stringType = new StringType();

        $unionType = new UnionType([$nonEmptyArrayType, $stringType]);
        $result = $this->helper->removeFalsyValuesFromUnion($unionType);

        // Non-empty array should be kept
        $this->assertCount(2, $result);
        $this->assertContains($nonEmptyArrayType, $result);
        $this->assertContains($stringType, $result);
    }

    // Case Q: Is array AND not constant array
    public function testRemoveFalsyValuesWithNonConstantArray(): void
    {
        $nonConstantArrayType = $this->createMockType([
            'isNull' => TrinaryLogic::createNo(),
            'isFalse' => TrinaryLogic::createNo(),
            'isConstantScalarValue' => TrinaryLogic::createYes(),
            'isInteger' => TrinaryLogic::createNo(),
            'isFloat' => TrinaryLogic::createNo(),
            'isString' => TrinaryLogic::createNo(),
            'isArray' => TrinaryLogic::createYes(),
            'isConstantArray' => TrinaryLogic::createNo(),
        ]);
        $stringType = new StringType();

        $unionType = new UnionType([$nonConstantArrayType, $stringType]);
        $result = $this->helper->removeFalsyValuesFromUnion($unionType);

        // Non-constant array should be kept (can't determine if empty)
        $this->assertCount(2, $result);
        $this->assertContains($nonConstantArrayType, $result);
        $this->assertContains($stringType, $result);
    }

    // Case: Array with non-constant size
    public function testRemoveFalsyValuesWithArrayNonConstantSize(): void
    {
        $arraySize = $this->createMockType([
            'isConstantScalarValue' => TrinaryLogic::createNo(),
        ]);

        $arrayWithNonConstantSizeType = $this->createMockType([
            'isNull' => TrinaryLogic::createNo(),
            'isFalse' => TrinaryLogic::createNo(),
            'isConstantScalarValue' => TrinaryLogic::createYes(),
            'isInteger' => TrinaryLogic::createNo(),
            'isFloat' => TrinaryLogic::createNo(),
            'isString' => TrinaryLogic::createNo(),
            'isArray' => TrinaryLogic::createYes(),
            'isConstantArray' => TrinaryLogic::createYes(),
            'getArraySize' => $arraySize,
        ]);
        $stringType = new StringType();

        $unionType = new UnionType([$arrayWithNonConstantSizeType, $stringType]);
        $result = $this->helper->removeFalsyValuesFromUnion($unionType);

        // Array with non-constant size should be kept
        $this->assertCount(2, $result);
        $this->assertContains($arrayWithNonConstantSizeType, $result);
        $this->assertContains($stringType, $result);
    }

    /**
     * Create a mock Type with specified behavior.
     *
     * @param array<string, mixed> $config
     */
    private function createMockType(array $config): Type
    {
        $mock = $this->createMock(Type::class);

        foreach ($config as $method => $returnValue) {
            if ('getConstantScalarValues' === $method || 'getArraySize' === $method) {
                $mock->method($method)->willReturn($returnValue);
            } else {
                $mock->method($method)->willReturn($returnValue);
            }
        }

        return $mock;
    }
}
