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

namespace Pipeline;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Pipeline\Simple
 * @covers \Pipeline\Principal
 */
class LazinessTest extends TestCase
{
    private function yieldFail()
    {
        $this->fail();
    }

    public function testMapLazy()
    {
        $pipeline = new Simple();
        $pipeline->map(function () {
            yield true;
            yield $this->yieldFail();
        });

        $this->assertInstanceOf(\Traversable::class, $pipeline);

        foreach ($pipeline as $value) {
            $this->assertTrue($value);
            break;
        }
    }

    public function testFilterLazy()
    {
        $pipeline = new Simple(new \ArrayIterator([true]));
        $pipeline->filter(function () {
            $this->fail();
        });

        $iterator = $pipeline->getIterator();
        $this->assertInstanceOf(\Traversable::class, $iterator);
    }

    public function testUnpackLazy()
    {
        $pipeline = new Simple();
        $pipeline->unpack(function () {
            yield true;
            yield $this->yieldFail();
        });

        $this->assertInstanceOf(\Traversable::class, $pipeline);

        foreach ($pipeline as $value) {
            $this->assertTrue($value);
            break;
        }
    }
}
