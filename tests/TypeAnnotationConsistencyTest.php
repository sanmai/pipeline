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

use function Pipeline\take;
use function preg_match;
use function trim;

/**
 * @coversNothing
 *
 * @internal
 */
class TypeAnnotationConsistencyTest extends TestCase
{
    /**
     * @dataProvider providePublicMethods
     */
    public function testGenericReturnHasMatchingSelfOut(ReflectionMethod $method): void
    {
        $methodName = $method->getName();
        $docComment = $method->getDocComment();

        if (false === $docComment) {
            $this->markTestSkipped("Method {$methodName} has no docblock");
            return;
        }

        // Extract annotations
        $returnType = null;
        if (preg_match('/@return\s+(.+?)(?:\n|\*\/)/s', $docComment, $matches)) {
            $returnType = trim($matches[1]);
        }

        $selfOutType = null;
        if (preg_match('/@phpstan-self-out\s+(.+?)(?:\n|\*\/)/s', $docComment, $matches)) {
            $selfOutType = trim($matches[1]);
        }

        // Skip if no relevant annotations
        if (null === $returnType && null === $selfOutType) {
            $this->markTestSkipped("Method {$methodName} has no @return or @phpstan-self-out annotations");
            return;
        }

        // Skip terminal operations
        if (null !== $returnType && preg_match('/@return\s+(int|list|array|null|mixed|T(?!\w))/', "@return {$returnType}")) {
            $this->markTestSkipped("Method {$methodName} is a terminal operation");
            return;
        }

        // Validate Standard<Type> methods have matching @phpstan-self-out
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

        // Validate methods returning $this with @phpstan-self-out
        if ('$this' === $returnType && null !== $selfOutType) {
            $this->assertMatchesRegularExpression(
                '/self<.+>/',
                $selfOutType,
                "Method {$methodName} returns \$this but has invalid @phpstan-self-out format"
            );
            return;
        }

        $this->markTestSkipped("Method {$methodName} does not require validation");
    }

    public static function providePublicMethods(): array
    {
        $class = new ReflectionClass(\Pipeline\Standard::class);

        return take($class->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn($method) => !$method->isConstructor() && !$method->isDestructor())
            ->map(fn($method) => [$method])
            ->toList();
    }
}
