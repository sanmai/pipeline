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

namespace Tests\Pipeline;

use PHPUnit\Framework\TestCase;
use Pipeline\Standard;
use ReflectionClass;
use ReflectionMethod;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

use function Pipeline\take;
use function preg_match;
use function str_replace;
use function trim;
use function str_contains;

/**
 * @internal
 */
#[CoversNothing]
class TypeConsistencyTest extends TestCase
{
    #[DataProvider('providePublicMethods')]
    public function testGenericReturnHasMatchingSelfOut(ReflectionMethod $method): void
    {
        $methodName = $method->getName();
        $docComment = $method->getDocComment();

        if (false === $docComment) {
            $this->markTestSkipped("Method {$methodName} has no docblock");
        }

        // Extract annotations
        $returnType = self::firstMatch('/@return           \s+(.+?<.*?>.*?)(?:\n|\*\/)/xs', $docComment);
        $selfOutType = self::firstMatch('/@phpstan-self-out\s+(.+?<.*?>.*?)(?:\n|\*\/)/xs', $docComment);

        if (!str_contains($returnType, 'Standard')) {
            $this->markTestSkipped("Method {$methodName} does not return the Standard type");
        }

        $expectedSelfOutType = str_replace('Standard', 'self', $returnType);

        $this->assertSame($expectedSelfOutType, $selfOutType, "Method {$methodName} has mismatched return '$returnType' and phpstan-self-out '$selfOutType' annotations");
    }

    public static function providePublicMethods(): iterable
    {
        $class = new ReflectionClass(Standard::class);

        return take($class->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(static fn(ReflectionMethod $method) => !$method->isConstructor() && !$method->isDestructor())
            ->map(static fn(ReflectionMethod $method) => yield $method->getName() => [$method]);
    }

    private static function firstMatch(string $pattern, string $subject): string
    {
        if (!preg_match($pattern, $subject, $matches)) {
            return '';
        }

        return trim($matches[1]);
    }
}
