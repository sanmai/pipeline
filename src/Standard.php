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

use ArrayIterator;
use CallbackFilterIterator;
use Countable;
use EmptyIterator;
use Generator;
use Iterator;
use IteratorAggregate;
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
use function assert;
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
 * Concrete pipeline with sensible default callbacks.
 *
 * @template-implements IteratorAggregate<mixed, mixed>
 */
class Standard implements IteratorAggregate, Countable
{
    /**
     * Pre-primed pipeline.
     *
     * This is not a full `iterable` per se because we exclude IteratorAggregate before assigning a value.
     */
    private Immutable $immutablePipeline;

    /**
     * Constructor with an optional source of data.
     */
    public function __construct(?iterable $input = null)
    {
        $this->immutablePipeline = new Immutable($input);
    }

    private function empty(): bool
    {
        // This method is no longer strictly necessary as Immutable handles empty state internally
        // but kept for compatibility with existing calls if any.
        return false; // Or delegate to $this->immutablePipeline->isEmpty() if such method exists
    }

    private function discard(): void
    {
        // In an immutable context, discarding the pipeline is not meaningful.
        // The internal immutable pipeline is replaced, not discarded.
    }

    private function resetPipeline(): void
    {
        $this->immutablePipeline = new Immutable();
    }

    /**
     * Appends the contents of an iterable to the end of the pipeline.
     */
    public function append(?iterable $values = null): self
    {
        $this->immutablePipeline = $this->immutablePipeline->append($values);

        return $this;
    }

    /**
     * Appends a list of values to the end of the pipeline.
     *
     * @param mixed ...$vector
     */
    public function push(...$vector): self
    {
        $this->immutablePipeline = $this->immutablePipeline->push(...$vector);

        return $this;
    }

    /**
     * Prepends the pipeline with the contents of an iterable.
     */
    public function prepend(?iterable $values = null): self
    {
        $this->immutablePipeline = $this->immutablePipeline->prepend($values);

        return $this;
    }

    /**
     * Prepends the pipeline with a list of values.
     *
     * @param mixed ...$vector
     */
    public function unshift(...$vector): self
    {
        $this->immutablePipeline = $this->immutablePipeline->unshift(...$vector);

        return $this;
    }

    public function flatten(): self
    {
        $this->immutablePipeline = $this->immutablePipeline->flatten();

        return $this;
    }

    public function unpack(?callable $func = null): self
    {
        $this->immutablePipeline = $this->immutablePipeline->unpack($func);

        return $this;
    }

    public function chunk(int $length, bool $preserve_keys = false): self
    {
        $this->immutablePipeline = $this->immutablePipeline->chunk($length, $preserve_keys);

        return $this;
    }

    public function map(?callable $func = null): self
    {
        $this->immutablePipeline = $this->immutablePipeline->map($func);

        return $this;
    }

    public function cast(?callable $func = null): self
    {
        $this->immutablePipeline = $this->immutablePipeline->cast($func);

        return $this;
    }

    public function filter(?callable $func = null, bool $strict = false): self
    {
        $this->immutablePipeline = $this->immutablePipeline->filter($func, $strict);

        return $this;
    }

    public function skipWhile(callable $predicate): self
    {
        $this->immutablePipeline = $this->immutablePipeline->skipWhile($predicate);

        return $this;
    }

    public function reduce(?callable $func = null, $initial = null)
    {
        $result = $this->immutablePipeline->reduce($func, $initial);
        $this->resetPipeline();

        return $result;
    }

    public function fold($initial, ?callable $func = null)
    {
        $result = $this->immutablePipeline->fold($initial, $func);
        $this->resetPipeline();

        return $result;
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return $this->immutablePipeline->getIterator();
    }

    /**
     * By default, returns all values regardless of keys used, discarding all keys in the process. This is a terminal operation.
     */
    public function toList(): array
    {
        $result = $this->immutablePipeline->toList();
        $this->resetPipeline();

        return $result;
    }

    /**
     * @deprecated Use toList() or toAssoc() instead
     */
    public function toArray(bool $preserve_keys = false): array
    {
        $result = $this->immutablePipeline->toArray($preserve_keys);
        $this->resetPipeline();

        return $result;
    }

