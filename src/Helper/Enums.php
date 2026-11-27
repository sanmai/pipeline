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

use BackedEnum;
use UnitEnum;

/**
 * Maps enum cases to their scalar properties.
 *
 * PHP has no property reference syntax, so use these methods as first-class
 * callables: `$pipeline->cast(Enums::value(...))`.
 *
 * @final
 */
class Enums
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @template T of BackedEnum
     * @param T $case
     * @return int|string
     * @phpstan-return value-of<T>
     * @psalm-return int|string
     */
    public static function value(BackedEnum $case): int|string
    {
        return $case->value;
    }

    public static function name(UnitEnum $case): string
    {
        return $case->name;
    }
}
