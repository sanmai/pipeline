<?php
/*
 * Copyright 2017 Alexey Kopytko <alexey@kopytko.com>
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

namespace Pipeline;

/**
 * Principal abstract pipeline with no defaults.
 *
 * You're not expected to use this class directly, but you may subclass it as you see fit.
 */
abstract class Principal implements Interfaces\Pipeline, Interfaces\Unpack
{
    /**
     * Pre-primed pipeline.
     *
     * @var \Traversable
     */
    private $pipeline;

    /**
     * Optional source of data.
     *
     * @param \Traversable $input
     */
    public function __construct(\Traversable $input = null)
    {
        $this->pipeline = $input;
    }

    public function map(callable $func)
    {
        assert((new \ReflectionFunction($func))->isGenerator(), 'Callback must be a generator');
        assert($this->pipeline || (new \ReflectionFunction($func))->getNumberOfRequiredParameters() == 0, 'Initial generator must not require parameters');

        if (!$this->pipeline) {
            $this->pipeline = call_user_func($func);

            return $this;
        }

        $previous = $this->pipeline;
        $this->pipeline = call_user_func(function () use ($previous, $func) {
            foreach ($previous as $value) {
                $result = $func($value);
                if ($result instanceof \Generator) {
                    foreach ($result as $value) {
                        yield $value;
                    }
                } else {
                    // Case of a plain old mapping function
                    yield $result;
                }
            }
        });

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @param ?callable $func {@inheritdoc}
     *
     * @return $this
     */
    public function unpack(callable $func = null)
    {
        $func = $func ? $func : function (...$args) {
            foreach ($args as $arg) {
                yield $arg;
            }
        };

        return $this->map(function ($args = []) use ($func) {
            $result = $func(...$args);
            if ($result instanceof \Generator) {
                foreach ($result as $value) {
                    yield $value;
                }
            } else {
                // Case of a plain old mapping function
                yield $result;
            }
        });
    }

    public function filter(callable $func)
    {
        $this->pipeline = new \CallbackFilterIterator($this->pipeline, $func);

        return $this;
    }

    /**
     * @return \Traversable
     */
    public function getIterator()
    {
        return $this->pipeline;
    }

    public function reduce(callable $func, $initial = null)
    {
        foreach ($this as $value) {
            $initial = $func($initial, $value);
        }

        return $initial;
    }
}
