# Building Testable & Maintainable Pipelines

For complex data processing, separating the workflow definition from the implementation details makes the code more maintainable and easier to test. This can be achieved with a "Pipeline-Helper" pattern.

## The Pattern

This pattern splits the logic into two parts:

1.  **The Orchestrator**: Defines *what* needs to happen and in *what order*. This is the pipeline itself.
2.  **The Helper**: Implements *how* each step is performed, with each step being a small, focused method.

This separation turns complex logic into clean, testable components.

## Example: Product Import Workflow

Let's build a product import system that must:
1.  Validate CSV data.
2.  Normalize values.
3.  Check SKU format.
4.  Verify the product doesn't exist in the database.
5.  Create product entities.

The order is critical: we must validate the SKU *before* querying the database.

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

The helper contains the implementation of each step.

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

The orchestrator defines the workflow as a clean, readable pipeline.

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
Using PHP's first-class callable syntax (`$this->helper->method(...)`) makes the pipeline expressive and self-documenting.

## Testing Strategy

### Testing the Helper

Each helper method can be unit tested in isolation.

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
    }

    public function testNormalizeData(): void
    {
        $input = ['sku' => '  PROD-12345  ', 'name' => ' Widget ', 'price' => '9.99'];
        $expected = ['sku' => 'PROD-12345', 'name' => 'Widget', 'price' => 9.99];

        $this->assertEquals($expected, $this->helper->normalizeData($input));
    }
}
```

### Testing the Sequence of Operations

With the helper mocked, we can verify the exact order of operations in the orchestrator.

```php
// tests/ProductImporterTest.php
class ProductImporterTest extends TestCase
{
    public function testImportSequenceIsCorrect(): void
    {
        $helper = $this->createMock(ProductImportHelper::class);

        // Define the expected sequence of calls
        $helper->expects($this->once())->method('isCompleteRow')->willReturn(true);
        $helper->expects($this->once())->method('normalizeData')->willReturnArgument(0);
        $helper->expects($this->once())->method('isValidSku')->willReturn(true);
        $helper->expects($this->once())->method('isNewProduct')->willReturn(true);
        $helper->expects($this->once())->method('createProductEntity')->willReturn(new Product('PROD-12345', 'Test', 99.99));

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

        // Verify that methods after the failed validation are never called
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
The second test is crucial: it verifies that we never query the database for an invalid SKU, preventing bugs and unnecessary side effects.

## Benefits

1.  **Sequence Contract**: Guarantees the order of operations, which is critical for workflows with side effects.
2.  **Separation of Concerns**: The orchestrator is a clean specification; the helper contains implementation details.
3.  **Testability**: Helper methods are simple unit tests, and the orchestrator logic is tested via mocks.
4.  **Maintainability**: Changes to implementation don't affect the workflow definition, and vice versa.
5.  **Readability**: The orchestrator becomes self-documenting business logic.

## When to Use This Pattern

Consider this pattern when:
-   Your pipeline has multiple, complex steps.
-   The order of operations is critical.
-   You have side effects (database, API calls, file I/O).
-   You need granular testing of each step.

## Advanced Tips

### Composing Multiple Helpers

For very complex workflows, you can compose multiple specialized helpers.

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

You can test with real implementations of some methods while mocking others.

```php
$helper = $this->getMockBuilder(ProductImportHelper::class)
    ->setConstructorArgs([$realDatabase])
    ->onlyMethods(['isNewProduct'])  // Only mock this method
    ->getMock();

$helper->method('isNewProduct')->willReturn(true);
// Other methods will use their real implementation
```

## Conclusion

The Pipeline-Helper pattern promotes clean, testable, and maintainable code by separating the workflow ("what") from the implementation ("how"). This allows each part to be tested independently while ensuring the overall process is correct.
