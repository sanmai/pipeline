# Building Testable & Maintainable Pipelines

When building complex data processing workflows, testing can quickly become a nightmare. The **Pipeline-Helper Pattern** solves this by separating your high-level workflow from implementation details, making your code both more maintainable and incredibly easy to test.

## The Pattern

The Pipeline-Helper Pattern (an application of the Orchestrator-Implementor pattern) splits your logic into two parts:

1. **The Orchestrator**: Defines *what* needs to happen and in *what order*
2. **The Helper**: Implements *how* each step is performed

This separation transforms complex, hard-to-test logic into clean, testable components.

## Example: Product Import Workflow

Let's build a product import system that must:
1. Validate CSV data
2. Normalize values
3. Check SKU format
4. Verify the product doesn't exist in the database
5. Create product entities

The order is critical - we must validate the SKU *before* hitting the database.

### The Data Model

```php
// src/Product.php
final class Product
{
    public function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly float $price
    ) {}
}
```

### The Helper: Implementation Details

The helper contains all the "how" - each step as a small, focused method:

```php
// src/ProductImportHelper.php
class ProductImportHelper
{
    public function __construct(private readonly DatabaseConnection $db) {}

    public function isCompleteRow(array $row): bool
    {
        return isset($row['sku'], $row['name'], $row['price']);
    }

    public function normalizeData(array $row): array
    {
        $row['sku'] = trim($row['sku']);
        $row['name'] = trim($row['name']);
        $row['price'] = (float) $row['price'];
        return $row;
    }

    public function isValidSku(array $row): bool
    {
        // SKUs must be "PROD-12345" format
        return (bool) preg_match('/^PROD-\d{5}$/', $row['sku']);
    }

    public function isNewProduct(array $row): bool
    {
        // SIDE EFFECT: Database query
        return !$this->db->productExists($row['sku']);
    }

    public function createProductEntity(array $row): Product
    {
        return new Product($row['sku'], $row['name'], $row['price']);
    }
}
```

### The Orchestrator: The Workflow

The orchestrator defines the "what" - a clean, readable pipeline:

```php
// src/ProductImporter.php
use function Pipeline\take;

class ProductImporter
{
    public function __construct(private readonly ProductImportHelper $helper) {}

    public function import(iterable $csvRows): iterable
    {
        return take($csvRows)
            ->filter($this->helper->isCompleteRow(...))
            ->map($this->helper->normalizeData(...))
            ->filter($this->helper->isValidSku(...))
            ->filter($this->helper->isNewProduct(...))  // Must come AFTER validation!
            ->map($this->helper->createProductEntity(...));
    }
}
```

Notice how PHP's first-class callable syntax (`$this->helper->method(...)`, which replaces the more verbose `[$this->helper, 'method']` array syntax) makes this incredibly expressive. The pipeline reads like a specification.

## Testing Strategy

### Testing the Helper

Each helper method is trivially testable:

```php
// tests/ProductImportHelperTest.php
class ProductImportHelperTest extends TestCase
{
    private ProductImportHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new ProductImportHelper($this->createMock(DatabaseConnection::class));
    }

    public function testIsValidSku(): void
    {
        $this->assertTrue($this->helper->isValidSku(['sku' => 'PROD-12345']));
        $this->assertFalse($this->helper->isValidSku(['sku' => 'INVALID']));
        $this->assertFalse($this->helper->isValidSku(['sku' => 'PROD-123']));  // Too short
    }

    public function testNormalizeData(): void
    {
        $input = ['sku' => '  PROD-12345  ', 'name' => ' Widget ', 'price' => '9.99'];
        $expected = ['sku' => 'PROD-12345', 'name' => 'Widget', 'price' => 9.99];

        $this->assertEquals($expected, $this->helper->normalizeData($input));
    }
}
```

### Testing the Sequence Contract

This is where the pattern truly shines. We can verify the exact order of operations:

