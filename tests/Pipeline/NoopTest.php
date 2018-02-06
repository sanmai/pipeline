<?php
/*
 * Copyright 2017 Alexey Kopytko <alexey@kopytko.com>
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

class NoopTest extends TestCase
{
    public function testNoop()
    {
        $pipeline = new Noop();

        $pipeline->map(function ($i) {
            yield $i + 1;
        });

        $pipeline->filter(function ($i) {
            return true;
        });

        $result = $pipeline->reduce(function ($a, $b) {
            return $a + $b + 1;
        });

        $this->assertNull($result);

        foreach (new Noop() as $value) {
            $this->fail();
        }
    }
}
