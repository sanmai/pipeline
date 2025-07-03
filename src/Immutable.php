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
 * Immutable pipeline.
 *
 * @template-implements IteratorAggregate<mixed, mixed>
 */
class Immutable implements IteratorAggregate, Countable
{
    /**
     * The source of data for this pipeline.
     *
     * @var iterable<mixed, mixed>
     */
    private $pipeline;

    /**
     * The original input iterable, used to create fresh iterators.
     *
     * @var iterable<mixed, mixed>
     */
    private $originalInput;

    /**
     * Constructor with an optional source of data.
     */
    public function __construct(?iterable $input = null)
    {
        if (null === $input) {
            $this->pipeline = new EmptyIterator();
            $this->originalInput = new EmptyIterator();
        } else {
            $this->pipeline = $input;
            $this->originalInput = $input;
        }
    }

    /**
     * Appends the contents of an iterable to the end of the pipeline.
     */
    public function append(?iterable $values = null): self
    {
        if (null === $values) {
            return $this;
        }

        $newPipeline = new \AppendIterator();
        $newPipeline->append($this->getIterator());
        $newPipeline->append(self::generatorFromIterable($values));

        return new self($newPipeline);
    }

    /**
     * Appends a list of values to the end of the pipeline.
     *
     * @param mixed ...$vector
     */
    public function push(...$vector): self
    {
        return $this->append($vector);
    }

    /**
     * Prepends the pipeline with the contents of an iterable.
     */
    public function prepend(?iterable $values = null): self
    {
        if (null === $values) {
            return $this;
        }

        $newPipeline = new \AppendIterator();
        $newPipeline->append(self::generatorFromIterable($values));
        $newPipeline->append($this->getIterator());

        return new self($newPipeline);
    }

    /**
     * Prepends the pipeline with a list of values.
     *
     * @param mixed ...$vector
     */
    public function unshift(...$vector): self
    {
        return $this->prepend($vector);
    }

    public function flatten(): self
    {
        return new self(self::flattenIterable($this->getIterator()));
    }

    private static function flattenIterable(iterable $iterable): Generator
    {
        foreach ($iterable as $item) {
            if (is_iterable($item)) {
                yield from self::flattenIterable($item);
            } else {
                yield $item;
            }
        }
    }

    public function unpack(?callable $func = null): self
    {
        return new self(self::unpackIterable($this->getIterator(), $func));
    }

    private static function unpackIterable(iterable $iterable, ?callable $func): Generator
    {
        foreach ($iterable as $item) {
            if (is_array($item)) {
                if (null !== $func) {
                    yield $func(...$item);
                } else {
                    yield from $item;
                }
            }
        }
    }

    public function chunk(int $length, bool $preserve_keys = false): self
    {
        return new self(self::chunkIterable($this->getIterator(), $length, $preserve_keys));
    }