```php
// tests/ProductImporterTest.php
class ProductImporterTest extends TestCase
{
    public function testImportSequenceIsCorrect(): void
    {
        $helper = $this->createMock(ProductImportHelper::class);

        // Define the EXACT sequence we expect
        $helper->expects($this->once())
            ->method('isCompleteRow')
            ->willReturn(true);

        $helper->expects($this->once())
            ->method('normalizeData')
            ->willReturnArgument(0);

        $helper->expects($this->once())
            ->method('isValidSku')
            ->willReturn(true);

        $helper->expects($this->once())
            ->method('isNewProduct')
            ->willReturn(true);

        $helper->expects($this->once())
            ->method('createProductEntity')
            ->willReturn(new Product('PROD-12345', 'Test', 99.99));

        $importer = new ProductImporter($helper);

        // Execute the pipeline
        $results = iterator_to_array($importer->import([
            ['sku' => 'PROD-12345', 'name' => 'Test Product', 'price' => '99.99']
        ]));

        $this->assertCount(1, $results);
    }

    public function testSkipsInvalidSku(): void
    {
        $helper = $this->createMock(ProductImportHelper::class);

        $helper->expects($this->once())->method('isCompleteRow')->willReturn(true);
        $helper->expects($this->once())->method('normalizeData')->willReturnArgument(0);
        $helper->expects($this->once())->method('isValidSku')->willReturn(false);

        // This is the key: isNewProduct should NEVER be called for invalid SKUs
        $helper->expects($this->never())->method('isNewProduct');
        $helper->expects($this->never())->method('createProductEntity');

        $importer = new ProductImporter($helper);

        $results = iterator_to_array($importer->import([
            ['sku' => 'INVALID', 'name' => 'Test', 'price' => '99.99']
        ]));

        $this->assertEmpty($results);
    }
}
```

The second test is crucial - it verifies that we never hit the database for invalid SKUs. This sequence enforcement prevents bugs and unnecessary side effects.

## Benefits

1. **Sequence Contract Enforcement**: Test and guarantee the order of operations, critical for workflows with side effects.

2. **Separation of Concerns**: The orchestrator is a clean specification; the helper contains implementation details.

3. **Exceptional Testability**:
   - Helper methods are simple unit tests
   - Orchestrator logic is tested via mocks
   - No complex test setup required

4. **Maintainability**: Changes to implementation don't affect the workflow definition, and vice versa.

5. **Readability**: The orchestrator becomes self-documenting business logic.

## When to Use This Pattern

Consider the Pipeline-Helper Pattern when:

- Your pipeline has multiple steps with complex logic
- The order of operations is critical
- You have side effects (database, API calls, file operations)
- You need granular testing of each step
- The pipeline logic is likely to evolve

## Advanced Tips

### Composing Multiple Helpers

For very complex workflows, you can use multiple specialized helpers:

```php
class OrderProcessor
{
    public function __construct(
        private readonly ValidationHelper $validator,
        private readonly PricingHelper $pricing,
        private readonly InventoryHelper $inventory
    ) {}

    public function process(iterable $orders): iterable
    {
        return take($orders)
            ->filter($this->validator->isValid(...))
            ->map($this->pricing->calculateTotals(...))
            ->filter($this->inventory->isInStock(...))
            ->map($this->createOrder(...));
    }
}
```

### Testing with Partial Mocks

Sometimes you want to test with real implementations of some methods:

```php
$helper = $this->getMockBuilder(ProductImportHelper::class)
    ->setConstructorArgs([$realDatabase])
    ->onlyMethods(['isNewProduct'])  // Only mock this method
    ->getMock();

$helper->method('isNewProduct')->willReturn(true);
// Other methods use real implementation
```

## Conclusion

The Pipeline-Helper Pattern transforms complex, monolithic pipelines into clean, testable components. By separating the "what" from the "how", you gain the ability to test each concern independently while maintaining readable, maintainable code.

The pattern is particularly powerful when combined with PHP's first-class callable syntax, creating pipelines that read like specifications while remaining fully testable.