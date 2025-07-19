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
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Type\Type;

/**
 * Resolves callback arguments to their target types.
 */
class CallbackResolver
{
    public function __construct(
        private FilterTypeNarrowingHelper $helper
    ) {}

    /**
     * Resolve a callback argument to its target type.
     *
     * @return Type|null The type that the callback filters for, or null if unknown
     */
    public function resolveCallbackType(?Arg $callbackArg): ?Type
    {
        if (null === $callbackArg) {
            return null;
        }

        $callbackExpr = $callbackArg->value;

        // Handle string callbacks (e.g., 'is_string')
        if ($callbackExpr instanceof String_) {
            $functionName = $this->helper->extractFunctionNameFromStringCallback($callbackExpr);
            if (null !== $functionName) {
                return $this->helper->getTargetTypeForFunction($functionName);
            }
        }

        // Handle first-class callable syntax (e.g., is_string(...))
        if ($callbackExpr instanceof FuncCall) {
            $functionName = $this->helper->extractFunctionNameFromFirstClassCallable($callbackExpr);
            if (null !== $functionName) {
                return $this->helper->getTargetTypeForFunction($functionName);
            }
        }

        return null;
    }
}
