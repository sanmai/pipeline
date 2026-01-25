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

namespace Tests\Pipeline\Benchmarks;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pipeline\Standard;

use function array_map;
use function array_sum;
use function Pipeline\fromArray;
use function random_int;

/**
 *
 * @internal
 * @long
 */
#[CoversClass(Standard::class)]
final class BenchTest extends TestCase
{
    public const ITER_MAX = 100;

    #[DataProvider('provideCases')]
    public function testBenchmarks(callable $nativePhpFunc, callable $pipelineFunc): void
    {
        $this->assertSame($nativePhpFunc(), $pipelineFunc());

        for ($i = 0; $i < self::ITER_MAX; ++$i) {
            $pipelineFunc();
        }
    }

    public static function provideCases(): iterable
    {
        $products = [];

        for ($i = 1; $i <= self::ITER_MAX; ++$i) {
            $products[] = [
                'quantity' => random_int(1, 100),
            ];
        }

        yield 'Aggregating arrays' => [
            function () use ($products) {
                $qtys = array_map(function ($p) {
                    return $p['quantity'];
                }, $products);

                return array_sum($qtys);
            },
            function () use ($products) {
                return fromArray($products)->map(function ($p) {
                    return $p['quantity'];
                })->fold(0);
            },
        ];
    }
}
