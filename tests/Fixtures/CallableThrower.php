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

namespace Tests\Pipeline\Fixtures;

use ArgumentCountError;

use function count;

class CallableThrower
{
    public array $args = [];
    public int $callCount = 0;

    public function __invoke(...$args): void
    {
        $this->args[] = $args;
        $this->callCount++;

        if (count($args) > 1) {
            throw new ArgumentCountError();
        }
    }
}
