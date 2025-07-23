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

use PHPStan\Testing\TypeInferenceTestCase;

/**
 * @covers \Pipeline\PHPStan\FilterReturnTypeExtension
 */
class ExtensionTypeInferenceTest extends TypeInferenceTestCase
{
    /**
     * @return iterable<mixed>
     */
    public static function dataFileAsserts(): iterable
    {
        // Use existing test files from tests/Inference/
        yield from self::gatherAssertTypes(__DIR__ . '/../Inference/FilterTypeNarrowingSimpleTest.php');
        yield from self::gatherAssertTypes(__DIR__ . '/../Inference/FilterTypeNarrowingAdvancedTest.php');
    }

    /**
     * @dataProvider dataFileAsserts
     */
    public function testFileAsserts(
        string $assertType,
        string $file,
        ...$args
    ): void {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/../../phpstan-extension.neon',
        ];
    }
}
