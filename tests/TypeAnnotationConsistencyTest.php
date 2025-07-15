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
use ReflectionClass;
use ReflectionMethod;

use function preg_match;
use function trim;

class TypeAnnotationConsistencyTest extends TestCase
{
    /**
     * @dataProvider provideMethodsWithGenericReturns
     * @coversNothing
     */
    public function testGenericReturnHasMatchingSelfOut(string $methodName, ?string $returnType, ?string $selfOutType): void
    {
        if (null === $returnType) {
            $this->markTestSkipped("Method {$methodName} has no @return annotation");
        }

        // Skip terminal operations and those that don't return self
        if (preg_match('/@return\s+(int|list|array|null|mixed|T(?!\w))/', $returnType)) {
            $this->markTestSkipped("Method {$methodName} is a terminal operation or has non-Standard return type");
        }

        // Extract the generic type from @return Standard<Type>
        if (preg_match('/Standard<(.+?)>/', $returnType, $returnMatches)) {
            $returnGeneric = trim($returnMatches[1]);

            $this->assertNotNull(
                $selfOutType,
                "Method {$methodName} has @return Standard<{$returnGeneric}> but no @phpstan-self-out annotation"
            );

            // Check if @phpstan-self-out has matching type
            if (!preg_match('/self<(.+?)>/', $selfOutType, $selfOutMatches)) {
                $this->fail("Method {$methodName} has invalid @phpstan-self-out format: {$selfOutType}");
                return;
            }

            $selfOutGeneric = trim($selfOutMatches[1]);

            $this->assertEquals(
                $returnGeneric,
                $selfOutGeneric,
                "Method {$methodName} has mismatched types: @return Standard<{$returnGeneric}> vs @phpstan-self-out self<{$selfOutGeneric}>"
            );
            return;
        }

        if ('$this' === $returnType && null !== $selfOutType) {
            // Method returns $this but has @phpstan-self-out - that's fine
            $this->assertTrue(true, "Method {$methodName} returns \$this and has @phpstan-self-out");
            return;
        }

        $this->markTestSkipped("Method {$methodName} does not return Standard<Type>");
    }

    public static function provideMethodsWithGenericReturns(): array
    {
        $class = new ReflectionClass(\Pipeline\Standard::class);
        $methods = [];

        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isDestructor()) {
                continue;
            }

            $docComment = $method->getDocComment();
            if (false === $docComment) {
                continue;
            }

            // Extract @return annotation
            $returnType = null;
            if (preg_match('/@return\s+(.+?)(?:\n|\*\/)/s', $docComment, $matches)) {
                $returnType = trim($matches[1]);
            }

            // Extract @phpstan-self-out annotation
            $selfOutType = null;
            if (preg_match('/@phpstan-self-out\s+(.+?)(?:\n|\*\/)/s', $docComment, $matches)) {
                $selfOutType = trim($matches[1]);
            }

            // Only include methods that have either annotation
            if (null !== $returnType || null !== $selfOutType) {
                $methods[$method->getName()] = [
                    $method->getName(),
                    $returnType,
                    $selfOutType,
                ];
            }
        }

        return $methods;
    }
}
