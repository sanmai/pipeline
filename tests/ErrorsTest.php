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

use ArgumentCountError;
use function class_exists;
use function is_callable;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;
use Pipeline\Standard;
use TypeError;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class ErrorsTest extends TestCase
{
    private function expectExceptionFallback(string $exception, string $php70fallback): void
    {
        if (class_exists($exception)) {
            $this->expectException($exception);
        } else {
            // fallback for PHP 7.0
            $this->expectException($php70fallback);
        }
    }

    public function testInvalidInitialGeneratorWithArguments(): void
    {
        // PHP 7.1+ fails with: Too few arguments to function...
        // PHP 7.0 fails with: Missing argument 1 for...
        $this->expectExceptionMessage('argument');
        $this->expectExceptionFallback(ArgumentCountError::class, Warning::class);

        $pipeline = new Standard();
        $pipeline->map(function ($unused) {
            $this->fail('Shall never be called');

            return $unused;
        });
    }

    /**
     * @covers \Pipeline\Standard::unpack()
     */
    public function testUnpackNonIterable(): void
    {
        $pipeline = new \Pipeline\Standard();

        $pipeline->map(function () {
            yield 1;
            yield [2, 3];
        })->unpack();

        $this->expectException(TypeError::class);
        // Older PHPUnit on 7.1 does not have this method
        if (is_callable([$this, 'expectExceptionMessageMatches'])) {
            $this->expectExceptionMessageMatches('/must .* (iterable|Traversable)/');
        }
        $pipeline->toArray();
    }
}
