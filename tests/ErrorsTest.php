<?php
/*
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

use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;
use Pipeline\Standard;

/**
 * @covers \Pipeline\Standard
 */
class ErrorsTest extends TestCase
{
    private function expectExceptionFallback(string $exception, string $php70fallback)
    {
        if (class_exists($exception)) {
            $this->expectException($exception);
        } else {
            // fallback for PHP 7.0
            $this->expectException($php70fallback);
        }
    }

    public function testInvalidInitialGeneratorWithArguments()
    {
        // PHP 7.1+ fails with: Too few arguments to function...
        // PHP 7.0 fails with: Missing argument 1 for...
        $this->expectExceptionMessage('argument');
        $this->expectExceptionFallback(\ArgumentCountError::class, Warning::class);

        $pipeline = new Standard();
        $pipeline->map(function ($unused) {
            $this->fail('Shall never be called');

            return $unused;
        });
    }

    public function testPipelineInPipelineUsesSelf()
    {
        $pipeline = new Standard(new \ArrayIterator([2, 3, 5, 7, 11]));
        $pipeline->map(function ($prime) {
            yield $prime;
            yield $prime * 2;
        });

        $pipeline->map($pipeline)->filter(function ($i) {
            return $i % 2 != 0;
        });

        $this->expectExceptionMessage('Cannot rewind a generator that was already run');
        $this->expectExceptionFallback(\Exception::class, Error::class);

        $pipeline->reduce();
    }
}
