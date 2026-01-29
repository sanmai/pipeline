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
 * A rewindable iterator that caches elements for replay.
 *
 * Unlike CursorIterator which is forward-only, WindowIterator buffers
 * seen elements allowing rewind within the buffer bounds.
 *
 * @template TKey
 * @template TValue
 *
 * @implements Iterator<TKey, TValue>
 *
 * @final
 */
class WindowIterator implements Iterator
{
    private int $position = 0;

    private bool $innerExhausted = false;

    private bool $initialized = false;

    /**
     * @param Iterator<TKey, TValue> $inner
     * @param int<1, max> $maxSize Maximum buffer size
     * @param WindowBuffer<TKey, TValue> $buffer
     */
    public function __construct(
        private readonly Iterator $inner,
        private readonly int $maxSize,
        private readonly WindowBuffer $buffer = new WindowBuffer()
    ) {}

    #[Override]
    public function current(): mixed
    {
        if (!$this->valid()) {
            return null;
        }

        return $this->buffer->valueAt($this->position);
    }

    #[Override]
    public function key(): mixed
    {
        if (!$this->valid()) {
            return null;
        }

        return $this->buffer->keyAt($this->position);
    }

    #[Override]
    public function next(): void
    {
        ++$this->position;

        // If still within buffer or inner exhausted, nothing to fetch
        if ($this->position < $this->buffer->count() || $this->innerExhausted) {
            return;
        }

        $this->inner->next();

        $this->fetch();

        while ($this->buffer->count() > $this->maxSize) {
            $this->buffer->shift();
            --$this->position;
        }
    }

    #[Override]
    public function rewind(): void
    {
        $this->position = 0;
    }

    #[Override]
    public function valid(): bool
    {
        $this->initialize();

        return $this->position < $this->buffer->count();
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;

        // If inner is already pointing at data (e.g. a started generator), capture it
        if ($this->inner->valid()) {
            $this->pushFromInner();

            return;
        }

        $this->inner->rewind();

        $this->fetch();
    }

    private function fetch(): void
    {
        if (!$this->inner->valid()) {
            $this->innerExhausted = true;

            return;
        }

        $this->pushFromInner();
    }

    private function pushFromInner(): void
    {
        $this->buffer->append($this->inner);
    }
}
