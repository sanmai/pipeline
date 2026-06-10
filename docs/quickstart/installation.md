# Installation

## Requirements

- A recent version of PHP (see [composer.json](https://github.com/sanmai/pipeline/blob/main/composer.json) for the current minimum)
- [Composer](https://getcomposer.org/)

## Composer Installation

To add the library to your project, run:

```bash
composer require sanmai/pipeline
```

Composer picks an appropriate version constraint for you. To try the latest development version instead:

```bash
composer require sanmai/pipeline:dev-main
```

## Autoloading

The library follows the PSR-4 autoloading standard. Ensure you include Composer's autoloader in your project's entry point:

```php
require_once 'vendor/autoload.php';
```

## Verifying the Installation

To confirm the library is installed correctly, run this simple script:

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

If you plan to contribute to the library or run its test suite:

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

Static analysis and the rest of the quality checks run with `make analyze`; all checks together run with `make -j -k`.

## Troubleshooting

- **Memory Limit Issues**: If Composer fails due to memory limits, run it with an unlimited memory setting:

    ```bash
    COMPOSER_MEMORY_LIMIT=-1 composer require sanmai/pipeline
    ```

- **Platform Requirements**: If Composer reports an unsatisfiable PHP version requirement, check your PHP version:

    ```bash
    php -v
    ```

- **Composer Version**: If you encounter other issues, ensure Composer is up-to-date:

    ```bash
    composer self-update
    ```

## Next Steps

- [Basic Usage](basic-usage.md)
- [Walkthrough](walkthrough.md)
