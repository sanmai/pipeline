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

namespace Tests\Pipeline\PHPStan\Data;

use stdClass;

use function PHPStan\Testing\assertType;
use function Pipeline\take;

/**
 * Test filter() with is_string(...) first-class callable.
 */
function testFilterWithIsStringFirstClassCallable(): void
{
    $pipeline = take(['hello', 123, false, 'world']);

    // After filter(is_string(...)), only strings should remain
    assertType('Pipeline\\Standard<int, string>', $pipeline->filter(is_string(...)));
}

/**
 * Test filter() with 'is_string' string callback.
 */
function testFilterWithIsStringCallback(): void
{
    $pipeline = take([1, 'hello', null, 'world', 42]);

    // After filter('is_string'), only strings should remain
    assertType('Pipeline\\Standard<int, string>', $pipeline->filter('is_string'));
}

/**
 * Test filter() with is_int(...) first-class callable.
 */
function testFilterWithIsInt(): void
{
    $pipeline = take([1, 'hello', 3.14, 42, 'world']);

    // After filter(is_int(...)), only integers should remain
    assertType('Pipeline\\Standard<int, int>', $pipeline->filter(is_int(...)));
}

/**
 * Test filter() with is_float(...) first-class callable.
 */
function testFilterWithIsFloat(): void
{
    $pipeline = take([1, 'hello', 3.14, 42, 2.71]);

    // After filter(is_float(...)), only floats should remain
    assertType('Pipeline\\Standard<int, float>', $pipeline->filter(is_float(...)));
}

/**
 * Test filter() with is_bool(...) first-class callable.
 */
function testFilterWithIsBool(): void
{
    $pipeline = take([1, true, 'hello', false, null]);

    // After filter(is_bool(...)), only booleans should remain
    assertType('Pipeline\\Standard<int, bool>', $pipeline->filter(is_bool(...)));
}

/**
 * Test filter() with is_array(...) first-class callable.
 */
function testFilterWithIsArray(): void
{
    $pipeline = take([[1, 2], 'hello', null, [3, 4, 5]]);

    // After filter(is_array(...)), only arrays should remain
    assertType('Pipeline\\Standard<int, array<mixed, mixed>>', $pipeline->filter(is_array(...)));
}

/**
 * Test filter() with is_object(...) first-class callable.
 */
function testFilterWithIsObject(): void
{
    $obj = new stdClass();
    $pipeline = take([$obj, 'hello', null, 123]);

    // After filter(is_object(...)), only objects should remain
    assertType('Pipeline\\Standard<int, object>', $pipeline->filter(is_object(...)));
}

/**
 * Test filter(strict: true) removes null and false values.
 */
function testFilterStrictMode(): void
{
    $pipeline = take(['hello', null, false, 'world', '']);

    // After filter(strict: true), null and false should be removed
    assertType('Pipeline\\Standard<int, string>', $pipeline->filter(strict: true));
}

/**
 * Test filter(strict: true) with mixed types.
 */
function testFilterStrictModeWithMixed(): void
{
    $pipeline = take([null, 0, '', '0', 'hello', false, 0.0]);

    // After filter(strict: true), only null and false are removed
    assertType('Pipeline\\Standard<int, int|string|float>', $pipeline->filter(strict: true));
}

/**
 * Test chained filters for type narrowing.
 */
function testChainedFilters(): void
{
    $pipeline = take([1, 'hello', null, 42, 'world']);

    // First remove nulls, then keep only strings
    assertType(
        'Pipeline\\Standard<int, string>',
        $pipeline
            ->filter(strict: true)  // Removes null
            ->filter(is_string(...)) // Keeps only strings
    );
}

/**
 * Test filter with complex union types.
 */
function testComplexUnionTypeNarrowing(): void
{
    $pipeline = take([1, 'hello', false, null, 42, 'world']);

    // First remove null and false, then keep only strings
    assertType(
        'Pipeline\\Standard<int, string>',
        $pipeline
            ->filter(strict: true)
            ->filter(is_string(...))
    );
}

/**
 * Test filter with unsupported callback (should return original type).
 */
function testFilterWithUnsupportedCallback(): void
{
    $pipeline = take([1, 'hello', null, 42, 'world']);

    // Custom callback not supported by extension, type should remain unchanged
    assertType(
        'Pipeline\\Standard<int, int|string|null>',
        $pipeline->filter('custom_function')
    );
}

/**
 * Test filter with no callback (should return original type).
 */
function testFilterWithNoCallback(): void
{
    $pipeline = take([1, 'hello', null, 42, 'world']);

    // No callback, type should remain unchanged
    assertType(
        'Pipeline\\Standard<int, int|string|null>',
        $pipeline->filter()
    );
}
