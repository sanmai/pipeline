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
use NoRewindIterator;
use Override;

/**
 * A forward-only iterator that auto-advances when iteration resumes.
 *
 * Unlike NoRewindIterator which repeats the current element on resume,
 * CursorIterator advances past it - matching database cursor semantics.
 *
 * @template TKey
 * @template TValue
 * @template TIterator of Iterator<TKey, TValue>
 *
 * @extends NoRewindIterator<TKey, TValue, TIterator>
 *
 * @final
 */
class CursorIterator extends NoRewindIterator
{
    private bool $started = false;

    /**
     * @param TIterator $iterator
     */
    public function __construct(Iterator $iterator)
    {
        parent::__construct($iterator);
        $iterator->rewind();
    }

    #[Override]
    public function rewind(): void
    {
        if (!$this->started) {
            $this->started = true;

            return;
        }

        $this->next();
    }
}
