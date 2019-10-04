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
 * Concrete pipeline with sensible default callbacks.
 */
final class Standard extends Principal implements Interfaces\StandardPipeline
{
    public function unpack(?callable $func = null): self
    {
        $func = $func ?? static function (...$args) {
            yield from $args;
        };

        return $this->map(static function (iterable $args = []) use ($func) {
            return $func(...$args);
        });
    }

    /**
     * {@inheritdoc}
     *
     * With no callback is a no-op (can safely take a null).
     */
    public function map(?callable $func = null): self
    {
        if (null === $func) {
            return $this;
        }

        return parent::map($func);
    }

    public function filter(?callable $func = null): self
    {
        $func = $func ?? static fn ($value) => $value; // Cast is unnecessary

        // Strings usually are internal functions, which typically require exactly one parameter.
        if (\is_string($func)) {
            $func = static fn ($value) => $func($value);
        }

        return parent::filter($func);
    }

    /**
     * {@inheritdoc}
     *
     * @param ?callable $func    {@inheritdoc}
     * @param ?mixed    $initial {@inheritdoc}
     *
     * @return mixed
     */
    public function reduce(?callable $func = null, $initial = null)
    {
        if (null === $func) {
            return parent::reduce(static function ($carry, $item) {
                $carry += $item;

                return $carry;
            }, $initial ?? 0);
        }

        return parent::reduce($func, $initial);
    }

    /**
     * Not part of any public interface. Specific to this implementation.
     */
    public function __invoke(): \Generator
    {
        /** @phan-suppress-next-line PhanTypeInvalidYieldFrom */
        yield from $this;
    }
}
