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

    public function provideMethods(): iterable
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
}