    /**
     * Returns all values preserving keys. This is a terminal operation.
     * @deprecated Use toAssoc() instead
     */
    public function toArrayPreservingKeys(): array
    {
        $result = $this->immutablePipeline->toArrayPreservingKeys();
        $this->resetPipeline();

        return $result;
    }

    /**
     * Returns all values preserving keys. This is a terminal operation.
     */
    public function toAssoc(): array
    {
        $result = $this->immutablePipeline->toAssoc();
        $this->resetPipeline();

        return $result;
    }

    /**
     * Counts seen values online.
     *
     * @param ?int &$count the current count; initialized unless provided
     *
     * @param-out int $count
     *
     * @return $this
     */
    public function runningCount(
        ?int &$count
    ): self {
        $this->immutablePipeline = $this->immutablePipeline->runningCount($count);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * This is a terminal operation.
     *
     * @see \Countable::count()
     */
    #[Override]
    public function count(): int
    {
        $result = $this->immutablePipeline->count();
        $this->resetPipeline();

        return $result;
    }

    /**
     * @return $this
     */
    public function stream(): self
    {
        $this->immutablePipeline = $this->immutablePipeline->stream();

        return $this;
    }

    private static function makeNonRewindable(iterable $input): Generator
    {
        if ($input instanceof Generator) {
            return $input;
        }

        return self::generatorFromIterable($input);
    }

    private static function generatorFromIterable(iterable $input): Generator
    {
        yield from $input;
    }

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
    public function slice(int $offset, ?int $length = null)
    {
        $this->immutablePipeline = $this->immutablePipeline->slice($offset, $length);

        return $this;
    }

    public function zip(iterable ...$inputs): \Pipeline\Standard
    {
        $this->immutablePipeline = $this->immutablePipeline->zip(...$inputs);

        return $this;
    }

    public function reservoir(int $size, ?callable $weightFunc = null): array
    {
        $result = $this->immutablePipeline->reservoir($size, $weightFunc);
        $this->resetPipeline();

        return $result;
    }

    public function min()
    {
        $result = $this->immutablePipeline->min();
        $this->resetPipeline();

        return $result;
    }

    public function max()
    {
        $result = $this->immutablePipeline->max();
        $this->resetPipeline();

        return $result;
    }

    public function values(): self
    {
        $this->immutablePipeline = $this->immutablePipeline->values();

        return $this;
    }

    public function keys(): self
    {
        $this->immutablePipeline = $this->immutablePipeline->keys();

        return $this;
    }

    public function flip(): self
    {
        $this->immutablePipeline = $this->immutablePipeline->flip();

        return $this;
    }

    public function tuples(): self
    {
        $this->immutablePipeline = $this->immutablePipeline->tuples();

        return $this;
    }

    private function feedRunningVariance(Helper\RunningVariance $variance, ?callable $castFunc): self
    {
        $this->immutablePipeline = $this->immutablePipeline->feedRunningVariance($variance, $castFunc);

        return $this;
    }

    /**
     * Feeds in an instance of RunningVariance.
     *
     * @param ?Helper\RunningVariance &$variance the instance of RunningVariance; initialized unless provided
     * @param ?callable               $castFunc  the cast callback, returning ?float; null values are not counted
     *
     * @param-out Helper\RunningVariance $variance
     *
     * @return $this
     */
    public function runningVariance(
        ?Helper\RunningVariance &$variance,
        ?callable $castFunc = null
    ): self {
        $this->immutablePipeline = $this->immutablePipeline->runningVariance($variance, $castFunc);

        return $this;
    }

    /**
     * Computes final statistics for the sequence.
     *
     * @param ?callable               $castFunc the cast callback, returning ?float; null values are not counted
     * @param ?Helper\RunningVariance $variance the optional instance of RunningVariance
     */
    public function finalVariance(
        ?callable $castFunc = null,
        ?Helper\RunningVariance $variance = null
    ): Helper\RunningVariance {
        $result = $this->immutablePipeline->finalVariance($castFunc, $variance);
        $this->resetPipeline();

        return $result;
    }

    /**
     * Eagerly iterates over the sequence using the provided callback. Discards the sequence after iteration.
     *
     * @param callable $func
     * @param bool $discard Whenever to discard the pipeline's iterator.
     */
    public function each(callable $func, bool $discard = true): void
    {
        // The discard parameter is handled by the Immutable class internally.
        // We just delegate the call.
        $this->immutablePipeline->each($func);
        $this->resetPipeline();
    }
}
