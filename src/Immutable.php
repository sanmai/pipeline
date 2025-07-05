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
 * @template TKey
 * @template TValue
 * @implements IteratorAggregate<TKey, TValue>
 */
class Immutable implements IteratorAggregate, Countable
{
    /**
     * @param array<TKey, TValue> $pipeline
     */
    public function __construct(private readonly array $pipeline) {}

    /**
     * @psalm-suppress TypeDoesNotContainType
     * @phpstan-assert-if-false non-empty-array $this->pipeline
     */
    private function empty(): bool
    {
        return [] === $this->pipeline;
    }

    /**
     * Appends the contents of an interable to the end of the pipeline.
     *
     * @return self<TKey, TValue>|Standard<TKey, TValue>
     */
    public function append(?iterable $values = null): self
    {
        if (null === $values) {
            // No-op: an empty array or null.
            return $this;
        }

        if (!is_array($values)) {
            $pipeline = new Standard($this->pipeline);
            return $pipeline->append($values);
        }

        return $this->join($this->pipeline, $values);
    }

    /**
     * Appends a list of values to the end of the pipeline.
     *
     * @param mixed ...$vector
     * @return self<TKey, TValue>
     */
    public function push(...$vector): self
    {
        return $this->append($vector);
    }

    /**
     * Prepends the pipeline with the contents of an array.
     *
     * @return self<TKey, TValue>
     */
    public function prepend(array $values): self
    {
        return $this->join($values, $this->pipeline);
    }

    /**
     * Prepends the pipeline with a list of values.
     *
     * @param mixed ...$vector
     * @return self<TKey, TValue>
     */
    public function unshift(...$vector): self
    {
        return $this->prepend($vector);
    }

    /**
     * @return self<TKey, TValue>
     */
    private function join(array $left, array $right): self
    {
        return new self(array_merge($left, $right));
    }

    /**
     * Chunks the pipeline into arrays with length elements. The last chunk may contain less than length elements.
     *
     * @param int<1, max> $length        the size of each chunk
     * @param bool        $preserve_keys When set to true keys will be preserved. Default is false which will reindex the chunk numerically.
     *
     * @return self<int, list<TValue>>
     */
    public function chunk(int $length, bool $preserve_keys = false): self
    {
        // No-op: an empty array or null.
        if ($this->empty()) {
            return $this;
        }

        // Array shortcut
        return new self(array_chunk($this->pipeline, $length, $preserve_keys));

    }


    /**
     * Takes a callback that for each input value expected to return another single value. Unlike map(), it assumes no special treatment for generators.
     *
     * With no callback is a no-op (can safely take a null).
     *
     * @template TCastValue
     * @param ?callable(TValue, TKey): TCastValue $func a callback must return a value
     *
     * @psalm-suppress RedundantCondition
     *
     * @return self<TKey, TCastValue>
     */
    public function cast(?callable $func = null): self
    {
        if (null === $func) {
            return $this;
        }

        if ($this->empty()) {
            return $this;
        }

        return new self(array_map($func, $this->pipeline));
    }


    /**
     * Removes elements unless a callback returns true.
     *
     * With no callback drops all null and false values (not unlike array_filter does by default).
     *
     * @param ?callable(TValue, TKey):bool $func
     * @param bool      $strict When true, only `null` and `false` are filtered out
     *
     * @return self<TKey, TValue>
     */
    public function filter(?callable $func = null, bool $strict = true): self
    {
        // No-op: an empty array or null.
        if ($this->empty()) {
            return $this;
        }

        $func = self::resolvePredicate($func, $strict);

        // We got an array, that's what we need. Moving along.
        return new self(array_filter($this->pipeline, $func));
    }

    /**
     * Resolves a nullable predicate into a sensible non-null callable.
     */
    private static function resolvePredicate(?callable $func, bool $strict): callable
    {
        if (null === $func && $strict) {
            return self::strictPredicate(...);
        }

        if (null === $func) {
            return self::nonStrictPredicate(...);
        }

        // Handle strict mode for user provided predicates.
        if ($strict) {
            return static function ($value) use ($func) {
                return self::strictPredicate($func($value));
            };
        }

        return self::resolveStringPredicate($func);
    }

    private static function strictPredicate(mixed $value): bool
    {
        return null !== $value && false !== $value;
    }

    private static function nonStrictPredicate(mixed $value): bool
    {
        return (bool) $value;
    }