    private static function chunkIterable(iterable $iterable, int $length, bool $preserve_keys): Generator
    {
        $chunk = [];
        foreach ($iterable as $key => $value) {
            $chunk[$key] = $value;
            if (count($chunk) === $length) {
                yield $preserve_keys ? $chunk : array_values($chunk);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            yield $preserve_keys ? $chunk : array_values($chunk);
        }
    }

    public function map(?callable $func = null): self
    {
        return new self(self::mapIterable($this->getIterator(), $func));
    }

    private static function mapIterable(iterable $iterable, ?callable $func): Generator
    {
        if (null === $func) {
            $func = fn($value) => $value;
        }

        foreach ($iterable as $key => $value) {
            yield $key => $func($value, $key);
        }
    }

    public function cast(?callable $func = null): self
    {
        return new self(self::castIterable($this->getIterator(), $func));
    }

    private static function castIterable(iterable $iterable, ?callable $func): Generator
    {
        if (null === $func) {
            $func = fn($value) => (string) $value;
        }

        foreach ($iterable as $key => $value) {
            yield $key => $func($value, $key);
        }
    }

    public function filter(?callable $func = null, bool $strict = false): self
    {
        return new self(self::filterIterable($this->getIterator(), $func, $strict));
    }

    private static function filterIterable(iterable $iterable, ?callable $func, bool $strict): Generator
    {
        if (null === $func) {
            $func = fn($value) => $strict ? $value !== null : (bool) $value;
        }

        foreach ($iterable as $key => $value) {
            if ($func($value, $key)) {
                yield $key => $value;
            }
        }
    }

    public function skipWhile(callable $predicate): self
    {
        return new self(self::skipWhileIterable($this->getIterator(), $predicate));
    }

    private static function skipWhileIterable(iterable $iterable, callable $predicate): Generator
    {
        $skipping = true;
        foreach ($iterable as $key => $value) {
            if ($skipping && $predicate($value, $key)) {
                continue;
            }
            $skipping = false;
            yield $key => $value;
        }
    }

    public function reduce(?callable $func = null, $initial = null)
    {
        if (null === $func) {
            $func = fn($carry, $item) => $carry + $item;
        }

        return array_reduce($this->toList(), $func, $initial);
    }

    public function fold($initial, ?callable $func = null)
    {
        return $this->reduce($func, $initial);
    }

    #[Override]
    public function getIterator(): Traversable
    {
        // Always return a fresh iterator from the original input
        return self::generatorFromIterable($this->originalInput);
    }

    public function toList(): array
    {
        return iterator_to_array($this->getIterator(), false);
    }

    /**
     * @deprecated Use toList() or toAssoc() instead
     */
    public function toArray(bool $preserve_keys = false): array
    {
        return iterator_to_array($this->getIterator(), $preserve_keys);
    }

    public function toAssoc(): array
    {
        return iterator_to_array($this->getIterator(), true);
    }

    /**
     * @deprecated Use toAssoc() instead
     */
    public function toArrayPreservingKeys(): array
    {
        return $this->toAssoc();
    }

    public function runningCount(?int &$count): self
    {
        return new self(self::runningCountIterable($this->getIterator(), $count));
    }

    private static function runningCountIterable(iterable $iterable, ?int &$count): Generator
    {
        $count = 0;
        foreach ($iterable as $key => $value) {
            $count++;
            yield $key => $value;
        }
    }

    #[Override]
    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    public function stream(): self
    {
        return new self(self::makeNonRewindable($this->getIterator()));
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

    public function slice(int $offset, ?int $length = null): self
    {
        return new self(self::sliceIterable($this->getIterator(), $offset, $length));
    }

    private static function sliceIterable(iterable $iterable, int $offset, ?int $length): Generator
    {
        $i = 0;
        foreach ($iterable as $key => $value) {
            if ($i >= $offset) {
                if (null === $length || $i < $offset + $length) {
                    yield $key => $value;
                } else {
                    break;
                }
            }
            $i++;
        }
    }

    public function zip(iterable ...$inputs): self
    {
        return new self(self::zipIterable($this->getIterator(), ...$inputs));
    }

    private static function zipIterable(iterable ...$iterables): Generator
    {
        $iterators = [];
        foreach ($iterables as $iterable) {
            $iterators[] = self::generatorFromIterable($iterable);
        }

        while (true) {
            $values = [];
            $allDone = true;
            foreach ($iterators as $iterator) {
                if ($iterator->valid()) {
                    $allDone = false;
                    $values[] = $iterator->current();
                    $iterator->next();
                } else {
                    $values[] = null;
                }
            }

            if ($allDone) {
                break;
            }

            yield $values;
        }
    }

    public function reservoir(int $size, ?callable $weightFunc = null): array
    {
        $reservoir = [];
        $count = 0;

        foreach ($this->getIterator() as $item) {
            $count++;
            if (count($reservoir) < $size) {
                $reservoir[] = $item;
            } else {
                $weight = $weightFunc ? $weightFunc($item, $count) : mt_rand(0, $count - 1);
                if ($weight < $size) {
                    $reservoir[$weight] = $item;
                }
            }
        }

        return $reservoir;
    }

    public function min()
    {
        return min(iterator_to_array($this->getIterator(), false));
    }

    public function max()
    {
        return max(iterator_to_array($this->getIterator(), false));
    }

    public function values(): self
    {
        return new self(self::valuesIterable($this->getIterator()));
    }

    private static function valuesIterable(iterable $iterable): Generator
    {
        foreach ($iterable as $value) {
            yield $value;
        }
    }

    public function keys(): self
    {
        return new self(self::keysIterable($this->getIterator()));
    }

    private static function keysIterable(iterable $iterable): Generator
    {
        foreach ($iterable as $key => $_) {
            yield $key;
        }
    }

    public function flip(): self
    {
        return new self(self::flipIterable($this->getIterator()));
    }

    private static function flipIterable(iterable $iterable): Generator
    {
        foreach ($iterable as $key => $value) {
            yield $value => $key;
        }
    }

    public function tuples(): self
    {
        return new self(self::tuplesIterable($this->getIterator()));
    }

    private static function tuplesIterable(iterable $iterable): Generator
    {
        foreach ($iterable as $key => $value) {
            yield [$key, $value];
        }
    }

    public function runningVariance(?Helper\RunningVariance &$variance, ?callable $castFunc = null): self
    {
        return new self(self::runningVarianceIterable($this->getIterator(), $variance, $castFunc));
    }

    private static function runningVarianceIterable(iterable $iterable, ?Helper\RunningVariance &$variance, ?callable $castFunc): Generator
    {
        if (null === $variance) {
            $variance = new Helper\RunningVariance();
        }

        foreach ($iterable as $key => $value) {
            $variance->observe($castFunc ? $castFunc($value) : $value);
            yield $key => $value;
        }
    }

    public function finalVariance(?callable $castFunc = null, ?Helper\RunningVariance $variance = null): Helper\RunningVariance
    {
        if (null === $variance) {
            $variance = new Helper\RunningVariance();
        }

        foreach ($this->getIterator() as $value) {
            $variance->observe($castFunc ? $castFunc($value) : $value);
        }

        return $variance;
    }

    public function each(callable $func): void
    {
        foreach ($this->getIterator() as $key => $value) {
            $func($value, $key);
        }
    }
}