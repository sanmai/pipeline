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

use Countable;
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
 * @internal
 *
 * @final
 */
class WindowIterator implements Iterator, Countable
{
    private bool $innerExhausted = false;

    /** @var Iterator<TKey, TValue> */
    private readonly Iterator $inner;

    /** @var SplDoublyLinkedList<array{TKey, TValue}> */
    private readonly SplDoublyLinkedList $buffer;

    /**
     * @param Iterator<TKey, TValue> $innerUnsafe
     * @param int<1, max> $maxSize Maximum buffer size
     * @param SplDoublyLinkedList<array{TKey, TValue}>|null $buffer
     * @param Iterator<TKey, TValue>|null $safeInner Injectable for testing
     */
    public function __construct(
        Iterator $innerUnsafe,
        private readonly int $maxSize,
        ?SplDoublyLinkedList $buffer = null,
        ?Iterator $inner = null
    ) {
        $this->buffer = $buffer ?? new SplDoublyLinkedList();
        $this->inner = $inner ?? new SafeStartIterator($innerUnsafe);
    }

    #[Override]
    public function current(): mixed
    {
        if (!$this->valid()) {
            return null;
        }

        if ($this->buffer->valid()) {
            return $this->buffer->current()[1];
        }

        return $this->buffer->top()[1];
    }

    #[Override]
    public function key(): mixed
    {
        if (!$this->valid()) {
            return null;
        }

        if ($this->buffer->valid()) {
            return $this->buffer->current()[0];
        }

        return $this->buffer->top()[0];
    }

    #[Override]
    public function next(): void
    {
        $this->buffer->next();

        if ($this->buffer->valid() || $this->innerExhausted) {
            return;
        }

        $this->inner->next();
        $this->fetch();

        while ($this->buffer->count() > $this->maxSize) {
            $this->buffer->shift();
        }
    }

    #[Override]
    public function rewind(): void
    {
        $this->buffer->rewind();
    }

    #[Override]
    public function valid(): bool
    {
        if (0 === $this->buffer->count() && !$this->innerExhausted) {
            $this->fetch();
        }

        // After push, buffer iterator is invalid but we have data at top()
        return $this->buffer->valid() || !$this->innerExhausted;
    }

    private function fetch(): void
    {
        if (!$this->inner->valid()) {
            $this->innerExhausted = true;

            return;
        }

        $this->buffer->push([$this->inner->key(), $this->inner->current()]);
    }

    #[Override]
    public function count(): int
    {
        return $this->buffer->count();
    }
}