    /**
     * Resolves a string/callable predicate into a sensible non-null callable.
     */
    private static function resolveStringPredicate(callable $func): callable
    {
        if (!is_string($func)) {
            return $func;
        }

        // Strings usually are internal functions, which typically require exactly one parameter.
        return static fn($value) => $func($value);
    }



    /**
     * Reduces input values to a single value. Defaults to summation. This is a terminal operation.
     *
     * @template T
     *
     * @param ?callable $func    function (mixed $carry, mixed $item) { must return updated $carry }
     * @param T         $initial The initial initial value for a $carry
     *
     * @return int|T
     */
    public function reduce(?callable $func = null, $initial = null)
    {
        return $this->fold($initial ?? 0, $func);
    }

    /**
     * Reduces input values to a single value. Defaults to summation. Requires an initial value. This is a terminal operation.
     *
     * @template T
     *
     * @param T         $initial initial value for a $carry
     * @param ?callable $func    function (mixed $carry, mixed $item) { must return updated $carry }
     *
     * @return T
     */
    public function fold($initial, ?callable $func = null)
    {
        if ($this->empty()) {
            return $initial;
        }

        return new self(array_reduce($this->pipeline, $func ?? self::defaultReducer(...), $initial));
    }

    /**
     * @param mixed $carry
     * @param mixed $item
     * @return mixed
     */
    private static function defaultReducer($carry, $item)
    {
        $carry += $item;

        return $carry;
    }

    #[Override]
    public function getIterator(): Traversable
    {
        /** @var ArrayIterator<TKey, TValue> */
        return new ArrayIterator($this->pipeline);
    }

    /**
     * By default, returns all values regardless of keys used, discarding all keys in the process. This is a terminal operation.
     * @return list<mixed>
     */
    public function toList(): array
    {
        return array_values($this->pipeline);
    }

    public function toArray(bool $preserve_keys): array
    {
        if ($preserve_keys) {
            return $this->toAssoc();
        }

        return $this->toList();
    }

    /**
     * Returns all values preserving keys. This is a terminal operation.
     * @return array<(int&TKey)|(string&TKey), TValue>
     */
    public function toAssoc(): array
    {
        // No-op: an empty array or null.
        if ($this->empty()) {
            return [];
        }

        return $this->pipeline;
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
        return count($this->pipeline);
    }

    /**
     * @return Standard<TKey, TValue>
     */
    public function stream()
    {
        return new Standard($this->pipeline);
    }

    /**
     * Extracts a slice from the inputs. Keys are not discarded intentionally.
     *
     * @see \array_slice()
     *
     * @param int  $offset If offset is non-negative, the sequence will start at that offset. If offset is negative, the sequence will start that far from the end.
     * @param ?int $length If length is given and is positive, then the sequence will have up to that many elements in it. If length is given and is negative then the sequence will stop that many elements from the end.
     *
     * @return self<TKey, TValue>
     */
    public function slice(int $offset, ?int $length = null): self
    {
        if ($this->empty()) {
            // With non-primed pipeline just move along.
            return $this;
        }

        if (0 === $length) {
            return new self([]);
        }

        return new self(array_slice($this->pipeline, $offset, $length, preserve_keys: true));
    }


    /**
     * Find lowest value using the standard comparison rules. Returns null for empty sequences.
     *
     * @return null|mixed
     */
    public function min()
    {
        if ($this->empty()) {
            return null;
        }

        return min($this->pipeline);
    }

    /**
     * Find highest value using the standard comparison rules. Returns null for empty sequences.
     *
     * @return null|mixed
     */
    public function max()
    {
        if ($this->empty()) {
            return null;
        }

        return max($this->pipeline);
    }

    /**
     * @return self<int, TValue>
     */
    public function values(): self
    {
        if ($this->empty()) {
            // No-op: null.
            return $this;
        }

        return new self(array_values($this->pipeline));
    }

    /**
     * @return self<int, TKey>
     */
    public function keys(): self
    {
        if ($this->empty()) {
            // No-op: null.
            return $this;
        }

        return new self(array_keys($this->pipeline));
    }


    /**
     * @return self<TValue, TKey>
     */
    public function flip(): self
    {
        if ($this->empty()) {
            // No-op: null.
            return $this;
        }

        return new self(array_flip($this->pipeline));
    }

    /**
     * @return self<int, array{TKey, TValue}>
     */
    public function tuples(): self
    {
        if ($this->empty()) {
            // No-op: null.
            return $this;
        }

        return new self(array_map(
            fn($key, $value) => [$key, $value],
            array_keys($this->pipeline),
            $this->pipeline
        ));
    }
}
