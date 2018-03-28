<?php
/*
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

/**
 * Concrete pipeline with sensible default callbacks.
 */
class Standard extends Principal implements Interfaces\StandardPipeline
{
    /**
     * An extra variant of `map` which unpacks arrays into arguments.
     *
     * @param ?callable $func
     *
     * @return $this
     */
    public function unpack(callable $func = null)
    {
        $func = $func ?? static function (...$args) {
            yield from $args;
        };

        return $this->map(static function (/* iterable */ $args = []) use ($func) {
            return $func(...$args);
        });
    }

    /**
     * With no callback drops all null and false values (not unlike array_filter defaults).
     *
     * @return $this
     */
    public function filter(callable $func = null)
    {
        $func = $func ?? static function ($value) {
            return (bool) $value;
        };

        // Strings usually are internal functions, which require exact number of parameters.
        if (is_string($func)) {
            $func = static function ($value) use ($func) {
                return $func($value);
            };
        }

        return parent::filter($func);
    }

    /**
     * Defaults to summation.
     *
     * @param null|mixed $initial
     */
    public function reduce(callable $func = null, $initial = null)
    {
        if (!$func) {
            return parent::reduce(static function ($carry, $item) {
                $carry += $item;

                return $carry;
            }, 0);
        }

        return parent::reduce($func, $initial);
    }
}
