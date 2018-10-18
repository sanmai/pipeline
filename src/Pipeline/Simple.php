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
 * Concrete pipeline with sensible default callbacks.
 */
class Simple extends Principal
{
    /**
     * With no callback drops all null and false values (not unlike array_filter defaults).
     */
    public function filter(callable $func = null)
    {
        if ($func) {
            return parent::filter($func);
        }

        return parent::filter(function ($value) {
            return (bool) $value;
        });
    }

    /**
     * Defaults to summation.
     *
     * @param null|mixed $initial
     */
    public function reduce(callable $func = null, $initial = null)
    {
        if ($func) {
            return parent::reduce($func, $initial);
        }

        return parent::reduce(function ($a, $b) {
            return $a + $b;
        }, 0);
    }

    /**
     * @return \Traversable
     */
    public function getIterator()
    {
        // with non-primed pipeline just return empty iterator
        if (!$iterator = parent::getIterator()) {
            return new \ArrayIterator([]);
        }

        return $iterator;
    }

    /**
     * Returns an array representation with keys ignored. Backported from 2.x.
     *
     * @return array
     */
    public function toArray(): array
    {
        // Because `yield from` does not reset keys we have to ignore them on export to return every item.
        // http://php.net/manual/en/language.generators.syntax.php#control-structures.yield.from
        return iterator_to_array($this, false);
    }
}
