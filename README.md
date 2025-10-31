# Catalog Bundle

[English](README.md) | [中文](README.zh-CN.md)

A Symfony bundle for managing catalog and classification functionality.

## Installation

Add this bundle to your Symfony project:

```bash
composer require tourze/catalog-bundle
```

## Quick Start

1. Register the bundle in your `config/bundles.php`:

```php
<?php

return [
    // ... other bundles
    Tourze\CatalogBundle\CatalogBundle::class => ['all' => true],
];
```

2. The bundle provides basic catalog management infrastructure for your Symfony application.

## Features

- Basic catalog bundle structure
- Symfony 6.4+ compatibility
- PHP 8.1+ support
- Doctrine ORM integration
- Timestamp tracking support

## Configuration

The bundle uses default Symfony configuration. Services are automatically registered with autowiring enabled.

## Usage

This bundle provides the foundation for catalog-related functionality in your Symfony application. It includes:

- Bundle class with dependency management
- Extension for service configuration
- Basic service configuration

```php
// Example usage in your application
use Tourze\CatalogBundle\CatalogBundle;

// The bundle is automatically registered and configured
```

## Testing

Run the test suite:

```bash
./vendor/bin/phpunit packages/catalog-bundle/tests
```

## Dependencies

- PHP 8.1+
- Symfony 6.4+
- Doctrine ORM 3.0+
- tourze/doctrine-timestamp-bundle

## License

This bundle is released under the MIT License. See the [LICENSE](LICENSE) file for details.