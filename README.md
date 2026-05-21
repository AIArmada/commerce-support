# AIArmada Commerce Support

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aiarmada/commerce-support.svg?style=flat-square)](https://packagist.org/packages/aiarmada/commerce-support)
[![Total Downloads](https://img.shields.io/packagist/dt/aiarmada/commerce-support.svg?style=flat-square)](https://packagist.org/packages/aiarmada/commerce-support)

Core utilities, contracts, exceptions, and foundation code for all AIArmada Commerce packages.

## Purpose

This package provides shared utilities, traits, and standardized patterns used across all AIArmada Commerce packages. It eliminates code duplication and ensures consistency across the ecosystem.

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.4+ |
| Laravel | 13.0+ |
| akaunting/laravel-money | 6.0+ |
| spatie/laravel-package-tools | 1.92+ |

## Installation

This package is automatically required by all AIArmada Commerce packages. You don't need to install it directly unless building custom extensions:

```bash
composer require aiarmada/commerce-support
```

## Features

- **Exception Hierarchy** - Standardized exceptions for consistent error handling
- **Payment Contracts** - Universal interfaces for payment gateway integrations
- **Configuration Traits** - Runtime configuration validation helpers
- **Helper Functions** - JSONB column type resolver for PostgreSQL support
- **Setup Command** - Interactive wizard for configuring Commerce packages

## Documentation

See the [docs](docs/) folder for detailed documentation:

- [Overview](docs/01-overview.md) - Package capabilities and architecture
- [Configuration](docs/03-configuration.md) - Config keys and defaults
- [Multi-tenancy](docs/04-multi-tenancy.md) - Owner scoping model and safety rules
- [Traits & Utilities](docs/10-traits-utilities.md) - Shared traits and helper utilities
- [Isolation Primitives](docs/11-isolation-primitives.md) - Owner-scoped cache/filesystem/job helpers

## Quick Start

### Exception Handling

```php
use AIArmada\CommerceSupport\Exceptions\CommerceException;

throw new CommerceException(
    message: 'Operation failed',
    errorCode: 'operation_failed',
    errorData: ['context' => 'value']
);
```

### Payment Gateway

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;

class MyGateway implements PaymentGatewayInterface
{
    public function createPayment(
        CheckoutableInterface $checkoutable,
        ?CustomerInterface $customer = null,
        array $options = []
    ): PaymentIntentInterface;
}
```

### Configuration Validation

```php
use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;

class MyServiceProvider extends PackageServiceProvider
{
    use ValidatesConfiguration;

    public function boot(): void
    {
        $this->validateConfiguration('mypackage', ['api_key']);
    }
}
```

## Package Structure

```
commerce-support/
├── composer.json
├── LICENSE
├── README.md
├── docs/
│   ├── 01-overview.md
│   ├── 03-configuration.md
│   ├── 04-multi-tenancy.md
│   ├── 10-traits-utilities.md
│   └── 11-isolation-primitives.md
└── src/
    ├── SupportServiceProvider.php
    ├── helpers.php
    ├── Commands/
    ├── Contracts/
    ├── Exceptions/
    ├── Middleware/
    ├── Support/
    ├── Targeting/
    ├── Testing/
    ├── Traits/
    └── Webhooks/
```

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
