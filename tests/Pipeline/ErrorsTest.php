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

namespace Pipeline;

use PHPUnit\Framework\TestCase;

class ErrorsTest extends TestCase
{
    protected function setUp()
    {
        if (ini_get('zend.assertions') != 1) {
            $this->markTestSkipped('This test case requires internal assertions being enabled');
        }
    }

    public function testAssertFalse()
    {
        $this->expectException(\AssertionError::class);
        assert(false);
    }

    public function testInvalidInitialGenerator()
    {
        $this->expectException(\AssertionError::class);

        $pipeline = new Simple();
        $pipeline->map(function ($a) {
            // Shall never be called
            $this->fail();
            yield $a;
        });
    }

    public function testNotGenerator()
    {
        $this->expectException(\AssertionError::class);

        $pipeline = new Simple();
        $pipeline->map(function () {
            return 0;
        });
    }

    public function testObjectNotGenerator()
    {
        $this->expectException(\AssertionError::class);

        $pipeline = new Simple();
        $pipeline->map($this);
    }

    public function __invoke()
    {
    }
}
