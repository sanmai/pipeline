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

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function array_filter;
use function in_array;

final class FilterReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return \Pipeline\Standard::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return 'filter' === $methodReflection->getName();
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        $args = array_filter($methodCall->args, static fn($arg) => $arg instanceof Arg);
        $parametersAcceptor = ParametersAcceptorSelector::selectFromArgs($scope, $args, $methodReflection->getVariants());
        $returnType = $parametersAcceptor->getReturnType();

        if (!$returnType->isObject()->yes()) {
            return $returnType;
        }

        $classNames = $returnType->getObjectClassNames();
        if (!in_array(\Pipeline\Standard::class, $classNames, true)) {
            return $returnType;
        }

        if (isset($methodCall->args[1]) && $methodCall->args[1] instanceof Arg) {
            $strictType = $scope->getType($methodCall->args[1]->value);
            if ($strictType->isTrue()->yes()) {
                // Value type narrowing is impossible without generics, return original object type.
                // This extension exists purely for extensibility and framework completeness.
            }
        }

        return $returnType;
    }
}
