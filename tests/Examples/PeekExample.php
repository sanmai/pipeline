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

namespace Tests\Pipeline\Examples;

class PeekExample
{
    public function __construct(
        public readonly int $count = 0,
        public readonly bool $consume = false,
        public readonly bool $preserve_keys = false,
        public readonly iterable $input = [],
        public readonly array $expected_peeked = [],
    ) {}

    public function withInput(iterable $input): static
    {
        return new static($this->count, $this->consume, $this->preserve_keys, $input, $this->expected_peeked);
    }
}
