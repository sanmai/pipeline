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

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Pipeline\Standard;
use ReflectionClass;
use ReflectionMethod;

use function file_get_contents;
use function implode;
use function Pipeline\take;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function strpos;
use function substr;

/**
 * Test documentation has sections for all public methods.
 *
 * @group documentation
 *
 * @coversNothing
 *
 * @internal
 */
final class DocumentationTest extends TestCase
{
    private static string $readme;
    private static string $headers;

    public static function setUpBeforeClass(): void
    {
        self::$readme = file_get_contents(__DIR__ . '/../README.md');

        preg_match_all('/^#.*/m', self::$readme, $matches);
        self::$headers = implode("\n", $matches[0]);
    }

    /**
     * @param array<ReflectionClass> $interfaces
     */
    private static function interfaceFilter(array $interfaces, ReflectionMethod $method): bool
    {
        foreach ($interfaces as $interface) {
            if ($interface->hasMethod($method->getName())) {
                return false;
            }
        }

        return true;
    }

    public static function provideMethods(): iterable
    {
        $reflection = new ReflectionClass(new Standard());
        $interfaces = $reflection->getInterfaces();

        return take($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn(ReflectionMethod $method) => self::interfaceFilter($interfaces, $method))
            ->filter(fn(ReflectionMethod $method) => false === str_contains($method->getDocComment() ?: '', '@deprecated'))
            ->cast(fn(ReflectionMethod $method) => [$method->getName()]);
    }

    public function testProvideMethods(): void
    {
        $methods = take(self::provideMethods())->toList();

        $this->assertGreaterThan(0, $methods, 'No public methods found.');
    }

    /**
     * @dataProvider provideMethods
     */
    public function testMethodHasMention(string $methodName): void
    {
        try {
            $this->assertMatchesRegularExpression(
                sprintf('/`%s\(\)`/', $methodName),
                self::$readme,
                "There's no mention of {$methodName}."
            );
        } catch (ExpectationFailedException $e) {
            $message = $e->getMessage();
            $this->fail(substr($message, 0, strpos($message, "\n")));
        }
    }

    /**
     * @dataProvider provideMethods
     */
    public function testMethodHasHeader(string $methodName): void
    {
        try {
            $this->assertMatchesRegularExpression(
                sprintf('/^##.*(->|`)%s\(\)`/m', $methodName),
                self::$headers,
                "There's no header dedicated to {$methodName}."
            );
        } catch (ExpectationFailedException $e) {
            $message = $e->getMessage();
            $this->markTestIncomplete(substr($message, 0, strpos($message, "\n")));
        }
    }
}
