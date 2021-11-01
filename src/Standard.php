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

/**
 * Concrete pipeline with sensible default callbacks.
 *
 * @final
 */
class Standard extends Principal
{
    /**
     * An extra variant of `map` which unpacks arrays into arguments. Flattens inputs if no callback provided.
     *
     * @param ?callable $func
     * @psalm-suppress InvalidArgument
     *
     * @return $this
     */
    public function unpack(?callable $func = null): self
    {
        $func = $func ?? static function (...$args) {
            yield from $args;
        };

        return $this->map(static function (iterable $args = []) use ($func) {
            return $func(...$args);
        });
    }

    /**
     * Takes a callback that for each input value may return one or yield many. Also takes an initial generator, where it must not require any arguments.
     *
     * With no callback is a no-op (can safely take a null).
     *
     * @param ?callable $func a callback must either return a value or yield values (return a generator)
     *
     * @return $this
     */
    public function map(?callable $func = null): self
    {
        if (null === $func) {
            return $this;
        }

        return parent::map($func);
    }

    /**
     * Takes a callback that for each input value expected to return another single value. Unlike map(), it assumes no special treatment for generators.
     *
     * With no callback is a no-op (can safely take a null).
     *
     * @param ?callable $func a callback must return a value
     *
     * @return $this
     */
    public function cast(callable $func = null): self
    {
        if (null === $func) {
            return $this;
        }

        return parent::cast($func);
    }

    /**
     * Removes elements unless a callback returns true.
     *
     * With no callback drops all null and false values (not unlike array_filter does by default).
     *
     * @param ?callable $func {@inheritdoc}
     *
     * @return $this
     */
    public function filter(?callable $func = null): self
    {
        $func = $func ?? static function ($value) {
            // Cast is unnecessary
            return $value;
        };

        // Strings usually are internal functions, which typically require exactly one parameter.
        if (\is_string($func)) {
            $func = static function ($value) use ($func) {
                return $func($value);
            };
        }

        return parent::filter($func);
    }

    /**
     * Reduces input values to a single value. Defaults to summation. This is a terminal operation.
     *
     * @param ?callable $func    function (mixed $carry, mixed $item) { must return updated $carry }
     * @param ?mixed    $initial initial value for a $carry
     *
     * @return ?mixed
     */
    public function reduce(?callable $func = null, $initial = null)
    {
        return $this->fold($initial ?? 0, $func);
    }

    /**
     * Reduces input values to a single value. Defaults to summation. Requires an initial value. This is a terminal operation.
     *
     * @param mixed     $initial initial value for a $carry
     * @param ?callable $func    function (mixed $carry, mixed $item) { must return updated $carry }
     *
     * @return ?mixed
     */
    public function fold($initial, ?callable $func = null)
    {
        if (null !== $func) {
            return parent::fold($initial, $func);
        }

        return parent::fold(
            $initial,
            static function ($carry, $item) {
                $carry += $item;

                return $carry;
            }
        );
    }
}
