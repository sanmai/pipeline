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
use Pipeline\Contracts\Output;
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
 * @template TOutput
 * @template-implements Output<TOutput>
 */
class Standard implements Countable, Output
{
    /**
     * Pre-primed pipeline.
     *
     * This is not a full `iterable` per se because we exclude IteratorAggregate before assigning a value.
     */
    private array|Iterator $pipeline;

    /**
     * Constructor with an optional source of data.
     */
    public function __construct(?iterable $input = null)
    {
        if (null !== $input) {
            $this->replace($input);
        }
    }

    private function replace(iterable $input): void
    {
        if (is_array($input)) {
            $this->pipeline = $input;

            return;
        }

        // IteratorAggregate is a nuance we'd best avoid dealing with.
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
        unset($this->pipeline);
    }

    /**
     * Appends the contents of an iterable to the end of the pipeline.
     *
     * @param null|iterable<TOutput> $values
     *
     * @return Standard<TOutput>
     */
    public function append(?iterable $values = null): self
    {
        // Do we need to do anything here?
        if ($this->willReplace($values)) {
            return $this;
        }

        return $this->join($this->pipeline, $values);
    }

    /**
     * Appends a list of values to the end of the pipeline.
     *
     * @param TOutput ...$vector
     *
     * @return Standard<TOutput>
     */
    public function push(...$vector): self
    {
        return $this->append($vector);
    }

    /**
     * Prepends the pipeline with the contents of an iterable.
     *
     * @param null|iterable<TOutput> $values
     *
     * @return Standard<TOutput>
     */
    public function prepend(?iterable $values = null): self
    {
        // Do we need to do anything here?
        if ($this->willReplace($values)) {
            return $this;
        }

        return $this->join($values, $this->pipeline);
    }

    /**
     * Prepends the pipeline with a list of values.
     *
     * @param TOutput ...$vector
     *
     * @return Standard<TOutput>
     */
    public function unshift(...$vector): self
    {
        return $this->prepend($vector);
    }

    /**
     * Determines if the internal pipeline will be replaced when appending/prepending.
     *
     * Utility method for appending/prepending methods.
     *
     * @phpstan-assert-if-false iterable $values
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

        // Handle edge cases in there
        $this->replace($values);

        return true;
    }

    /**
     * Replaces the internal pipeline with a combination of two non-empty iterables, array-optimized.
     *
     * Utility method for appending/prepending methods.
     */
    private function join(iterable $left, iterable $right): self
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
     * Replaces the internal pipeline with a combination of two non-empty iterables, generator-way.
     */
    private static function joinYield(iterable $left, iterable $right): Generator
    {
        yield from $left;
        yield from $right;
    }

    /**
     * Flattens inputs: arrays become lists.
     *
     * @return $this
     */
    public function flatten(): self
    {
        return $this->map(static function (iterable $args = []) {
            yield from $args;
        });
    }

    /**
     * An extra variant of `map` which unpacks arrays into arguments. Flattens inputs if no callback provided.
     *
     * @param ?callable $func A callback that accepts any number of arguments and returns a single value.
     *
     * @return $this
     */
    public function unpack(?callable $func = null): self
    {
        if (null === $func) {
            return $this->flatten();
        }

        return $this->map(static function (iterable $args = []) use ($func) {
            /** @psalm-suppress InvalidArgument */
            return $func(...$args);
        });
    }

