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
 * Special case of empty pipeline that does nothing.
 *
 * You would want to use it when returning an empty pipeline, e.g. instead of:
 * new \Pipeline\Simple(new \ArrayIterator([]));
 */
class Noop implements Interfaces\Pipeline
{
    public function map(callable $func = null)
    {
        return $this;
    }

    public function filter(callable $func = null)
    {
        return $this;
    }

    public function reduce(callable $func = null, $initial = null)
    {
        return null;
    }

    public function getIterator()
    {
        return new \ArrayIterator([]);
    }
}
