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

namespace Tests\Pipeline\Inference;

use Iterator;
use PHPUnit\Framework\TestCase;
use Pipeline\Standard;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Tests\Pipeline\Fixtures\Foo;

use function Pipeline\take;
use function preg_match;
use function str_contains;
use function dirname;
use function microtime;
use function random_int;
use function uniqid;

/**
 * @coversNothing
 * @group integration
 *
 * @internal
 */
class TypeInferenceTest extends TestCase
{
    public function testExample(): void
    {
        $this->expectOutputString("2\n4\n6\n");

        $foos = take(['a' => 1, 'b' => 2, 'c' => 3])
            ->cast(fn(int $n): int => $n * 2)
            ->cast(fn(int $n): Foo => new Foo($n));

        foreach ($foos as $value) {
            echo $value->bar();
        }
    }

    public function testExample2(): void
    {
        $this->expectOutputString("2\n4\n6\n");

        $foos2 = take(['a' => 1, 'b' => 2, 'c' => 3]);
        $foos2->cast(fn(int $n): int => $n * 2);
        $foos2->cast(fn(int $n): Foo => new Foo($n));

        foreach ($foos2 as $value) {
            echo $value->bar();
        }
    }

    public function testExample3(): void
    {
        $this->expectOutputString("2\n4\n6\n");

        $fooKeys = take(['a' => 1, 'b' => 2, 'c' => 3]);
        $fooKeys->map(static fn(int $n) => yield new Foo($n * 2) => $n * 2);

        foreach ($fooKeys as $foo => $value) {
            echo $foo->bar();
        }
    }

    public function testExample4(): void
    {
        $this->expectOutputString("2\n3\n");

        $fooKVals = new Standard();
        $fooKVals->map(static fn() => yield new Foo(2) => new Foo(3));

        foreach ($fooKVals as $foo => $value) {
            echo $foo->bar();
            echo $value->bar();
        }
    }

    public function testExample5(): void
    {
        $reflection = new ReflectionClass(Foo::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);

        $result1 = take($constructor->getParameters())
            ->cast(function ($param) {
                return $param->getName();
            })
            ->toList();

        $this->assertSame(['n'], $result1);
    }

    public function testExtractFixtureNamesFromTests(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__) . '/Fixtures', RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var Iterator<SplFileInfo> $iterator */
        $result = take($iterator)
            ->filter(fn(SplFileInfo $file) => 'php' === $file->getExtension())
            ->map(fn(SplFileInfo $file) => yield from $file->openFile('r'))
            ->filter(is_string(...))
            ->filter(fn(string $line) => str_contains($line, 'class'))
            ->cast(fn(string $line) => preg_match("#class (.+)\s#", $line, $matches) ? $matches[1] : null)
            ->filter(strict: true)
            ->map(fn(string $className) => yield $className => $className)
            ->toAssoc()
        ;

        /** @var array<string, string> $result */
        $this->assertSame(['Foo' => 'Foo'], $result);
    }

    /**
     * Test that demonstrates the issue with calling getConstantScalarValues()
     * on non-constant types during PHPStan analysis.
     *
     * This test creates a scenario where PHPStan's type inference would need
     * to analyze a filter operation on union types containing non-constant scalars.
     */
    public function testFilterTypeInferenceWithMixedConstantAndNonConstantTypes(): void
    {
        // Create a scenario that PHPStan needs to analyze
        // This involves union types with both constant and non-constant values

        /** @var array<int|float|string> $mixedInput */
        $mixedInput = [1, 2.5, 'hello'];

        // Add some values that create non-constant types at analysis time
        if (random_int(0, 1)) {
            $mixedInput[] = random_int(1, 100);  // Non-constant int
        }

        if (random_int(0, 1)) {
            $mixedInput[] = microtime(true);     // Non-constant float
        }

        if (random_int(0, 1)) {
            $mixedInput[] = uniqid();           // Non-constant string
        }

        // This filter operation will trigger PHPStan's FilterReturnTypeExtension
        // The type narrowing helper must handle both constant and non-constant types
        $pipeline = take($mixedInput);
        $filtered = $pipeline->filter();

        // The important part is that this compiles and runs without errors
        // PHPStan should not crash when analyzing this due to calling
        // getConstantScalarValues() on non-constant types
        $result = $filtered->toList();

        // We can't predict exact count due to random values, but should have some
        $this->assertNotEmpty($result);
    }
}
