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

use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

use function count;
use function method_exists;
use function in_array;

/**
 * Applies type narrowing logic based on filter parameters.
 */
class TypeNarrower
{
    public function __construct(
        private FilterTypeNarrowingHelper $helper
    ) {}

    /**
     * Narrow the pipeline type based on strict mode.
     *
     * @return Type|null The narrowed type, or null if no narrowing occurred
     */
    public function narrowForStrictMode(Type $keyType, Type $valueType): ?Type
    {
        if ($valueType instanceof UnionType) {
            $filteredTypes = $this->helper->removeFalsyTypesFromUnion($valueType);

            if ([] !== $filteredTypes && count($filteredTypes) < count($valueType->getTypes())) {
                return $this->helper->createGenericTypeWithFilteredValues($keyType, $filteredTypes);
            }
        } elseif ($valueType->isNull()->yes() || $valueType->isFalse()->yes()) {
            // If the entire type is falsy, filter() would return empty
            return new GenericObjectType(\Pipeline\Standard::class, [$keyType, new NeverType()]);
        }

        return null;
    }

    /**
     * Narrow the pipeline type based on a callback that filters to a specific type.
     *
     * @return Type|null The narrowed type, or null if no narrowing occurred
     */
    public function narrowForCallback(Type $keyType, Type $valueType, Type $targetType): ?Type
    {
        if (!($valueType instanceof UnionType)) {
            return null;
        }

        $filteredTypes = $this->helper->filterUnionTypeByTarget($valueType, $targetType);
        if ([] === $filteredTypes) {
            return null;
        }

        return $this->helper->createGenericTypeWithFilteredValues($keyType, $filteredTypes);
    }

    /**
     * Narrow the pipeline type based on default filter (removes all falsy values).
     *
     * @return Type|null The narrowed type, or null if no narrowing occurred
     */
    public function narrowForDefaultFilter(Type $keyType, Type $valueType): ?Type
    {
        if ($valueType instanceof UnionType) {
            $filteredTypes = $this->helper->removeFalsyValuesFromUnion($valueType);

            if ([] !== $filteredTypes && count($filteredTypes) < count($valueType->getTypes())) {
                return $this->helper->createGenericTypeWithFilteredValues($keyType, $filteredTypes);
            }
        } elseif ($this->isFalsyType($valueType)) {
            // If the entire type is falsy, filter() would return empty
            return new GenericObjectType(\Pipeline\Standard::class, [$keyType, new NeverType()]);
        }

        return null;
    }

    /**
     * Check if a type is entirely falsy (would be removed by default filter).
     */
    private function isFalsyType(Type $type): bool
    {
        // Check common falsy types
        if ($type->isNull()->yes()) {
            return true;
        }

        if ($type->isFalse()->yes()) {
            return true;
        }

        // Check for literal 0
        // Check for literal 0.0
        // Check for empty string

        if ($type->isConstantScalarValue()->yes()) {
            $value = $type->getConstantScalarValues()[0] ?? null;
            return 0 === $value || 0.0 === $value || '' === $value;
        }

        // Check for empty array
        if (!$type->isArray()->yes()) {
            return false;
        }

        $arraySize = $type->getArraySize();
        if (!$arraySize->isConstantScalarValue()->yes()) {
            return false;
        }

        $values = $arraySize->getConstantScalarValues();
        return in_array(0, $values, true);
    }
}
