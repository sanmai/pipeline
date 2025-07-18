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

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\VariadicPlaceholder;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

use function is_array;
use function count;
use function in_array;
use function method_exists;

/**
 * Helper class for filter type narrowing logic.
 *
 * This class implements the "how" - each step of type narrowing as focused methods.
 * The main extension acts as the orchestrator, defining the "what" and "when".
 */
final class FilterTypeNarrowingHelper
{
    /**
     * Get the type map for supported type checking functions.
     *
     * @return array<string, Type>
     */
    public function getTypeMap(): array
    {
        return [
            'is_string' => new StringType(),
            'is_int' => new IntegerType(),
            'is_float' => new FloatType(),
            'is_bool' => new BooleanType(),
            'is_array' => new ArrayType(new MixedType(), new MixedType()),
            'is_object' => new ObjectType('object'),
        ];
    }

    /**
     * Extract function name from string callback.
     */
    public function extractFunctionNameFromStringCallback(\PhpParser\Node\Scalar\String_ $callback): ?string
    {
        $functionName = $callback->value;
        $typeMap = $this->getTypeMap();

        return isset($typeMap[$functionName]) ? $functionName : null;
    }

    /**
     * Extract function name from first-class callable.
     */
    public function extractFunctionNameFromFirstClassCallable(FuncCall $funcCall): ?string
    {
        if (!$funcCall->name instanceof Name) {
            return null;
        }

        if (!$this->isFirstClassCallable($funcCall)) {
            return null;
        }

        $functionName = $funcCall->name->toString();
        $typeMap = $this->getTypeMap();

        return isset($typeMap[$functionName]) ? $functionName : null;
    }

    /**
     * Check if a FuncCall is a first-class callable (has VariadicPlaceholder).
     */
    public function isFirstClassCallable(FuncCall $funcCall): bool
    {
        foreach ($funcCall->args as $arg) {
            if ($arg instanceof VariadicPlaceholder) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the target type for a function name.
     */
    public function getTargetTypeForFunction(string $functionName): ?Type
    {
        $typeMap = $this->getTypeMap();

        return $typeMap[$functionName] ?? null;
    }

    /**
     * Remove falsy types (null and false) from a union type for strict mode.
     *
     * @return Type[]
     */
    public function removeFalsyTypesFromUnion(UnionType $unionType): array
    {
        $filteredTypes = [];

        foreach ($unionType->getTypes() as $type) {
            // Skip only null and false (strict mode behavior)
            if ($type->isNull()->yes()) {
                continue;
            }
            if ($type->isFalse()->yes()) {
                continue;
            }

            $filteredTypes[] = $type;
        }

        return $filteredTypes;
    }

    /**
     * Filter union type by target type.
     *
     * @return Type[]
     */
    public function filterUnionTypeByTarget(UnionType $unionType, Type $targetType): array
    {
        $filteredTypes = [];

        foreach ($unionType->getTypes() as $type) {
            if ($targetType->isSuperTypeOf($type)->yes()) {
                $filteredTypes[] = $type;
            }
        }

        return $filteredTypes;
    }

    /**
     * Create a new generic type with filtered value types.
     *
     * @param Type[] $filteredTypes
     */
    public function createGenericTypeWithFilteredValues(Type $keyType, array $filteredTypes): ?GenericObjectType
    {
        if ([] === $filteredTypes) {
            return null;
        }

        $newValueType = 1 === count($filteredTypes)
            ? $filteredTypes[0]
            : TypeCombinator::union(...$filteredTypes);

        return new GenericObjectType(\Pipeline\Standard::class, [$keyType, $newValueType]);
    }

    /**
     * Check if the return type has the expected structure for type narrowing.
     */
    public function isValidReturnTypeStructure(Type $returnType): bool
    {
        if (!$returnType->isObject()->yes()) {
            return false;
        }

        if (!method_exists($returnType, 'getTypes')) {
            return false;
        }

        $classNames = $returnType->getObjectClassNames();
        if (!in_array(\Pipeline\Standard::class, $classNames, true)) {
            return false;
        }

        $genericTypes = $returnType->getTypes();
        if (!is_array($genericTypes) || 2 !== count($genericTypes)) {
            return false;
        }

        [$keyType, $valueType] = $genericTypes;
        return $keyType instanceof Type && $valueType instanceof Type;
    }

    /**
     * Extract key and value types from a valid return type.
     *
     * @return array{Type, Type}|null
     */
    public function extractKeyAndValueTypes(Type $returnType): ?array
    {
        if (!$this->isValidReturnTypeStructure($returnType)) {
            return null;
        }

        if (!method_exists($returnType, 'getTypes')) {
            return null;
        }

        $genericTypes = $returnType->getTypes();
        if (!is_array($genericTypes) || 2 !== count($genericTypes)) {
            return null;
        }

        [$keyType, $valueType] = $genericTypes;

        return [$keyType, $valueType];
    }
}
