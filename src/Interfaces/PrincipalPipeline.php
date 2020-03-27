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

namespace Pipeline\Interfaces;

/**
 * Interface definitions for the most basic principal pipeline.
 */
interface PrincipalPipeline extends \IteratorAggregate
{
    /**
     * Takes a callback that for each input value may return one or yield many. Also takes an initial generator, where it must not require any arguments.
     *
     * @param callable $func a callback must either return a value or yield values (return a generator)
     *
     * @return $this
     */
    public function map(callable $func);

    /**
     * Removes elements unless a callback returns true.
     *
     * @param callable $func a callback returning true or false
     *
     * @return $this
     */
    public function filter(callable $func);

    /**
     * Reduces input values to a single value.
     *
     * @param mixed    $initial initial value for a $carry
     * @param callable $func    function (mixed $carry, mixed $item) { must return updated $carry }
     *
     * @return mixed
     */
    public function fold($initial, callable $func);

    /**
     * Performs a lazy zip operation on iterables, not unlike that of
     * array_map with first argument set to null. Also known as transposition.
     *
     * @return $this
     */
    public function zip(iterable ...$inputs);

    /**
     * Returns all values regardless of keys used, discarding all keys in the process.
     */
    public function toArray(): array;
}
