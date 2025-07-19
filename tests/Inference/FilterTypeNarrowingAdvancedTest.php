<?php

declare(strict_types=1);

namespace Tests\Pipeline\Inference;

use PHPUnit\Framework\TestCase;
use Pipeline\Standard;

use function Pipeline\take;
use function PHPStan\Testing\assertType;

/**
 * Advanced tests for PHPStan FilterReturnTypeExtension to achieve 100% mutation coverage.
 * 
 * @coversNothing
 * @group integration
 * @internal
 */
class FilterTypeNarrowingAdvancedTest extends TestCase
{
    public function testFilterWithNamedStrictParameter(): void
    {
        /** @var Standard<int, string|int|null|false> $pipeline */
        $pipeline = take(['hello', 42, null, false, 'world']);
        assertType('Pipeline\Standard<int, string|int|false|null>', $pipeline);
        
        // Test named parameter strict: true
        $filtered = $pipeline->filter(strict: true);
        assertType('Pipeline\Standard<int, string|int>', $filtered);
        
        $result = [];
        foreach ($filtered as $value) {
            assertType('string|int', $value);
            $result[] = $value;
        }
        
        $this->assertSame(['hello', 42, 'world'], $result);
    }
    
    public function testFilterWithPositionalStrictParameter(): void
    {
        /** @var Standard<int, string|null|false> $pipeline */
        $pipeline = take(['hello', null, false, 'world']);
        assertType('Pipeline\Standard<int, string|false|null>', $pipeline);
        
        // Test positional second parameter true
        $filtered = $pipeline->filter(null, true);
        assertType('Pipeline\Standard<int, string>', $filtered);
        
        $result = $filtered->toList();
        $this->assertSame(['hello', 'world'], $result);
    }
    
    public function testFilterWithCallbackAndStrictMode(): void
    {
        /** @var Standard<int, string|int|null|false> $pipeline */
        $pipeline = take(['hello', 42, null, false, 'world', 0]);
        assertType('Pipeline\Standard<int, string|int|false|null>', $pipeline);
        
        // Test is_string with strict mode
        $filtered = $pipeline->filter('is_string', true);
        assertType('Pipeline\Standard<int, string>', $filtered);
        
        $result = $filtered->toList();
        $this->assertSame(['hello', 'world'], $result);
    }
    
    public function testFilterWithIsInt(): void
    {
        /** @var Standard<int, string|int|float> $pipeline */
        $pipeline = take([1, 'hello', 2.5, 42, 'world']);
        assertType('Pipeline\Standard<int, string|int|float>', $pipeline);
        
        $filtered = $pipeline->filter(is_int(...));
        assertType('Pipeline\Standard<int, int>', $filtered);
        
        $result = $filtered->toList();
        $this->assertSame([1, 42], $result);
    }
    
    public function testFilterWithIsFloat(): void
    {
        /** @var Standard<int, string|int|float> $pipeline */
        $pipeline = take([1, 'hello', 2.5, 42, 3.14]);
        assertType('Pipeline\Standard<int, string|int|float>', $pipeline);
        
        $filtered = $pipeline->filter('is_float');
        assertType('Pipeline\Standard<int, float>', $filtered);
        
        $result = $filtered->toList();
        $this->assertSame([2.5, 3.14], $result);
    }
    
    public function testFilterWithIsBool(): void
    {
        /** @var Standard<int, string|bool|int> $pipeline */
        $pipeline = take([true, 'hello', false, 42]);
        assertType('Pipeline\Standard<int, string|bool|int>', $pipeline);
        
        $filtered = $pipeline->filter(is_bool(...));
        assertType('Pipeline\Standard<int, bool>', $filtered);
        
        $result = $filtered->toList();
        $this->assertSame([true, false], $result);
    }
    
    public function testFilterWithIsArray(): void
    {
        /** @var Standard<int, string|array<mixed>|int> $pipeline */
        $pipeline = take(['hello', [1, 2], 42, ['a', 'b']]);
        assertType('Pipeline\Standard<int, string|array<mixed>|int>', $pipeline);
        
        $filtered = $pipeline->filter('is_array');
        assertType('Pipeline\Standard<int, array<mixed>>', $filtered);
        
        $result = $filtered->toList();
        $this->assertSame([[1, 2], ['a', 'b']], $result);
    }
    
    public function testFilterWithIsObject(): void
    {
        $obj1 = new \stdClass();
        $obj2 = new \DateTime();
        
        /** @var Standard<int, string|object|int> $pipeline */
        $pipeline = take(['hello', $obj1, 42, $obj2]);
        assertType('Pipeline\Standard<int, string|object|int>', $pipeline);
        
        $filtered = $pipeline->filter(is_object(...));
        assertType('Pipeline\Standard<int, object>', $filtered);
        
        $result = $filtered->toList();
        $this->assertSame([$obj1, $obj2], $result);
    }
    
    public function testFilterWithNoCallback(): void
    {
        /** @var Standard<int, string|null|false|0> $pipeline */
        $pipeline = take(['hello', null, false, 0, 'world']);
        assertType('Pipeline\Standard<int, string|false|0|null>', $pipeline);
        
        // Default filter removes all falsy values
        $filtered = $pipeline->filter();
        assertType('Pipeline\Standard<int, string>', $filtered);
        
        $result = $filtered->toList();
        $this->assertSame(['hello', 'world'], $result);
    }
    
    public function testFilterOnNonUnionType(): void
    {
        /** @var Standard<int, string> $pipeline */
        $pipeline = take(['hello', 'world']);
        assertType('Pipeline\Standard<int, string>', $pipeline);
        
        // Filter on non-union type should return same type
        $filtered = $pipeline->filter(is_string(...));
        assertType('Pipeline\Standard<int, string>', $filtered);
        
        $result = $filtered->toList();
        $this->assertSame(['hello', 'world'], $result);
    }
    
    public function testFilterWithUnknownCallback(): void
    {
        /** @var Standard<int, string|int> $pipeline */
        $pipeline = take(['hello', 42, 'world']);
        assertType('Pipeline\Standard<int, string|int>', $pipeline);
        
        // Unknown callback doesn't narrow types
        $filtered = $pipeline->filter('unknown_function');
        assertType('Pipeline\Standard<int, string|int>', $filtered);
        
        // This would fail at runtime, but PHPStan doesn't know
        $this->expectNotToPerformAssertions();
    }
    
    public function testFilterStrictOnNonUnionType(): void
    {
        /** @var Standard<int, string> $pipeline */
        $pipeline = take(['hello', 'world', '']);
        assertType('Pipeline\Standard<int, string>', $pipeline);
        
        // Strict mode on type without null/false should return same type
        $filtered = $pipeline->filter(strict: true);
        assertType('Pipeline\Standard<int, string>', $filtered);
        
        $result = $filtered->toList();
        $this->assertSame(['hello', 'world', ''], $result);
    }
    
    public function testChainedFilters(): void
    {
        /** @var Standard<int, string|int|float|null|false> $pipeline */
        $pipeline = take(['hello', 42, 3.14, null, false, 'world']);
        assertType('Pipeline\Standard<int, string|int|float|false|null>', $pipeline);
        
        // Chain multiple filters
        $filtered = $pipeline
            ->filter(strict: true)  // Remove null and false
            ->filter(is_string(...));  // Keep only strings
            
        assertType('Pipeline\Standard<int, string>', $filtered);
        
        $result = $filtered->toList();
        $this->assertSame(['hello', 'world'], $result);
    }
}