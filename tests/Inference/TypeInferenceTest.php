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

/**
 *
 * @internal
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
#[\PHPUnit\Framework\Attributes\Group('integration')]
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
        $this->assertArrayHasKey('Foo', $result);
        $this->assertSame('Foo', $result['Foo']);
    }
}
