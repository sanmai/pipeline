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

namespace Pipeline\Contracts;

use ArrayIterator;
use CallbackFilterIterator;
use Countable;
use EmptyIterator;
use Generator;
use Iterator;
use IteratorAggregate;
use Pipeline\Helper;
use Traversable;
use Override;

use function array_chunk;
use function array_filter;
use function array_flip;
use function array_map;
use function array_merge;
use function array_reduce;
use function array_shift;
use function array_slice;
use function array_values;
use function count;
use function is_array;
use function is_string;
use function iterator_count;
use function iterator_to_array;
use function max;
use function min;
use function mt_getrandmax;
use function mt_rand;
use function array_keys;

/**
 * @template TOutput
 * @template-implements IteratorAggregate<array-key, TOutput>
 */
interface Output extends IteratorAggregate
{
    /**
     * Returns all values preserving keys. This is a terminal operation.
     * @return array<array-key, TOutput>
     */
    public function toAssoc(): array;

    /**
     * @return list<TOutput>
     */
    public function toList(): array;
}
