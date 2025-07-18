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
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Arg;
use PhpParser\Node\VariadicPlaceholder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use PHPStan\Type\StringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\FloatType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Generic\GenericObjectType;

use function array_filter;
use function in_array;
use function count;
use function method_exists;
use function is_array;

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

        // Check if return type has generic types
        if (!method_exists($returnType, 'getTypes')) {
            return $returnType;
        }

        $classNames = $returnType->getObjectClassNames();
        if (!in_array(\Pipeline\Standard::class, $classNames, true)) {
            return $returnType;
        }

        // Get the generic types (TKey, TValue)
        $genericTypes = $returnType->getTypes();
        if (!is_array($genericTypes) || 2 !== count($genericTypes)) {
            return $returnType;
        }

        [$keyType, $valueType] = $genericTypes;

        // Ensure we have Type objects
        if (!$keyType instanceof Type || !$valueType instanceof Type) {
            return $returnType;
        }

        // Check for strict mode filter(strict: true) which removes falsy values
        $isStrictMode = false;

        // Check if this is filter(strict: true) with named parameter
        foreach ($methodCall->args as $i => $arg) {
            if (!$arg instanceof Arg) {
                continue;
            }
            if (null === $arg->name || 'strict' !== $arg->name->toString()) {
                continue;
            }
            $strictType = $scope->getType($arg->value);
            if ($strictType->isTrue()->yes()) {
                $isStrictMode = true;
                break;
            }
        }

        // Also check if second parameter is true (positional argument)
        if (!$isStrictMode && isset($methodCall->args[1]) && $methodCall->args[1] instanceof Arg) {
            $strictType = $scope->getType($methodCall->args[1]->value);
            if ($strictType->isTrue()->yes()) {
                $isStrictMode = true;
            }
        }

        if ($isStrictMode) {
            // Remove falsy types from the value type
            if ($valueType instanceof UnionType) {
                $filteredTypes = [];
                foreach ($valueType->getTypes() as $type) {
                    // Skip only null and false (strict mode behavior)
                    if ($type->isNull()->yes()) {
                        continue;
                    }
                    if ($type->isFalse()->yes()) {
                        continue;
                    }

                    $filteredTypes[] = $type;
                }

                if ([] !== $filteredTypes && count($filteredTypes) < count($valueType->getTypes())) {
                    $newValueType = 1 === count($filteredTypes) ? $filteredTypes[0] : TypeCombinator::union(...$filteredTypes);
                    return new GenericObjectType(\Pipeline\Standard::class, [$keyType, $newValueType]);
                }
            } elseif ($valueType->isNull()->yes() || $valueType->isFalse()->yes()) {
                // If the entire type is falsy, filter() would return empty
                return new GenericObjectType(\Pipeline\Standard::class, [$keyType, new \PHPStan\Type\NeverType()]);
            }
        }

        $callbackArg = null;
        if (isset($methodCall->args[0]) && $methodCall->args[0] instanceof Arg) {
            $callbackArg = $methodCall->args[0]->value;
        }

        if (null === $callbackArg) {
            return $returnType;
        }

        // Get the callback type to understand what we're dealing with
        $callbackType = $scope->getType($callbackArg);

        // Map type check functions to their corresponding types
        $typeMap = [
            'is_string' => new StringType(),
            'is_int' => new IntegerType(),
            'is_float' => new FloatType(),
            'is_bool' => new BooleanType(),
            'is_array' => new ArrayType(new IntegerType(), new StringType()),
            'is_object' => new ObjectType('object'),
        ];

        $targetType = null;
        $functionName = null;

        // Handle string callbacks (e.g., 'is_string')
        if ($callbackArg instanceof \PhpParser\Node\Scalar\String_) {
            $functionName = $callbackArg->value;
            if (isset($typeMap[$functionName])) {
                $targetType = $typeMap[$functionName];
            }
        }
        // Handle first-class callable syntax (e.g., is_string(...))
        elseif ($callbackArg instanceof FuncCall && $callbackArg->name instanceof Name) {
            // Check if it's a first-class callable (has VariadicPlaceholder)
            $isFirstClassCallable = false;
            foreach ($callbackArg->args as $arg) {
                if (!$arg instanceof VariadicPlaceholder) {
                    continue;
                }
                $isFirstClassCallable = true;
                break;
            }

            if (!$isFirstClassCallable) {
                return $returnType;
            }

            $functionName = $callbackArg->name->toString();
            if (isset($typeMap[$functionName])) {
                $targetType = $typeMap[$functionName];
            }
        }

        if (null !== $targetType) {
            // If the value type is a union, filter it
            if ($valueType instanceof UnionType) {
                $filteredTypes = [];
                foreach ($valueType->getTypes() as $type) {
                    if ($targetType->isSuperTypeOf($type)->yes()) {
                        $filteredTypes[] = $type;
                    }
                }

                if ([] !== $filteredTypes) {
                    $newValueType = 1 === count($filteredTypes) ? $filteredTypes[0] : TypeCombinator::union(...$filteredTypes);
                    return new GenericObjectType(\Pipeline\Standard::class, [$keyType, $newValueType]);
                }
            } elseif ($targetType->isSuperTypeOf($valueType)->yes()) {
                // Value type already matches, return as-is
                return $returnType;
            }
        }

        return $returnType;
    }
}
