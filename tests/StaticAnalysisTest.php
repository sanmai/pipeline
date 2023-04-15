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

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use Pipeline\Standard;
use ReflectionClass;
use ReflectionMethod;
use Traversable;

/**
 * @coversNothing
 *
 * @internal
 */
final class StaticAnalysisTest extends TestCase
{
    public function testIsNotFinal(): void
    {
        $pipelineClass = new ReflectionClass(Standard::class);

        $this->assertFalse($pipelineClass->isFinal());
    }

    public static function provideMethods(): iterable
    {
        $pipelineClass = new ReflectionClass(Standard::class);

        foreach ($pipelineClass->getMethods() as $method) {
            yield $method->name => [$method];
        }
    }

    /**
     * @dataProvider provideMethods
     */
    public function testPublicMethodsAreNotFinal(ReflectionMethod $method): void
    {
        $this->assertFalse($method->isFinal());
    }

    /**
     * @dataProvider provideMethods
     */
    public function testAllMethodsArePublicOrPrivate(ReflectionMethod $method): void
    {
        $this->assertFalse($method->isProtected());
        $this->assertTrue($method->isPublic() || $method->isPrivate());
    }

    /**
     * Test the interface is compatible with PHP 7.1 variety.
     */
    public function testInterfaceCompatibilityPHP71(): void
    {
        $example = new class() extends Standard {
            private $input;

            public function __construct(iterable $input = null)
            {
                $this->input = $input;
            }

            public function append(iterable $values = null): self
            {
                return $this;
            }

            public function push(...$vector): self
            {
                return $this;
            }

            public function prepend(iterable $values = null): self
            {
                return $this;
            }

            public function unshift(...$vector): self
            {
                return $this;
            }

            public function flatten(): self
            {
                return $this;
            }

            public function unpack(?callable $func = null): self
            {
                return $this;
            }

            public function chunk(int $length, bool $preserve_keys = false): self
            {
                return $this;
            }

            public function map(?callable $func = null): self
            {
                return $this;
            }

            public function cast(callable $func = null): self
            {
                return $this;
            }

            public function filter(?callable $func = null): self
            {
                return $this;
            }

            public function reduce(?callable $func = null, $initial = null)
            {
                return null;
            }

            public function fold($initial, ?callable $func = null)
            {
                return null;
            }

            public function getIterator(): Traversable
            {
                return new ArrayIterator([]);
            }

            public function toArray(bool $preserve_keys = false): array
            {
                return [];
            }

            public function count(): int
            {
                return 0;
            }

            public function slice(int $offset, ?int $length = null)
            {
                return $this;
            }

            public function zip(iterable ...$inputs)
            {
                return $this;
            }

            public function reservoir(int $size, ?callable $weightFunc = null): array
            {
                return [];
            }

            public function min()
            {
                return 0;
            }

            public function max()
            {
                return 0;
            }

            public function flip()
            {
                return $this;
            }
        };

        $this->assertSame(0, $example->count());
    }
}
