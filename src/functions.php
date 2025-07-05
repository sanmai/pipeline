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

namespace Pipeline;

use function is_array;

/**
 * @template TMapKey
 * @template TMapValue
 * @param null|callable(TMapValue, TMapKey): TMapValue $func
 * @return Standard<TMapKey, TMapValue>
 */
function map(?callable $func = null): Standard
{
    $pipeline = new Standard();

    if (null === $func) {
        return $pipeline;
    }

    return $pipeline->map($func);
}

/**
 * @template TTakeKey
 * @template TTakeValue
 * @param null|iterable<TTakeKey, TTakeValue> $input
 * @param iterable<TTakeKey, TTakeValue> ...$inputs
 * @return Immutable<TTakeKey, TTakeValue>|Standard<TTakeKey, TTakeValue>
 */
function take(?iterable $input = null, iterable ...$inputs): Standard|Immutable
{
    $pipeline = is_array($input) ? new Immutable($input) : new Standard($input);

    foreach ($inputs as $input) {
        $pipeline = $pipeline->append($input);
    }

    return $pipeline;
}

/**
 * @template TArrayKey of array-key
 * @template TArrayValue
 * @param array<TArrayKey, TArrayValue> $input
 * @return Immutable<TArrayKey, TArrayValue>
 */
function fromArray(array $input): Immutable
{
    return new Immutable($input);
}

/**
 * @param mixed ...$values
 * @return Standard<int|string, mixed>
 */
function fromValues(...$values): Immutable
{
    return new Immutable($values);
}

/**
 * @template TZipKey
 * @template TZipValue
 * @param iterable<TZipKey, TZipValue> $base
 * @param iterable<TZipKey, TZipValue> ...$inputs
 * @return Standard<TZipKey, TZipValue>
 */
function zip(iterable $base, iterable ...$inputs): Standard
{
    $result = take($base);
    $result->zip(...$inputs);

    return $result;
}
