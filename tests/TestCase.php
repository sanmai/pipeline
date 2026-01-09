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

namespace Tests\Pipeline;

use ArrayIterator;
use IteratorIterator;
use Pipeline\Standard;

use function Pipeline\fromArray;
use function Pipeline\take;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @param array $array
     * @return iterable<Standard>
     */
    protected static function pipelinesForInput(array $array): iterable
    {
        yield 'fromArray' => [fromArray($array)];

        foreach (self::wrapArray($array, withSameKeyGenerators: true) as $label => $iterable) {
            yield $label => [take($iterable)];
        }

        yield 'stream' => [fromArray($array)->stream()];
    }

    /**
     * @param array $array
     * @return iterable<string, iterable>
     */
    protected static function wrapArray(array $array, bool $withSameKeyGenerators = false): iterable
    {
        yield 'array' => $array;

        yield 'ArrayIterator' => new ArrayIterator($array);

        yield 'IteratorIterator' => new IteratorIterator(new ArrayIterator($array));

        yield 'IteratorAggregate' => new Standard(new IteratorIterator(new ArrayIterator($array)));

        yield 'Generator' => self::generatorFrom($array);

        if (!$withSameKeyGenerators) {
            return;
        }

        yield 'SameKeyGenerator(int)' => self::generatorWithKey(1, $array);

        yield 'SameKeyGenerator(string)' => self::generatorWithKey('a', $array);
    }

    private static function generatorFrom(array $array): iterable
    {
        yield from $array;
    }

    private static function generatorWithKey(mixed $key, array $array): iterable
    {
        foreach ($array as $value) {
            yield $key => $value;
        }
    }

}
