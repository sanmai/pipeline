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

function map(?callable $func = null): Standard
{
    $pipeline = new Standard();

    if (null === $func) {
        return $pipeline;
    }

    return $pipeline->map($func);
}

function take(?iterable $input = null, iterable ...$inputs): Standard
{
    $pipeline = new Standard($input);

    foreach ($inputs as $input) {
        $pipeline->append($input);
    }

    return $pipeline;
}

function fromArray(array $input): Standard
{
    return new Standard($input);
}

/**
 * @param mixed ...$values
 */
function fromValues(...$values): Standard
{
    return new Standard($values);
}

function zip(iterable $base, iterable ...$inputs): Standard
{
    $result = take($base);
    $result->zip(...$inputs);

    return $result;
}
