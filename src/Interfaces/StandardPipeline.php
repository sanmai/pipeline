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

namespace Pipeline\Interfaces;

/**
 * Interface definitions for the standard pipeline.
 */
interface StandardPipeline extends PrincipalPipeline
{
    /**
     * {@inheritdoc}
     *
     * With no callback is a no-op (can safely take a null).
     *
     * @param ?callable $func {@inheritdoc}
     *
     * @return $this
     */
    public function map(?callable $func = null);

    /**
     * An extra variant of `map` which unpacks arrays into arguments. Flattens inputs if no callback provided.
     *
     * @param ?callable $func
     *
     * @return $this
     */
    public function unpack(?callable $func = null);

    /**
     * {@inheritdoc}
     *
     * With no callback drops all null and false values (not unlike array_filter does by default).
     *
     * @param ?callable $func {@inheritdoc}
     *
     * @return $this
     */
    public function filter(?callable $func = null);

    /**
     * {@inheritdoc}
     *
     * Defaults to summation.
     *
     * @param ?callable $func    {@inheritdoc}
     * @param ?mixed    $initial {@inheritdoc}
     *
     * @return null|mixed
     */
    public function reduce(?callable $func = null, $initial = null);

    /**
     * {@inheritdoc}
     */
    public function toArray(): array;
}
