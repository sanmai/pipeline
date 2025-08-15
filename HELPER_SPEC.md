# FilterTypeNarrowingHelper Specification

## Overview

`FilterTypeNarrowingHelper` is a utility class that implements the core type narrowing logic for PHPStan static analysis of the Pipeline library's `filter()` method. It serves as the foundation for type analysis by providing focused, testable methods that handle specific aspects of type filtering logic.

## Design Philosophy

The class follows the **Sculpture Principle** by breaking down complex type narrowing into discrete, focused methods. Each method does one thing well, making the code easier to understand, test, and maintain. The helper acts as the "how" implementation while other classes orchestrate the "what" and "when".

## Architecture Role

```
FilterReturnTypeExtension (orchestrator)
├── ArgumentParser (extracts method call arguments)
├── StrictModeDetector (determines strict mode)
├── CallbackResolver (resolves callback types) ──┐
├── TypeNarrower (applies narrowing logic) ──────┤
└── FilterTypeNarrowingHelper (core utilities) ←─┘
```

The helper provides shared functionality used by multiple components:
- **CallbackResolver**: Uses helper to extract function names and get target types
- **TypeNarrower**: Uses helper to filter union types and create results
- **FilterReturnTypeExtension**: Uses helper to validate return types and extract generics

## Public Interface

### Type Mapping

#### `getTypeMap(): array<string, Type>`
Returns the mapping of supported type-checking functions to their corresponding PHPStan types.

**Supported Functions:**
- `is_string` → `StringType`
- `is_int` → `IntegerType`
- `is_float` → `FloatType`
- `is_bool` → `BooleanType`
- `is_array` → `ArrayType(mixed, mixed)`
- `is_object` → `ObjectType('object')`

### Callback Resolution

#### `extractFunctionNameFromStringCallback(String_ $callback): ?string`
Extracts function name from string callback (e.g., `'is_string'`).

**Returns:** Function name if supported, `null` otherwise.

#### `extractFunctionNameFromFirstClassCallable(FuncCall $funcCall): ?string`
Extracts function name from first-class callable syntax (e.g., `is_string(...)`).

**Validation:** Checks for `VariadicPlaceholder` to confirm first-class callable syntax.

#### `isFirstClassCallable(FuncCall $funcCall): bool`
Determines if a function call uses first-class callable syntax.

**Detection:** Looks for `VariadicPlaceholder` in arguments.

#### `getTargetTypeForFunction(string $functionName): ?Type`
Returns the target type for a supported function name.

**Lookup:** Uses type map to resolve function to type.

### Union Type Filtering

#### `removeFalsyTypesFromUnion(UnionType $unionType): Type[]`
Removes only `null` and `false` types from a union (strict mode behavior).

**Strict Mode Logic:**
- Removes `null` types (`isNull()->yes()`)
- Removes `false` types (`isFalse()->yes()`)
- Preserves all other types including `0`, `0.0`, `''`, `[]`

#### `removeFalsyValuesFromUnion(UnionType $unionType): Type[]`
Removes all falsy values from a union (default filter behavior).

**Default Filter Logic:**
- Removes `null` types
- Removes `false` types
- Removes literal `0` (integer)
- Removes literal `0.0` (float)
- Removes literal `''` (empty string)
- Removes literal `[]` (empty array)

