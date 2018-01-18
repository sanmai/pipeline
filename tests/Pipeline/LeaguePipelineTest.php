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

class LeaguePipelineTest extends TestCase
{
    public function testWithLeaguePipeline()
    {
        $leaguePipeline = (new \League\Pipeline\Pipeline())->pipe(function ($payload) {
            return $payload + 1;
        })->pipe(function ($payload) {
            return $payload * 2;
        });

        $this->assertEquals(22, $leaguePipeline->process(10));

        $pipeline = new \Pipeline\Simple(new \ArrayIterator([10, 20, 30]));
        $pipeline->map($leaguePipeline);

        $this->assertEquals([22, 42, 62], iterator_to_array($pipeline));
    }
}
