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
use function file_get_contents;
use function implode;
use function Pipeline\take;
use function preg_match_all;
use function sprintf;

/**
 * Test documentation has sections for all public methods.
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
        self::$readme = file_get_contents(__DIR__.'/../README.md');

        preg_match_all('/^#.*/m', self::$readme, $matches);
        self::$headers = implode("\n", $matches[0]);
    }

    public static function provideMethods(): iterable
    {
        $reflection = new ReflectionClass(new Standard());
        $interfaces = $reflection->getInterfaces();

        return take($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(function (ReflectionMethod $method) use ($interfaces) {
                foreach ($interfaces as $interface) {
                    if ($interface->hasMethod($method->getName())) {
                        return false;
                    }
                }

                return true;
            })
            ->cast(fn (ReflectionMethod $method) => [$method->getName()]);
    }

    /**
     * @dataProvider provideMethods
     */
    public function testMethod(string $methodName): void
    {
        $this->assertMatchesRegularExpression(
            sprintf('/^##.*(->|`)%s\(\)`/m', $methodName),
            self::$headers
        );
    }
}
