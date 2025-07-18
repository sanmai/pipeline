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
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\NullType;

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
        $calledOnType = $scope->getType($methodCall->var);

        if (!$calledOnType instanceof GenericObjectType) {
            return $methodReflection->getReturnType();
        }

        $strictArgType = null;
        if (isset($methodCall->args[1])) {
            $strictArgType = $scope->getType($methodCall->args[1]->value);
        }

        if (null !== $strictArgType && $strictArgType->isTrue()->yes()) {
            [$keyType, $valueType] = $calledOnType->getTypes();
            $valueTypeWithoutNull = TypeCombinator::removeNull($valueType);
            return new GenericObjectType($calledOnType->getClassName(), [$keyType, $valueTypeWithoutNull]);
        }

        return $calledOnType; // fallback: same type, no narrowing
    }
}
