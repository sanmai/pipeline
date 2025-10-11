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

namespace Tests\Pipeline\Scenarios;

class PeekScenario
{
    public function __construct(
        public readonly int $count = 0,
        public readonly iterable $input = [],
        public readonly iterable $expected_peeked = [],
        public readonly ?iterable $expected_remains = null,
    ) {}

    public function withInput(iterable $input): self
    {
        return new self($this->count, $input, $this->expected_peeked);
    }
}