    /**
     * Chunks the pipeline into arrays with length elements. The last chunk may contain less than length elements.
     *
     * @param int<1, max> $length The size of each chunk.
     * @param bool $preserve_keys When set to true keys will be preserved. Default is false which will reindex the chunk numerically.
     *
     * @return $this
     */
    public function chunk(int $length, bool $preserve_keys = false): self
    {
        // No-op: an empty array or null.
        if ($this->empty()) {
            return $this;
        }

        // Array shortcut
        if (is_array($this->pipeline)) {
            $this->pipeline = array_chunk($this->pipeline, $length, $preserve_keys);

            return $this;
        }

        $this->pipeline = self::toChunks(
            self::makeNonRewindable($this->pipeline),
            $length,
            $preserve_keys
        );

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
     * @param ?callable $func A callback must either return a value or yield values (return a generator).
     *
     * @return $this
     */
    public function map(?callable $func = null): self
    {
        if (null === $func) {
            return $this;
        }

        // That's the standard case for any next stages.
        if (isset($this->pipeline)) {
            $this->pipeline = self::apply($this->pipeline, $func);

            return $this;
        }

        // Let's check what we got for a start.
        $value = $func();

        // Generator is a generator, moving along
        if ($value instanceof Generator) {
            // It is possible to detect if callback is a generator like so:
            // (new \ReflectionFunction($func))->isGenerator();
            // Yet this will restrict users from replacing the pipeline and has unknown performance impact.
            // But, again, we could add a direct internal method to replace the pipeline, e.g. as done by unpack()
            $this->pipeline = $value;

            return $this;
        }

        // Not a generator means we were given a simple value to be treated as an array.
        // We do not cast to an array here because casting a null to an array results in
        // an empty array; that's surprising and not how it works for other values.
        $this->pipeline = [
            $value,
        ];

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
     * Takes a callback that for each input value is expected to return another single value. Unlike map(), it assumes no special treatment for generators.
     *
     * With no callback is a no-op (can safely take a null).
     *
     * @param ?callable $func A callback must return a value.
     *
     * @return $this
     */
    public function cast(?callable $func = null): self
    {
        if (null === $func) {
            return $this;
        }

        // We got an array, that's what we need. Moving along.
        if (isset($this->pipeline) && is_array($this->pipeline)) {
            $this->pipeline = array_map($func, $this->pipeline);

            return $this;
        }

        if (isset($this->pipeline)) {
            $this->pipeline = self::applyOnce($this->pipeline, $func);

            return $this;
        }

        // Else get the seed value.
        // We do not cast to an array here because casting a null to an array results in
        // an empty array; that's surprising and not how it works for other values.
        $this->pipeline = [
            $func(),
        ];

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
     * @param ?callable $func A callback that accepts a single value and returns a boolean value.
     * @param bool $strict When true, only `null` and `false` are filtered out.
     *
     * @return $this
     */
    public function filter(?callable $func = null, bool $strict = false): self
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
     * Skips elements while the predicate returns true, and keeps everything after the predicate returns false just once.
     *
     * @param callable $predicate A callback returning boolean value.
     */
    public function skipWhile(callable $predicate): self
    {
        // No-op: an empty array or null.
        if ($this->empty()) {
            return $this;
        }

        $predicate = self::resolveStringPredicate($predicate);

        $this->filter(static function ($value) use ($predicate): bool {
            static $done = false;

            if ($predicate($value) && !$done) {
                return false;
            }

            $done = true;

            return true;
        });

        return $this;
    }

    /**
     * Reduces input values to a single value. Defaults to summation. This is a terminal operation.
     *
     * @template T
     *
     * @param ?callable $func A reducer such as fn($carry, $item), must return updated $carry.
     * @param T $initial The initial value for the $carry.
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
     * @param T $initial Initial value for the $carry.
     * @param ?callable $func A reducer such as fn($carry, $item), must return updated $carry.
     *
     * @return T
     */
    public function fold($initial, ?callable $func = null)
    {
        if ($this->empty()) {
            return $initial;
        }

        $func ??= self::defaultReducer(...);

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

        return new ArrayIterator($this->pipeline);
    }

    /**
     * By default, returns all values regardless of keys used, discarding all keys in the process. This is a terminal operation.
     * @return list<mixed>
     */
    #[Override]
    public function toList(): array
    {
        // No-op: an empty array or null.
        if ($this->empty()) {
            return [];
        }

        // We got what we need, moving along.
        if (is_array($this->pipeline)) {
            return array_values($this->pipeline);
        }

        // Because `yield from` does not reset keys we have to ignore them on export by default to return every item.
        // http://php.net/manual/en/language.generators.syntax.php#control-structures.yield.from
        return iterator_to_array($this, preserve_keys: false);
    }

    /**
     * @deprecated Use toList() or toAssoc() instead.
     */
    public function toArray(bool $preserve_keys = false): array
    {
        if ($preserve_keys) {
            return $this->toAssoc();
        }

        return $this->toList();
    }

    /**
     * Returns all values preserving keys. This is a terminal operation.
     * @deprecated Use toAssoc() instead.
     */
    public function toArrayPreservingKeys(): array
    {
        return $this->toAssoc();
    }

    /**
     * Returns all values preserving keys. This is a terminal operation.
     */
    #[Override]
    public function toAssoc(): array
    {
        // No-op: an empty array or null.
        if ($this->empty()) {
            return [];
        }

        // We got what we need, moving along.
        if (is_array($this->pipeline)) {
            return $this->pipeline;
        }

        // Preserve keys by default.
        return iterator_to_array($this);
    }

    /**
     * Counts seen values online.
     *
     * @param ?int &$count The current count; initialized unless provided.
     *
     * @param-out int $count
     *
     * @return $this
     */
    public function runningCount(
        ?int &$count
    ): self {
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
            // With non-primed pipeline just return zero.
            return 0;
        }

        if (is_array($this->pipeline)) {
            return count($this->pipeline);
        }

        return iterator_count($this->pipeline);
    }

    /**
     * Converts the pipeline to a non-rewindable stream.
     *
     * @return $this
     */
    public function stream()
    {
        $this->pipeline = self::makeNonRewindable($this->pipeline ?? []);

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
        if ($this->empty()) {
            // With non-primed pipeline just move along.
            return $this;
        }

        if (0 === $length) {
            // We're not consuming anything assuming total laziness.
            $this->discard();

            return $this;
        }

        // Shortcut to array_slice() for actual arrays.
        if (is_array($this->pipeline)) {
            $this->pipeline = array_slice($this->pipeline, $offset, $length, true);

            return $this;
        }

        $this->pipeline = self::makeNonRewindable($this->pipeline);

        if ($offset < 0) {
            // If offset is negative, the sequence will start that far from the end of the array.
            $this->pipeline = self::tail($this->pipeline, -$offset);
        }

        if ($offset > 0) {
            // If offset is non-negative, the sequence will start at that offset in the array.
            $this->pipeline = self::skip($this->pipeline, $offset);
        }

        if ($length < 0) {
            // If length is given and is negative then the sequence will stop that many elements from the end of the array.
            $this->pipeline = self::head($this->pipeline, -$length);
        }

        if ($length > 0) {
            // If length is given and is positive, then the sequence will have up to that many elements in it.
            $this->pipeline = self::take($this->pipeline, $length);
        }

        return $this;
    }

    /**
     * @psalm-param positive-int $skip
     */
    private static function skip(Iterator $input, int $skip): Generator
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
     * @return $this
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

    /**
     * @return Iterator[]
     */
    private static function toIterators(iterable ...$inputs): array
    {
        return array_map(static function (iterable $input): Iterator {
            while ($input instanceof IteratorAggregate) {
                $input = $input->getIterator();
            }

            if ($input instanceof Iterator) {
                return $input;
            }

            // IteratorAggregate and Iterator are out of picture, which leaves... an array.

            /** @var array $input */
            return new ArrayIterator($input);
        }, $inputs);
    }

    /**
     * Performs reservoir sampling with an optional weighting function. Uses the most optimal algorithm.
     *
     * @see https://en.wikipedia.org/wiki/Reservoir_sampling
     *
     * @param int       $size       The desired sample size.
     * @param ?callable $weightFunc The optional weighting function.
     */
    public function reservoir(int $size, ?callable $weightFunc = null): array
    {
        if ($this->empty()) {
            return [];
        }

        if ($size <= 0) {
            // Discard the state to emulate full consumption
            $this->discard();

            return [];
        }

        // Algorithms below assume inputs are non-rewindable
        $this->pipeline = self::makeNonRewindable($this->pipeline);

        $result = null === $weightFunc ?
            self::reservoirRandom($this->pipeline, $size) :
            self::reservoirWeighted($this->pipeline, $size, $weightFunc);

        return iterator_to_array($result, true);
    }

    private static function drainValues(Generator $input): Generator
    {
        while ($input->valid()) {
            yield $input->current();
            // @infection-ignore-all
            $input->next();
        }
    }

    /**
     * Implements the simple and slow algorithm, commonly known as Algorithm R.
     *
     * @see https://en.wikipedia.org/wiki/Reservoir_sampling#Simple_algorithm
     *
     * @psalm-param positive-int $size
     */
    private static function reservoirRandom(Generator $input, int $size): Generator
    {
        // Take an initial sample (AKA fill the reservoir array)
        foreach (self::take($input, $size) as $output) {
            yield $output;
        }

        // Fetch the next value
        $input->next();

        // Return if there's nothing more to fetch
        if (!$input->valid()) {
            return;
        }

        $counter = $size;

        // Produce replacement elements with gradually decreasing probability
        foreach (self::drainValues($input) as $value) {
            $key = mt_rand(0, $counter);

            if ($key < $size) {
                yield $key => $value;
            }

            ++$counter;
        }
    }

    /**
     * Performs weighted random sampling.
     *
     * @see https://en.wikipedia.org/wiki/Reservoir_sampling#Algorithm_A-Chao
     *
     * @psalm-param positive-int $size
     */
    private static function reservoirWeighted(Generator $input, int $size, callable $weightFunc): Generator
    {
        $sum = 0.0;

        // Take an initial sample (AKA fill the reservoir array)
        foreach (self::take($input, $size) as $output) {
            yield $output;
            $sum += $weightFunc($output);
        }

        // Fetch the next value
        $input->next();

        // Return if there's nothing more to fetch
        if (!$input->valid()) {
            return;
        }

        foreach (self::drainValues($input) as $value) {
            $weight = $weightFunc($value);
            $sum += $weight;

            // probability for this item
            $probability = $weight / $sum;

            // @infection-ignore-all
            if (self::random() <= $probability) {
                yield mt_rand(0, $size - 1) => $value;
            }
        }
    }

    /**
     * Returns a pseudorandom value between zero (inclusive) and one (exclusive).
     *
     * @infection-ignore-all
     */
    private static function random(): float
    {
        return mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax();
    }

    /**
     * Finds the lowest value using the standard comparison rules. Returns null for empty sequences.
     *
     * @return null|mixed
     */
    public function min()
    {
        if ($this->empty()) {
            return null;
        }

        if (is_array($this->pipeline)) {
            /** @psalm-suppress ArgumentTypeCoercion */
            return min($this->pipeline);
        }

        $this->pipeline = self::makeNonRewindable($this->pipeline);

        $min = null;

        foreach ($this->pipeline as $min) {
            break;
        }

        // Return if there's nothing more to fetch
        if (!$this->pipeline->valid()) {
            return $min;
        }

        foreach ($this->pipeline as $value) {
            if ($value < $min) {
                $min = $value;
            }
        }

        return $min;
    }

    /**
     * Finds the highest value using the standard comparison rules. Returns null for empty sequences.
     *
     * @return null|mixed
     */
    public function max()
    {
        if ($this->empty()) {
            return null;
        }

        if (is_array($this->pipeline)) {
            /** @psalm-suppress ArgumentTypeCoercion */
            return max($this->pipeline);
        }

        $this->pipeline = self::makeNonRewindable($this->pipeline);

        // Everything is greater than null
        $max = null;

        foreach ($this->pipeline as $value) {
            if ($value > $max) {
                $max = $value;
            }
        }

        return $max;
    }

    /**
     * Extracts only the values from the pipeline, discarding keys.
     *
     * @return $this
     */
    public function values()
    {
        if ($this->empty()) {
            // No-op: null.
            return $this;
        }

        if (is_array($this->pipeline)) {
            $this->pipeline = array_values($this->pipeline);

            return $this;
        }

        $this->pipeline = self::valuesOnly($this->pipeline);

        return $this;
    }

    private static function valuesOnly(iterable $previous): Generator
    {
        foreach ($previous as $value) {
            yield $value;
        }
    }

    /**
     * Extracts only the keys from the pipeline, discarding values.
     *
     * @return $this
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
     * Swaps keys and values in the pipeline.
     *
     * @return $this
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
     * Converts each key-value pair into a tuple [key, value].
     *
     * @return $this
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

    private function feedRunningVariance(Helper\RunningVariance $variance, ?callable $castFunc): self
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
     * @param ?Helper\RunningVariance &$variance The instance of RunningVariance; initialized unless provided.
     * @param ?callable $castFunc The cast callback, returning ?float; null values are not counted.
     *
     * @param-out Helper\RunningVariance $variance
     *
     * @return $this
     */
    public function runningVariance(
        ?Helper\RunningVariance &$variance,
        ?callable $castFunc = null
    ): self {
        $variance ??= new Helper\RunningVariance();

        $this->feedRunningVariance($variance, $castFunc);

        return $this;
    }

    /**
     * Computes final statistics for the sequence.
     *
     * @param ?callable $castFunc The cast callback, returning ?float; null values are not counted.
     * @param ?Helper\RunningVariance $variance The optional instance of RunningVariance.
     */
    public function finalVariance(
        ?callable $castFunc = null,
        ?Helper\RunningVariance $variance = null
    ): Helper\RunningVariance {
        $variance ??= new Helper\RunningVariance();

        if ($this->empty()) {
            // No-op: an empty array.
            return $variance;
        }

        $this->feedRunningVariance($variance, $castFunc);

        if (is_array($this->pipeline)) {
            // We are done!
            return $variance;
        }

        // Consume every available item (fastest way to do it)
        $_ = iterator_count($this->pipeline);

        return $variance;
    }

    /**
     * Eagerly iterates over the sequence using the provided callback. Discards the sequence after iteration.
     *
     * @param callable $func A callback such as fn($value, $key); return value is ignored.
     * @param bool $discard Whether to discard the pipeline's iterator.
     */
    public function each(callable $func, bool $discard = true): void
    {
        if ($this->empty()) {
            return;
        }

        try {
            foreach ($this->pipeline as $key => $value) {
                $func($value, $key);
            }
        } finally {
            if ($discard) {
                $this->discard();
            }
        }
    }
}
