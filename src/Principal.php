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
 * Principal abstract pipeline with no defaults. Could be subclassed.
 */
abstract class Principal implements Interfaces\PrincipalPipeline
{
    /**
     * Pre-primed pipeline.
     */
    private ?iterable $pipeline;

    /**
     * Contructor with an optional source of data.
     */
    public function __construct(?iterable $input = null)
    {
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
        $result = $func();

        // Generator is a generator, moving along
        if ($result instanceof \Generator) {
            $this->pipeline = $result;

            return $this;
        }

        // Not a generator means we were given a simple value to be treated as an array
        // We do not cast to an array here because casting a null to an array results in
        // an empty array; that's surprising and not how map() works for other values
        $this->pipeline = [
            $result,
        ];

        return $this;
    }

    private static function apply(iterable $previous, callable $func): \Generator
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
        if (null === $this->pipeline || [] === $this->pipeline) {
            // No-op: either an empty array or null.
            return $this;
        }

        // We got an array, that's what we need. Moving along.
        if (\is_array($this->pipeline)) {
            $this->pipeline = \array_filter($this->pipeline, $func);

            return $this;
        }

        // CallbackFilterIterator needs a plain Iterator
        if (!$this->pipeline instanceof \Iterator) {
            $this->pipeline = new \IteratorIterator($this->pipeline);
        }

        $this->pipeline = new \CallbackFilterIterator($this->pipeline, $func);

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

        if ($this->pipeline) {
            return new \ArrayIterator($this->pipeline);
        }

        return new \EmptyIterator();
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        // With non-primed pipeline just return an empty array
        if (empty($this->pipeline)) {
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
}
