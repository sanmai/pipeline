# Installation

## Requirements

- A recent version of PHP (see [composer.json](https://github.com/sanmai/pipeline/blob/main/composer.json) for the current minimum)
- [Composer](https://getcomposer.org/)

## Composer Installation

To add the library to your project, run:

```bash
composer require sanmai/pipeline
```

Composer adds a version constraint to `composer.json`. To install the latest development version instead, run:

```bash
composer require sanmai/pipeline:dev-main
```

## Autoloading

The library uses PSR-4 autoloading. Include the Composer autoloader in the entry point of your project:

```php
require_once 'vendor/autoload.php';
```

## Verifying the Installation

To verify the installation, run this script:

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

The helper functions are in the `Pipeline` namespace. Import them individually, or use their fully qualified names.

```php
// Import individual functions
use function Pipeline\take;
use function Pipeline\map;

// Or use fully qualified names
$pipeline = \Pipeline\take($data);
```

To use the class directly, import it:

```php
use Pipeline\Standard;

$pipeline = new Standard($data);
```

## Development Setup

To contribute to the library or run its test suite, do these steps:

1. **Clone the repository:**

    ```bash
    git clone https://github.com/sanmai/pipeline.git
    cd pipeline
    ```

2. **Install dependencies (including dev-dependencies):**

    ```bash
    composer install
    ```

3. **Run the test suite:**

    ```bash
    make test
    ```

`make analyze` runs static analysis (PHPStan and Psalm). `make -j -k` runs all checks in parallel.

## Troubleshooting

- **Memory Limit Issues**: If Composer stops with a memory limit error, run it without a memory limit:

    ```bash
    COMPOSER_MEMORY_LIMIT=-1 composer require sanmai/pipeline
    ```

- **Platform Requirements**: If Composer reports an unsatisfiable PHP version requirement, check your PHP version:

    ```bash
    php -v
    ```

- **Composer Version**: For other errors, update Composer to the latest version:

    ```bash
    composer self-update
    ```

## Next Steps

- [Basic Usage](basic-usage.md)
- [Walkthrough](walkthrough.md)
