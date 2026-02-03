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

use Iterator;
use Override;

/**
 * Wraps an iterator that might already be started, ensuring we don't lose
 * the current element and don't double-rewind.
 *
 * @template TKey
 * @template TValue
 *
 * @implements Iterator<TKey, TValue>
 * @internal
 *
 * @final
 */
class SafeStartIterator implements Iterator
{
    private bool $started = false;

    /**
     * @param Iterator<TKey, TValue> $inner
     */
    public function __construct(private readonly Iterator $inner) {}

    #[Override]
    public function rewind(): void
    {
        if ($this->started) {
            return;
        }
        $this->started = true;

        if ($this->inner->valid()) {
            return;
        }

        $this->inner->rewind();
    }

    #[Override]
    public function valid(): bool
    {
        if (!$this->started) {
            $this->rewind();
        }

        return $this->inner->valid();
    }

    #[Override]
    public function current(): mixed
    {
        return $this->inner->current();
    }

    #[Override]
    public function key(): mixed
    {
        return $this->inner->key();
    }

    #[Override]
    public function next(): void
    {
        $this->inner->next();
    }
}
