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
class Standard implements IteratorAggregate, Countable
{
    /**
     * Pre-primed pipeline.
     *
     * @var array<TKey, TValue>|Iterator<TKey, TValue>
     */
    private array|Iterator $pipeline;

    /**
     * Constructor with an optional source of data.
     *
     * @param null|iterable<TKey, TValue> $input
     */
    public function __construct(?iterable $input = null)
    {
        $this->immutablePipeline = new Immutable($input);
    }

    private function replace(iterable $input): void
    {
        if (is_array($input)) {
            $this->pipeline = $input;

            return;
        }

        // IteratorAggregate is a nuance best we avoid dealing with.
        // For example, CallbackFilterIterator needs a plain Iterator.
        while ($input instanceof IteratorAggregate) {
            $input = $input->getIterator();
        }

        /** @var Iterator $input */
        $this->pipeline = $input;
    }

    /**
     * @psalm-suppress TypeDoesNotContainType
     * @phpstan-assert-if-false non-empty-array|Traversable $this->pipeline
     */
    private function empty(): bool
    {
        if (!isset($this->pipeline)) {
            return true;
        }

        if ([] === $this->pipeline) {
            return true;
        }

        return false;
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
     * Appends the contents of an interable to the end of the pipeline.
     *
     * @return self<TKey, TValue>
     */
    public function append(?iterable $values = null)
    {
        $this->immutablePipeline = $this->immutablePipeline->append($values);

        return $this;
    }

    /**
     * Appends a list of values to the end of the pipeline.
     *
     * @param mixed ...$vector
     * @return self<TKey, TValue>
     */
    public function push(...$vector)
    {
        $this->immutablePipeline = $this->immutablePipeline->push(...$vector);

        return $this;
    }

    /**
     * Prepends the pipeline with the contents of an iterable.
     *
     * @return self<TKey, TValue>
     */
    public function prepend(?iterable $values = null)
    {
        $this->immutablePipeline = $this->immutablePipeline->prepend($values);

        return $this;
    }

    /**
     * Prepends the pipeline with a list of values.
     *
     * @param mixed ...$vector
     * @return self<TKey, TValue>
     */
    public function unshift(...$vector)
    {
        return $this->prepend($vector);
    }

    /**
     * Determine if the internal pipeline will be replaced when appending/prepending.
     *
     * Utility method for appending/prepending methods.
     */
    private function willReplace(?iterable $values = null): bool
    {
        // Nothing needs to be done here.
        if (null === $values || [] === $values) {
            return true;
        }

        // No shortcuts are applicable if the pipeline was initialized.
        if (!$this->empty()) {
            return false;
        }

        // Handle edge cases there
        $this->replace($values);

        return true;
    }

    /**
     * Replace the internal pipeline with a combination of two non-empty iterables, array-optimized.
     *
     * Utility method for appending/prepending methods.
     *
     * @return self<TKey, TValue>
     */
    private function join(iterable $left, iterable $right)
    {
        // We got two arrays, that's what we will use.
        if (is_array($left) && is_array($right)) {
            $this->pipeline = array_merge($left, $right);

            return $this;
        }

        // Last, join the hard way.
        $this->pipeline = self::joinYield($left, $right);

        return $this;
    }

    /**
     * Replace the internal pipeline with a combination of two non-empty iterables, generator-way.
     */
    private static function joinYield(iterable $left, iterable $right): Generator
    {
        yield from $left;
        yield from $right;
    }

    /**
     * Flattens inputs: arrays become lists.
     *
     * @return self<int, mixed>
     */
    public function flatten()
    {
        $this->immutablePipeline = $this->immutablePipeline->flatten();

        return $this;
    }

    /**
     * An extra variant of `map` which unpacks arrays into arguments. Flattens inputs if no callback provided.
     *
     * @param ?callable $func
     *
     * @return self<int, mixed>
     */
    public function unpack(?callable $func = null)
    {
        $this->immutablePipeline = $this->immutablePipeline->unpack($func);

        return $this;
    }

    /**
     * Chunks the pipeline into arrays with length elements. The last chunk may contain less than length elements.
     *
     * @param int<1, max> $length        the size of each chunk
     * @param bool        $preserve_keys When set to true keys will be preserved. Default is false which will reindex the chunk numerically.
     *
     * @return self<int, list<TValue>>
     */
    public function chunk(int $length, bool $preserve_keys = false)
    {
        $this->immutablePipeline = $this->immutablePipeline->chunk($length, $preserve_keys);

        return $this;
    }

    /**
     * @psalm-param positive-int $length
     */
    private static function toChunks(Generator $input, int $length, bool $preserve_keys): Generator
    {
        while ($input->valid()) {
            yield iterator_to_array(self::take($input, $length), $preserve_keys);
            $input->next();
        }
    }

    /**
     * Takes a callback that for each input value may return one or yield many. Also takes an initial generator, where it must not require any arguments.
     *
     * With no callback is a no-op (can safely take a null).
     *
     * @template TMapValue
     * @param ?callable(TValue, TKey): TMapValue $func a callback must either return a value or yield values (return a generator)
     *
     * @return self<TKey, TMapValue>
     */
    public function map(?callable $func = null)
    {
        $this->immutablePipeline = $this->immutablePipeline->map($func);

        return $this;
    }

    private static function apply(iterable $previous, callable $func): Generator
    {
        foreach ($previous as $key => $value) {
            $result = $func($value);

            // For generators we use keys they provide
            if ($result instanceof Generator) {
                yield from $result;

                continue;
            }

            // In case of a plain old mapping function we use the original key
            yield $key => $result;
        }
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
    public function cast(?callable $func = null)
    {
        $this->immutablePipeline = $this->immutablePipeline->cast($func);

        return $this;
    }

    private static function applyOnce(iterable $previous, callable $func): Generator
    {
        foreach ($previous as $key => $value) {
            yield $key => $func($value);
        }
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
    public function filter(?callable $func = null, bool $strict = false)
    {
        // No-op: an empty array or null.
        if ($this->empty()) {
            return $this;
        }

        $func = self::resolvePredicate($func, $strict);

        // We got an array, that's what we need. Moving along.
        if (is_array($this->pipeline)) {
            $this->pipeline = array_filter($this->pipeline, $func);

            return $this;
        }

        $this->pipeline = new CallbackFilterIterator($this->pipeline, $func);

        return $this;
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
     * Skips elements while the predicate returns true, and keeps everything after the predicate return false just once.
     *
     * @param callable $predicate a callback returning boolean value
     * @return self<TKey, TValue>
     */
    public function skipWhile(callable $predicate)
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
        if (null === $func) {
            $func = self::defaultReducer(...);
        }

        if ($this->empty()) {
            return $initial;
        }

        if (is_array($this->pipeline)) {
            return array_reduce($this->pipeline, $func, $initial);
        }

        foreach ($this->pipeline as $value) {
            $initial = $func($initial, $value);
        }

        return $initial;
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
        if (!isset($this->pipeline)) {
            return new EmptyIterator();
        }

        if ($this->pipeline instanceof Traversable) {
            return $this->pipeline;
        }

        /** @var ArrayIterator<TKey, TValue> */
        return new ArrayIterator($this->pipeline);
    }

    /**
     * By default, returns all values regardless of keys used, discarding all keys in the process. This is a terminal operation.
     * @return list<mixed>
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
     * @return array<(int&TKey)|(string&TKey), TValue>
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
    ) {
        $count ??= 0;

        $this->cast(static function ($input) use (&$count) {
            ++$count;

            return $input;
        });

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
        if ($this->empty()) {
            return 0;
        }

        return $result;
    }

    /**
     * @return self<TKey, TValue>
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
     * @return self<TKey, TValue>
     */
    public function slice(int $offset, ?int $length = null)
    {
        $this->immutablePipeline = $this->immutablePipeline->slice($offset, $length);

        return $this;
    }

    public function zip(iterable ...$inputs): \Pipeline\Standard
    {
        // Consume until seen enough.
        foreach ($input as $_) {
            /** @psalm-suppress DocblockTypeContradiction */
            if (0 === $skip--) {
                break;
            }
        }

        // Avoid yielding from an exhausted generator. Gives error:
        // Generator passed to yield from was aborted without proper return and is unable to continue
        if (!$input->valid()) {
            return;
        }

        yield from $input;
    }

    /**
     * @psalm-param positive-int $take
     */
    private static function take(Generator $input, int $take): Generator
    {
        while ($input->valid()) {
            yield $input->key() => $input->current();

            // Stop once taken enough.
            if (0 === --$take) {
                break;
            }

            $input->next();
        }
    }

    private static function tail(iterable $input, int $length): Generator
    {
        $buffer = [];

        foreach ($input as $key => $value) {
            if (count($buffer) < $length) {
                // Read at most N records.
                $buffer[] = [$key, $value];

                continue;
            }

            // Remove and add one record each time.
            array_shift($buffer);
            $buffer[] = [$key, $value];
        }

        foreach ($buffer as [$key, $value]) {
            yield $key => $value;
        }
    }

    /**
     * Allocates a buffer of $length, and reads records into it, proceeding with FIFO when buffer is full.
     */
    private static function head(iterable $input, int $length): Generator
    {
        $buffer = [];

        foreach ($input as $key => $value) {
            $buffer[] = [$key, $value];

            if (count($buffer) > $length) {
                [$key, $value] = array_shift($buffer);
                yield $key => $value;
            }
        }
    }

    /**
     * Performs a lazy zip operation on iterables, not unlike that of
     * array_map with first argument set to null. Also known as transposition.
     *
     * @return self<int, list<mixed>>
     */
    public function zip(iterable ...$inputs)
    {
        if ([] === $inputs) {
            return $this;
        }

        if (!isset($this->pipeline)) {
            $this->replace(array_shift($inputs));
        }

        if ([] === $inputs) {
            return $this;
        }

        $this->map(static function ($item): array {
            return [$item];
        });

        foreach (self::toIterators(...$inputs) as $iterator) {
            // MultipleIterator won't work here because it'll stop at first invalid iterator.
            $this->map(static function (array $current) use ($iterator) {
                if (!$iterator->valid()) {
                    $current[] = null;

                    return $current;
                }

                $current[] = $iterator->current();
                $iterator->next();

                return $current;
            });
        }

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

    /**
     * @return self<int, TValue>
     */
    public function values()
    {
        $this->immutablePipeline = $this->immutablePipeline->values();

        return $this;
    }

    private static function valuesOnly(iterable $previous): Generator
    {
        foreach ($previous as $value) {
            yield $value;
        }
    }

    /**
     * @return self<int, TKey>
     */
    public function keys()
    {
        if ($this->empty()) {
            // No-op: null.
            return $this;
        }

        if (is_array($this->pipeline)) {
            $this->pipeline = array_keys($this->pipeline);

            return $this;
        }

        $this->pipeline = self::keysOnly($this->pipeline);

        return $this;
    }

    private static function keysOnly(iterable $previous): Generator
    {
        foreach ($previous as $key => $_) {
            yield $key;
        }
    }

    /**
     * @return self<TValue, TKey>
     */
    public function flip()
    {
        if ($this->empty()) {
            // No-op: null.
            return $this;
        }

        if (is_array($this->pipeline)) {
            $this->pipeline = array_flip($this->pipeline);

            return $this;
        }

        $this->pipeline = self::flipKeysAndValues($this->pipeline);

        return $this;
    }

    private static function flipKeysAndValues(iterable $previous): Generator
    {
        foreach ($previous as $key => $value) {
            yield $value => $key;
        }
    }

    /**
     * @return self<int, array{TKey, TValue}>
     */
    public function tuples()
    {
        if ($this->empty()) {
            // No-op: null.
            return $this;
        }

        if (is_array($this->pipeline)) {
            $this->pipeline = array_map(
                fn($key, $value) => [$key, $value],
                array_keys($this->pipeline),
                $this->pipeline
            );

            return $this;
        }


        $this->pipeline = self::toTuples($this->pipeline);

        return $this;
    }

    private static function toTuples(iterable $previous): Generator
    {
        foreach ($previous as $key => $value) {
            yield [$key, $value];
        }
    }

    private function feedRunningVariance(Helper\RunningVariance $variance, ?callable $castFunc)
    {
        if (null === $castFunc) {
            $castFunc = floatval(...);
        }

        return $this->cast(static function ($value) use ($variance, $castFunc) {
            /** @var float|null $float */
            $float = $castFunc($value);

            if (null !== $float) {
                $variance->observe($float);
            }

            // Returning the original value here
            return $value;
        });
    }

    /**
     * Feeds in an instance of RunningVariance.
     *
     * @param ?Helper\RunningVariance &$variance the instance of RunningVariance; initialized unless provided
     * @param ?callable               $castFunc  the cast callback, returning ?float; null values are not counted
     *
     * @param-out Helper\RunningVariance $variance
     *
     * @return self<TKey, TValue>
     */
    public function runningVariance(
        ?Helper\RunningVariance &$variance,
        ?callable $castFunc = null
    ) {
        $variance ??= new Helper\RunningVariance();

        $this->feedRunningVariance($variance, $castFunc);

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

    public function __call(string $name, array $arguments)
    {
        $pipeline = new Standard($this->pipeline);

        return $pipeline->$name(...$arguments);
    }
}
