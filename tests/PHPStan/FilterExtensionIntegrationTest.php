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