**Edge Cases:**
- Only removes constant scalar values that are definitively falsy
- Non-constant types are preserved (can't determine runtime value)
- Empty arrays: only constant arrays with size 0 are removed

#### `filterUnionTypeByTarget(UnionType $unionType, Type $targetType): Type[]`
Filters union types to include only types compatible with the target.

**Compatibility:** Uses `$targetType->isSuperTypeOf($type)->yes()` for matching.

### Type Construction

#### `createGenericTypeWithFilteredValues(Type $keyType, Type[] $filteredTypes): ?GenericObjectType`
Creates a new `Pipeline\Standard<K, V>` type with filtered value types.

**Behavior:**
- Returns `null` if no filtered types
- Returns single type if one filtered type
- Creates `UnionType` if multiple filtered types
- Always preserves the original key type

### Type Structure Validation

#### `isValidReturnTypeStructure(Type $returnType): bool`
Validates that a type has the expected structure for type narrowing.

**Requirements:**
- Must be an object type (`isObject()->yes()`)
- Must have `getTypes()` method
- Must be `Pipeline\Standard` class
- Must have exactly 2 generic type parameters
- Both parameters must be `Type` instances

#### `extractKeyAndValueTypes(Type $returnType): ?array{Type, Type}`
Extracts key and value types from a valid `Pipeline\Standard<K, V>` type.

**Returns:** `[keyType, valueType]` array or `null` if invalid structure.

## Implementation Details

### Falsy Value Detection

The helper implements precise falsy detection following PHP's truthiness rules:

```php
// Strict mode (removes only null and false)
null     → removed
false    → removed
0        → preserved
0.0      → preserved
''       → preserved
[]       → preserved

// Default mode (removes all falsy values)
null     → removed
false    → removed
0        → removed
0.0      → removed
''       → removed
[]       → removed
```

### Type Safety

- All methods handle edge cases gracefully
- `null` returns indicate "no narrowing possible"
- Method existence is checked before calling (`method_exists`)
- Array bounds are validated before access
- Type instance checks prevent runtime errors

### Performance Considerations

- Uses early returns to avoid unnecessary computation
- Leverages PHPStan's built-in type comparison methods
- Minimal object creation (reuses existing types when possible)
- Efficient array operations with `in_array` strict comparisons

## Testing Strategy

The helper is comprehensively tested with two test classes:

### `FilterTypeNarrowingHelperTest`
Tests the primary public interface and edge cases:
- Type map completeness and correctness
- Callback resolution (string and first-class callable)
- Union type filtering scenarios
- Type construction and validation
- Error conditions and boundary cases

### `FilterTypeNarrowingHelperSystematicTest`
Provides systematic coverage of the complex `removeFalsyValuesFromUnion` logic:
- Each conditional branch is tested independently
- Mock types simulate specific type behaviors
- Achieves 100% mutation testing coverage
- Validates the logic for all falsy value types

## Usage Examples

### Callback Type Resolution
```php
$helper = new FilterTypeNarrowingHelper();

// String callback
$stringCallback = new String_('is_string');
$functionName = $helper->extractFunctionNameFromStringCallback($stringCallback);
// Returns: 'is_string'

$targetType = $helper->getTargetTypeForFunction($functionName);
// Returns: StringType instance
```

### Union Type Narrowing
```php
// Strict mode filtering
$unionType = new UnionType([new StringType(), new NullType(), new FalseType()]);
$filtered = $helper->removeFalsyTypesFromUnion($unionType);
// Returns: [StringType] (removes null and false only)

// Default filtering
$filtered = $helper->removeFalsyValuesFromUnion($unionType);
// Returns: [StringType] (removes all falsy values)
```

### Type Construction
```php
$keyType = new IntegerType();
$filteredTypes = [new StringType(), new IntegerType()];
$result = $helper->createGenericTypeWithFilteredValues($keyType, $filteredTypes);
// Returns: GenericObjectType representing Pipeline\Standard<int, string|int>
```

## Integration Points

### With CallbackResolver
The `CallbackResolver` uses the helper to:
1. Extract function names from different callback syntaxes
2. Map function names to target types
3. Validate that callbacks are supported type-checking functions

### With TypeNarrower
The `TypeNarrower` uses the helper to:
1. Filter union types based on mode (strict vs default vs callback)
2. Create new generic types with narrowed value types
3. Handle edge cases where narrowing results in empty types

### With FilterReturnTypeExtension
The main extension uses the helper to:
1. Validate return type structure before attempting narrowing
2. Extract key and value types from Pipeline generic types
3. Ensure type safety throughout the analysis process

## Error Handling

The helper follows a "graceful degradation" approach:
- Invalid inputs return `null` or empty arrays
- No exceptions are thrown
- Calling code can safely check for `null` returns
- Edge cases are handled with sensible defaults

This design allows the PHPStan extension to fall back to default behavior when type narrowing cannot be applied, ensuring analysis never fails due to helper errors.

## Future Extensibility

The helper is designed for extension:
- New type-checking functions can be added to the type map
- Additional filtering modes can be implemented
- New callback syntaxes can be supported
- The interface remains stable while implementation can evolve

The modular design ensures that adding new features requires minimal changes to existing code, following the Open/Closed Principle.