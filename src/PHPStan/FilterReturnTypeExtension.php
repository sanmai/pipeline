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
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Override;

/**
 * @final
 */
class FilterReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    private FilterTypeNarrowingHelper $helper;
    private ArgumentParser $argumentParser;
    private StrictModeDetector $strictModeDetector;
    private CallbackResolver $callbackResolver;
    private TypeNarrower $typeNarrower;

    public function __construct(
        ?FilterTypeNarrowingHelper $helper = null,
        ?ArgumentParser $argumentParser = null,
        ?StrictModeDetector $strictModeDetector = null,
        ?CallbackResolver $callbackResolver = null,
        ?TypeNarrower $typeNarrower = null
    ) {
        $this->helper = $helper ?? new FilterTypeNarrowingHelper();
        $this->argumentParser = $argumentParser ?? new ArgumentParser();
        $this->strictModeDetector = $strictModeDetector ?? new StrictModeDetector();
        $this->callbackResolver = $callbackResolver ?? new CallbackResolver($this->helper);
        $this->typeNarrower = $typeNarrower ?? new TypeNarrower($this->helper);
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
        // Step 1: Parse arguments
        $args = $this->argumentParser->extractArgs($methodCall);

        // Step 2: Get the return type from PHPStan
        $parametersAcceptor = ParametersAcceptorSelector::selectFromArgs($scope, $args, $methodReflection->getVariants());
        $returnType = $parametersAcceptor->getReturnType();

        // Step 3: Extract key and value types from the return type
        $keyValueTypes = $this->helper->extractKeyAndValueTypes($returnType);
        if (null === $keyValueTypes) {
            return $returnType;
        }

        [$keyType, $valueType] = $keyValueTypes;

        // Step 4: Get arguments
        $strictArg = $this->argumentParser->getStrictArg($args);
        $callbackArg = $this->argumentParser->getCallbackArg($args);

        // Step 5: Handle callback filtering (takes precedence over strict mode)
        if (null !== $callbackArg) {
            $targetType = $this->callbackResolver->resolveCallbackType($callbackArg);
            if (null !== $targetType) {
                $narrowedType = $this->typeNarrower->narrowForCallback($keyType, $valueType, $targetType);
                if (null !== $narrowedType) {
                    return $narrowedType;
                }
            }
        }

        // Step 6: Check for strict mode (only if no callback)
        if ($this->strictModeDetector->isStrictMode($strictArg, $scope)) {
            $narrowedType = $this->typeNarrower->narrowForStrictMode($keyType, $valueType);
            if (null !== $narrowedType) {
                return $narrowedType;
            }
        }

        // Step 7: Default filter (no callback, no strict) - removes all falsy values
        if (null === $callbackArg) {
            $narrowedType = $this->typeNarrower->narrowForDefaultFilter($keyType, $valueType);
            if (null !== $narrowedType) {
                return $narrowedType;
            }
        }

        return $returnType;
    }
}
