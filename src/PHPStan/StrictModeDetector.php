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
use PHPStan\Analyser\Scope;

/**
 * Detects if strict mode is enabled for filter() method.
 */
class StrictModeDetector
{
    /**
     * Check if strict mode is enabled based on the argument.
     */
    public function isStrictMode(?Arg $strictArg, Scope $scope): bool
    {
        if (null === $strictArg) {
            return false;
        }
        
        $strictType = $scope->getType($strictArg->value);
        return $strictType->isTrue()->yes();
    }
}