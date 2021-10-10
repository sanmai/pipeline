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
 * Interface definitions for the standard pipeline.
 */
interface StandardPipeline extends \IteratorAggregate, \Countable
{
    /**
     * Takes a callback that for each input value may return one or yield many. Also takes an initial generator, where it must not require any arguments.
     *
     * With no callback is a no-op (can safely take a null).
     *
     * @param ?callable $func a callback must either return a value or yield values (return a generator)
     *
     * @return $this
     */
    public function map(?callable $func = null);

    /**
     * An extra variant of `map` which unpacks arrays into arguments. Flattens inputs if no callback provided.
     *
     * @param ?callable $func
     *
     * @return $this
     */
    public function unpack(?callable $func = null);

    /**
     * Takes a callback that for each input value expected to return another single value. Unlike map(), it assumes no special treatment for generators.
     *
     * With no callback is a no-op (can safely take a null).
     *
     * @param ?callable $func a callback must return a value
     *
     * @return $this
     */
    public function cast(?callable $func = null);

    /**
     * Removes elements unless a callback returns true.
     *
     * With no callback drops all null and false values (not unlike array_filter does by default).
     *
     * @param ?callable $func {@inheritdoc}
     *
     * @return $this
     */
    public function filter(?callable $func = null);

    /**
     * Extracts a slice from the inputs. Keys are not discarded intentionally.
     *
     * @see \array_slice()
     *
     * @param int  $offset If offset is non-negative, the sequence will start at that offset. If offset is negative, the sequence will start that far from the end.
     * @param ?int $length If length is given and is positive, then the sequence will have up to that many elements in it. If length is given and is negative then the sequence will stop that many elements from the end.
     *
     * @return $this
     */
    public function slice(int $offset, ?int $length = null);

    /**
     * Remove a portion of the inputs and replace it with something else.
     *
     * @see \array_splice()
     *
     * @param ?int      $length
     * @param ?iterable $replacement
     *
     * @return $this
     */
    public function splice(int $offset, ?int $length = null, ?iterable $replacement = null);

    /**
     * Reduces input values to a single value. Defaults to summation. This is a terminal operation.
     *
     * @param ?callable $func    function (mixed $carry, mixed $item) { must return updated $carry }
     * @param ?mixed    $initial initial value for a $carry
     *
     * @return ?mixed
     */
    public function reduce(?callable $func = null, $initial = null);

    /**
     * Reduces input values to a single value. Defaults to summation. Requires an initial value. This is a terminal operation.
     *
     * @param mixed     $initial initial value for a $carry
     * @param ?callable $func    function (mixed $carry, mixed $item) { must return updated $carry }
     *
     * @return ?mixed
     */
    public function fold($initial, ?callable $func = null);

    /**
     * Performs a lazy zip operation on iterables, not unlike that of
     * array_map with first argument set to null. Also known as transposition.
     *
     * @return $this
     */
    public function zip(iterable ...$inputs);

    /**
     * By default returns all values regardless of keys used, discarding all keys in the process. Has an option to keep the keys. This is a terminal operation.
     */
    public function toArray(bool $useKeys = false): array;
}
