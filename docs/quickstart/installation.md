# Installation

## Requirements

- PHP 8.2 or higher
- Composer package manager

## Installation via Composer

```bash
composer require sanmai/pipeline
```

## Version Constraints

### Latest Stable Version
```json
{
    "require": {
        "sanmai/pipeline": "^6.0"
    }
}
```

### Specific Version
```json
{
    "require": {
        "sanmai/pipeline": "6.11.0"
    }
}
```

### Development Version
```bash
composer require sanmai/pipeline:dev-main
```

## Autoloading

The library uses PSR-4 autoloading. After installation, include Composer's autoloader:

```php
require_once 'vendor/autoload.php';
```

## Basic Verification

Verify installation with a simple test:

```php
<?php
require_once 'vendor/autoload.php';

use function Pipeline\take;

$result = take([1, 2, 3, 4, 5])
    ->map(fn($x) => $x * 2)
    ->toList();

print_r($result); // Output: [2, 4, 6, 8, 10]
```

## Importing Functions

The library provides helper functions in the `Pipeline` namespace:

```php
// Import individual functions
use function Pipeline\take;
use function Pipeline\map;
use function Pipeline\fromArray;
use function Pipeline\zip;

// Or use fully qualified names
$pipeline = \Pipeline\take($data);
```

## Class Imports

For direct class usage:

```php
use Pipeline\Standard;
use Pipeline\Helper\RunningVariance;

$pipeline = new Standard($data);
$variance = new RunningVariance();
```

## Development Installation

For contributing or running tests:

```bash
# Clone repository
git clone https://github.com/sanmai/pipeline.git
cd pipeline

# Install dependencies including dev dependencies
composer install

# Run tests
make test
```

## Static Analysis Tools

For development with static analysis:

```bash
# Install analysis tools
composer require --dev phpstan/phpstan psalm/phar phan/phan

# Run analysis
make analyze
```

## Common Installation Issues

### Memory Limit
If installation fails due to memory limits:
```bash
COMPOSER_MEMORY_LIMIT=-1 composer require sanmai/pipeline
```

### Platform Requirements
Ensure PHP version compatibility:
```bash
php -v  # Should show PHP 8.2.0 or higher
```

### Composer Version
Update Composer if needed:
```bash
composer self-update
```

## Next Steps

- [Basic Usage](basic-usage.md) - Start using the library
- [Examples](examples.md) - See pipeline patterns in action