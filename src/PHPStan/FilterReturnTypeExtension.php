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
use PHPStan\Type\MixedType;
use Override;

use function array_filter;
use function in_array;
use function count;
use function method_exists;
use function is_array;

/**
 * @final
 */
class FilterReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    private FilterTypeNarrowingHelper $helper;

    public function __construct(?FilterTypeNarrowingHelper $helper = null)
    {
        $this->helper = $helper ?? new FilterTypeNarrowingHelper();
    }
    #[Override]
    public function getClass(): string
    {
        return \Pipeline\Standard::class;
    }

    #[Override]
    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return 'filter' === $methodReflection->getName();
    }

    #[Override]
    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        $args = array_filter($methodCall->args, static fn($arg) => $arg instanceof Arg);
        $parametersAcceptor = ParametersAcceptorSelector::selectFromArgs($scope, $args, $methodReflection->getVariants());
        $returnType = $parametersAcceptor->getReturnType();

        // Check if return type has the expected structure for type narrowing
        $keyValueTypes = $this->helper->extractKeyAndValueTypes($returnType);
        if (null === $keyValueTypes) {
            return $returnType;
        }

        [$keyType, $valueType] = $keyValueTypes;

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
            // Remove falsy types from the value type using helper
            if ($valueType instanceof UnionType) {
                $filteredTypes = $this->helper->removeFalsyTypesFromUnion($valueType);

                if ([] !== $filteredTypes && count($filteredTypes) < count($valueType->getTypes())) {
                    $result = $this->helper->createGenericTypeWithFilteredValues($keyType, $filteredTypes);
                    if (null !== $result) {
                        return $result;
                    }
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

        // Determine the target type using helper
        $targetType = null;
        $functionName = null;

        // Handle string callbacks (e.g., 'is_string')
        if ($callbackArg instanceof \PhpParser\Node\Scalar\String_) {
            $functionName = $this->helper->extractFunctionNameFromStringCallback($callbackArg);
            if (null !== $functionName) {
                $targetType = $this->helper->getTargetTypeForFunction($functionName);
            }
        }
        // Handle first-class callable syntax (e.g., is_string(...))
        elseif ($callbackArg instanceof FuncCall) {
            $functionName = $this->helper->extractFunctionNameFromFirstClassCallable($callbackArg);
            if (null !== $functionName) {
                $targetType = $this->helper->getTargetTypeForFunction($functionName);
            }
        }

        if (null !== $targetType) {
            // Filter the value type using helper
            if ($valueType instanceof UnionType) {
                $filteredTypes = $this->helper->filterUnionTypeByTarget($valueType, $targetType);

                if ([] !== $filteredTypes) {
                    $result = $this->helper->createGenericTypeWithFilteredValues($keyType, $filteredTypes);
                    if (null !== $result) {
                        return $result;
                    }
                }
            } elseif ($targetType->isSuperTypeOf($valueType)->yes()) {
                // Value type already matches, return as-is
                return $returnType;
            }
        }

        return $returnType;
    }
}
