<?php
/*
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
 * You're not expected to use this class directly, but you may subclass it as you see fit.
 */
abstract class Principal implements Interfaces\Pipeline
{
    /**
     * Pre-primed pipeline.
     *
     * @var \Traversable
     */
    private $pipeline;

    /**
     * Contructor with an optional source of data.
     *
     * @param \Traversable|null $input
     */
    public function __construct(\Traversable $input = null)
    {
        $this->pipeline = $input;
    }

    public function map(callable $func)
    {
        // If we know the callback is one of us, we can use a shortcut
        // This also allows inheriting classes to replace the pipeline
        // Moreover, using one of use as a callback is dubious
        if ($func instanceof self) {
            $this->pipeline = $func->getIterator();

            return $this;
        }

        // That's the standard case for any next stages
        if ($this->pipeline) {
            $this->pipeline = self::apply($this->pipeline, $func);

            return $this;
        }

        // Let's check what we got for a start
        $this->pipeline = $func();

        // Generator is a generator, moving along
        if ($this->pipeline instanceof \Generator) {
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

    private static function apply($previous, callable $func): \Generator
    {
        foreach ($previous as $value) {
            $result = $func($value);
            if ($result instanceof \Generator) {
                yield from $result;
            } else {
                // Case of a plain old mapping function
                yield $result;
            }
        }
    }

    public function filter(callable $func)
    {
        if (!$this->pipeline) {
            // No-op: either an empty array or null
            return $this;
        }

        $this->pipeline = new \CallbackFilterIterator($this->pipeline, $func);

        return $this;
    }

    /**
     * @return \Traversable
     */
    public function getIterator()
    {
        if ($this->pipeline instanceof \Traversable) {
            return $this->pipeline;
        }

        if ($this->pipeline) {
            return new \ArrayIterator($this->pipeline);
        }

        return new \EmptyIterator();
    }

    public function toArray()
    {
        // With non-primed pipeline just return an empty array
        if (empty($this->pipeline)) {
            return [];
        }

        // We got what we need, moving along
        if (is_array($this->pipeline)) {
            return $this->pipeline;
        }

        // Because `yield from` does not reset keys we have to ignore them on export to return every item.
        // http://php.net/manual/en/language.generators.syntax.php#control-structures.yield.from
        return iterator_to_array($this, false);
    }

    public function reduce(callable $func, $initial = null)
    {
        if (is_array($this->pipeline)) {
            return array_reduce($this->pipeline, $func, $initial);
        }

        foreach ($this as $value) {
            $initial = $func($initial, $value);
        }

        return $initial;
    }

    /**
     * Must not take any arguments whatsoever.
     *
     * @return \Generator
     */
    public function __invoke()
    {
        yield from $this;
    }
}
