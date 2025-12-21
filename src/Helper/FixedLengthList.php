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

use ArrayAccess;
use Countable;
use Override;
use SplDoublyLinkedList;

/**
 * A fixed-length list that auto-trims oldest elements.
 *
 * @template T
 *
 * @implements ArrayAccess<int, T>
 *
 * @final
 */
class FixedLengthList implements Countable, ArrayAccess
{
    /** @var SplDoublyLinkedList<T> */
    private SplDoublyLinkedList $list;

    /**
     * @param int<1, max>|null $maxSize Null for unlimited
     */
    public function __construct(
        private readonly ?int $maxSize = null
    ) {
        $this->list = new SplDoublyLinkedList();
    }

    /**
     * @param T $value
     * @return bool True if an element was shifted to maintain size
     */
    public function push(mixed $value): bool
    {
        $this->list->push($value);

        if (null === $this->maxSize || $this->list->count() <= $this->maxSize) {
            return false;
        }

        $this->list->shift();

        return true;
    }

    #[Override]
    public function count(): int
    {
        return $this->list->count();
    }

    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        return $this->list->offsetExists($offset);
    }

    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->list->offsetGet($offset);
    }

    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->list->offsetSet($offset, $value);
    }

    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        $this->list->offsetUnset($offset);
    }
}
