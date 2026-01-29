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

namespace Pipeline\Helper;

use function count;

/**
 * @template TKey
 * @template TValue
 *
 * @internal
 */
class WindowBuffer
{
    /** @var array<int, array{TKey, TValue}> */
    private array $buffer = [];

    private int $headKey = 0;

    public function keyAt(int $position): mixed
    {
        return $this->buffer[$this->headKey + $position][0];
    }

    public function valueAt(int $position): mixed
    {
        return $this->buffer[$this->headKey + $position][1];
    }

    public function append(mixed $key, mixed $value): void
    {
        $this->buffer[] = [$key, $value];
    }

    public function count(): int
    {
        return count($this->buffer);
    }

    public function dropOldest(): void
    {
        unset($this->buffer[$this->headKey]);
        ++$this->headKey;
    }
}
