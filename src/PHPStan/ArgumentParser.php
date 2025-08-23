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

namespace Pipeline\PHPStan;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;

use function array_filter;
use function array_values;

/**
 * Parses and extracts arguments from method calls.
 */
class ArgumentParser
{
    /**
     * Extract only Arg instances from method call arguments.
     * Filters out VariadicPlaceholder and other non-Arg types.
     *
     * @return array<int, Arg>
     */
    public function extractArgs(MethodCall $methodCall): array
    {
        return array_values(
            array_filter(
                $methodCall->args,
                static fn($arg) => $arg instanceof Arg
            )
        );
    }

    /**
     * Get the callback argument if present (first argument).
     *
     * @param array<int, Arg> $args
     */
    public function getCallbackArg(array $args): ?Arg
    {
        return $args[0] ?? null;
    }

    /**
     * Get the strict mode argument if present (second argument or named 'strict').
     *
     * @param array<int, Arg> $args
     */
    public function getStrictArg(array $args): ?Arg
    {
        // Check for named parameter first
        foreach ($args as $arg) {
            if (null !== $arg->name && 'strict' === $arg->name->toString()) {
                return $arg;
            }
        }

        // Check positional second parameter
        return $args[1] ?? null;
    }
}
