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

use Countable;
use Iterator;
use Override;

/**
 * @template TKey
 * @template TValue
 *
 * @internal
 */
class WindowBuffer implements Countable
{
    private int $head = 0;

    /** @var array<int, TKey> */
    private array $keys = [];

    /** @var array<int, TValue> */
    private array $values = [];

    /** @return TKey */
    public function keyAt(int $position): mixed
    {
        return $this->keys[$this->head + $position];
    }

    /** @return TValue */
    public function valueAt(int $position): mixed
    {
        return $this->values[$this->head + $position];
    }

    /** @param Iterator<TKey, TValue> $iterator */
    public function append(Iterator $iterator): void
    {
        $this->keys[] = $iterator->key();
        $this->values[] = $iterator->current();
    }

    #[Override]
    public function count(): int
    {
        return count($this->keys);
    }

    public function shift(): void
    {
        unset($this->keys[$this->head]);
        unset($this->values[$this->head]);

        $this->head++;
    }
}
