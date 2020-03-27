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
 * Principal abstract pipeline with no defaults.
 *
 * @internal
 */
abstract class Principal implements Interfaces\PrincipalPipeline, Interfaces\ZipPipeline
{
    /**
     * Pre-primed pipeline.
     *
     * @var ?iterable
     */
    private $pipeline;

    /**
     * Contructor with an optional source of data.
     *
     * @param ?iterable $input
     */
    public function __construct(iterable $input = null)
    {
        // IteratorAggregate is a nuance best we avoid dealing with.
        // For example, CallbackFilterIterator needs a plain Iterator.
        while ($input instanceof \IteratorAggregate) {
            $input = $input->getIterator();
        }

        $this->pipeline = $input;
    }

    /**
     * {@inheritdoc}
     *
     * @param callable $func {@inheritdoc}
     *
     * @return $this
     */
    public function map(callable $func)
    {
        // That's the standard case for any next stages
        if (\is_iterable($this->pipeline)) {
            /** @phan-suppress-next-line PhanTypeMismatchArgument */
            $this->pipeline = self::apply($this->pipeline, $func);

            return $this;
        }

        // Let's check what we got for a start
        $this->pipeline = $func();

        // Generator is a generator, moving along
        if ($this->pipeline instanceof \Generator) {
            // It is possible to detect if callback is a generator like so:
            // (new \ReflectionFunction($func))->isGenerator();
            // Yet this will restrict users from replacing the pipeline and has unknown performance impact.
            // But, again, we could add a direct internal method to replace the pipeline, e.g. as done by unpack()

            return $this;
        }

        // Not a generator means we were given a simple value to be treated as an array
        // We do not cast to an array here because casting a null to an array results in
        // an empty array; that's surprising and not how map() works for other values
        $this->pipeline = [
            $this->pipeline,
        ];

        return $this;
    }

    private static function apply(iterable $previous, callable $func): iterable
    {
        foreach ($previous as $value) {
            $result = $func($value);
            if ($result instanceof \Generator) {
                yield from $result;

                continue;
            }

            // Case of a plain old mapping function
            yield $result;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param callable $func {@inheritdoc}
     *
     * @return $this
     */
    public function filter(callable $func)
    {
        if (null === $this->pipeline) {
            // No-op: null.
            return $this;
        }

        if ([] === $this->pipeline) {
            // No-op: an empty array.
            return $this;
        }

        // We got an array, that's what we need. Moving along.
        if (\is_array($this->pipeline)) {
            $this->pipeline = \array_filter($this->pipeline, $func);

            return $this;
        }

        /** @var \Iterator $iterator */
        $iterator = $this->pipeline;

        /** @phan-suppress-next-line PhanTypeMismatchArgumentInternal */
        $this->pipeline = new \CallbackFilterIterator($iterator, $func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): \Traversable
    {
        if ($this->pipeline instanceof \Traversable) {
            return $this->pipeline;
        }

        if (null !== $this->pipeline) {
            return new \ArrayIterator($this->pipeline);
        }

        return new \EmptyIterator();
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        if (null === $this->pipeline) {
            // With non-primed pipeline just return an empty array
            return [];
        }

        if ([] === $this->pipeline) {
            // Empty array is empty.
            return [];
        }

        // We got what we need, moving along
        if (\is_array($this->pipeline)) {
            return \array_values($this->pipeline);
        }

        // Because `yield from` does not reset keys we have to ignore them on export to return every item.
        // http://php.net/manual/en/language.generators.syntax.php#control-structures.yield.from
        return \iterator_to_array($this, false);
    }

    /**
     * {@inheritdoc}
     *
     * @param callable $func    {@inheritdoc}
     * @param mixed    $initial {@inheritdoc}
     *
     * @return null|mixed
     */
    public function reduce(callable $func, $initial)
    {
        if (\is_array($this->pipeline)) {
            return \array_reduce($this->pipeline, $func, $initial);
        }

        foreach ($this as $value) {
            $initial = $func($initial, $value);
        }

        return $initial;
    }

    public function zip(iterable ...$inputs): self
    {
        if (null === $this->pipeline) {
            $this->pipeline = \array_shift($inputs);
        }

        if ([] === $inputs) {
            return $this;
        }

        $this->map(static function ($item): array {
            return [$item];
        });

        foreach (self::toIterators(...$inputs) as $iterator) {
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

    /** @return \Iterator[] */
    private static function toIterators(iterable ...$inputs): array
    {
        return \array_map(static function (iterable $input): \Iterator {
            while ($input instanceof \IteratorAggregate) {
                $input = $input->getIterator();
            }

            if ($input instanceof \Iterator) {
                return $input;
            }

            // IteratorAggregate and Iterator are out of picture, which leaves... an array

            /** @var array $input */
            return new \ArrayIterator($input);
        }, $inputs);
    }
}
