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
use Pipeline\PHPStan\FilterReturnTypeExtension;
use Pipeline\Standard;

/**
 * Tests for FilterReturnTypeExtension.
 *
 * This test focuses on the basic functionality that can be easily unit tested.
 * For comprehensive type narrowing testing, see tests/Inference/FilterTypeNarrowingSimpleTest.php
 * which tests the actual behavior through PHPStan analysis.
 *
 * Additional PHPStan-specific testing can be done using:
 * - The data file: tests/PHPStan/data/filter-type-narrowing.php
 * - PHPStan's TypeInferenceTestCase framework (if installed separately)
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
}
