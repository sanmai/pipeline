# Installation

## Requirements

- PHP 8.2 or higher
- [Composer](https://getcomposer.org/)

## Composer Installation

To add the library to your project, run the following Composer command:

```bash
composer require sanmai/pipeline
```

### Version Constraints

It is recommended to use a version constraint to ensure your project remains compatible with future releases.

**Latest Stable Version (Recommended)**

```json
{
    "require": {
        "sanmai/pipeline": "^6.0"
    }
}
```

**Specific Version**

```json
{
    "require": {
        "sanmai/pipeline": "6.11.0"
    }
}
```

**Development Version**

To use the latest development version, you can require the `dev-main` branch:

```bash
composer require sanmai/pipeline:dev-main
```

## Autoloading

The library follows the PSR-4 autoloading standard. Ensure you include Composer's autoloader in your project's entry point:

```php
require_once 'vendor/autoload.php';
```

## Verifying the Installation

To confirm the library is installed correctly, you can run this simple script:

```php
<?php
require_once 'vendor/autoload.php';

use function Pipeline\take;

$result = take([1, 2, 3, 4, 5])
    ->map(fn($x) => $x * 2)
    ->toList();

print_r($result); // Expected output: [2, 4, 6, 8, 10]
```

## Importing Functions and Classes

The library provides helper functions in the `Pipeline` namespace. You can import them individually or use their fully qualified names.

```php
// Import individual functions
use function Pipeline\take;
use function Pipeline\map;

// Or use fully qualified names
$pipeline = \Pipeline\take($data);
```

For direct class usage, import the necessary classes:

```php
use Pipeline\Standard;

$pipeline = new Standard($data);
```

## Development Setup

If you plan to contribute to the library or run its test suite, follow these steps:

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/sanmai/pipeline.git
    cd pipeline
    ```

2.  **Install dependencies (including dev-dependencies):**
    ```bash
    composer install
    ```

3.  **Run the test suite:**
    ```bash
    make test
    ```

## Static Analysis

To run static analysis tools like PHPStan and Psalm, first ensure they are installed, then run the analysis command:

```bash
composer require --dev phpstan/phpstan vimeo/psalm
make analyze
```

## Troubleshooting

-   **Memory Limit Issues**: If Composer fails due to memory limits, you can run it with an unlimited memory setting:
    ```bash
    COMPOSER_MEMORY_LIMIT=-1 composer require sanmai/pipeline
    ```

-   **Platform Requirements**: Ensure your environment meets the minimum PHP version requirement:
    ```bash
    php -v // Must be 8.2.0 or higher
    ```

-   **Composer Version**: If you encounter issues, ensure Composer is up-to-date:
    ```bash
    composer self-update
    ```

## Next Steps

-   [Basic Usage](basic-usage.md)
-   [Examples](examples.md)
