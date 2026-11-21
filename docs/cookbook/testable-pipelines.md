# Building Testable & Maintainable Pipelines

Complex data processing workflows are difficult to test. The Pipeline-Helper Pattern separates the high-level workflow from the implementation details. This separation makes the code easier to maintain and to test.

## The Pattern

The Pipeline-Helper Pattern (an application of the Orchestrator-Implementor pattern) splits the logic into two parts:

1. **The Orchestrator**: Defines *what* happens and in *what order*.
2. **The Helper**: Implements *how* each step operates.

Each part is a small component that you can test on its own.

## Example: Product Import Workflow

This product import system must do these steps:
1. Validate CSV data
2. Normalize values
3. Check SKU format
4. Verify the product doesn't exist in the database
5. Create product entities

The order is important: the system must validate the SKU *before* it queries the database.

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

The helper implements the "how". Each step is a small method:

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

The orchestrator defines the "what" as a pipeline:

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

PHP's first-class callable syntax (`$this->helper->method(...)`) replaces the longer `[$this->helper, 'method']` array syntax. With it, each stage uses a single line, and the pipeline reads like a specification.

## Testing Strategy

### Testing the Helper

You can test each helper method directly:

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

This test is the main benefit of the pattern. It verifies the exact order of operations:

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

        // The importer must NEVER call isNewProduct for invalid SKUs
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

The second test verifies that the importer does not query the database for invalid SKUs. A test of the sequence prevents bugs and unnecessary side effects.

## Benefits

1. **Sequence Contract Enforcement**: Tests guarantee the order of operations. This is important for workflows with side effects.

2. **Separation of Concerns**: The orchestrator is the specification. The helper contains the implementation details.

3. **Testability**:
   - Each helper method has a simple unit test.
   - Mocks test the orchestrator logic.
   - Tests do not require a complex setup.

4. **Maintainability**: Changes to the implementation do not affect the workflow definition, and changes to the workflow do not affect the implementation.

5. **Readability**: The orchestrator documents the business logic.

## When to Use This Pattern

Use the Pipeline-Helper Pattern when:

- Your pipeline has multiple steps with complex logic
- The order of operations is important
- You have side effects (database, API calls, file operations)
- You need granular testing of each step
- The pipeline logic is likely to change

## Advanced Tips

### Composing Multiple Helpers

For large workflows, use multiple specialized helpers:

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

A partial mock replaces some methods and keeps the real implementation of the others:

```php
$helper = $this->getMockBuilder(ProductImportHelper::class)
    ->setConstructorArgs([$realDatabase])
    ->onlyMethods(['isNewProduct'])  // Only mock this method
    ->getMock();

$helper->method('isNewProduct')->willReturn(true);
// Other methods use real implementation
```

## Conclusion

The Pipeline-Helper Pattern divides a large pipeline into small components. The orchestrator defines the "what" and the helper implements the "how", so you can test each part independently.

With PHP's first-class callable syntax, the pipeline reads like a specification and stays fully testable.