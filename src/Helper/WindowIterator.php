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
use SplDoublyLinkedList;

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
    /** @var SplDoublyLinkedList<array{TKey, TValue}> */
    private SplDoublyLinkedList $buffer;

    private int $position = 0;

    private ?int $maxSize;

    private bool $innerExhausted = false;

    /** @var Iterator<TKey, TValue> */
    private Iterator $inner;

    /**
     * @param Iterator<TKey, TValue> $iterator
     * @param int|null $size Maximum buffer size (null = unlimited)
     */
    public function __construct(Iterator $iterator, ?int $size = null)
    {
        $this->inner = $iterator;
        $this->maxSize = $size;
        $this->buffer = new SplDoublyLinkedList();

        // Eager initialization
        $iterator->rewind();
        if (!$iterator->valid()) {
            $this->innerExhausted = true;

            return;
        }

        $this->buffer->push([$iterator->key(), $iterator->current()]);
    }

    #[Override]
    public function current(): mixed
    {
        if (!$this->valid()) {
            return null;
        }

        return $this->buffer[$this->position][1];
    }

    #[Override]
    public function key(): mixed
    {
        if (!$this->valid()) {
            return null;
        }

        return $this->buffer[$this->position][0];
    }

    #[Override]
    public function next(): void
    {
        ++$this->position;

        // If still within buffer or inner exhausted, nothing to fetch
        if ($this->position < $this->buffer->count() || $this->innerExhausted) {
            return;
        }

        // Fetch from inner
        $this->inner->next();
        if (!$this->inner->valid()) {
            $this->innerExhausted = true;

            return;
        }

        $this->buffer->push([$this->inner->key(), $this->inner->current()]);

        // Trim if over limit
        if (null === $this->maxSize) {
            return;
        }

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
        return $this->position >= 0 && $this->position < $this->buffer->count();
    }
}
