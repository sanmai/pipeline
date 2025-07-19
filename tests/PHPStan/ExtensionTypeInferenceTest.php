<?php

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