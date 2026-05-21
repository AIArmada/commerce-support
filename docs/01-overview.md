---
title: Overview
---

# Commerce Support

The foundational package for the AIArmada Commerce ecosystem. Provides shared contracts, traits, utilities, and primitives used across all commerce packages.

## Purpose

Commerce Support serves as the **single source of truth** for:

- **Multi-tenancy** - Owner scoping primitives and enforcement
- **Isolation Primitives** - Cache, filesystem, queue, and middleware helpers for single-database multitenancy
- **Payment Gateway Contracts** - Universal interfaces for any payment provider
- **Targeting Engine** - Advanced rule-based eligibility evaluation
- **Auditing & Logging** - Compliance-grade tracking with Spatie packages
- **Webhook Processing** - Base classes for webhook handling
- **Health Checks** - Service health monitoring
- **Money Normalization** - Consistent currency handling

## Key Dependencies

| Package | Purpose |
|---------|---------|
| `akaunting/laravel-money` | Money objects with currency handling |
| `spatie/laravel-data` | Data Transfer Objects |
| `spatie/laravel-activitylog` | Business event logging |
| `owen-it/laravel-auditing` | Compliance auditing |
| `spatie/laravel-webhook-client` | Webhook processing |
| `spatie/laravel-health` | Health checks |
| `spatie/laravel-settings` | Settings management |
| `lorisleiva/laravel-actions` | Action classes |

## Architecture

```
commerce-support/
├── Contracts/              # Interfaces for cross-package communication
│   ├── Events/             # Event interfaces (Cart, Inventory, Voucher)
│   ├── Payment/            # Payment gateway abstractions
│   └── ...                 # Owner resolver, owner scope, Auditable, Loggable
├── Concerns/               # Shared traits
│   ├── HasCommerceAudit    # Compliance auditing
│   └── LogsCommerceActivity # Activity logging
├── Traits/                 # Model traits
│   ├── HasOwner            # Multi-tenancy support
│   ├── HasOwnerScopeConfig # Config-based scope setup
│   └── ValidatesConfiguration
├── Support/                # Core utilities
│   ├── MoneyNormalizer     # Price normalization
│   ├── OwnerContext        # Tenant context management
│   ├── OwnerCache          # Owner-scoped cache helper
│   ├── OwnerFilesystem     # Owner-scoped storage helper
│   ├── OwnerScope          # Eloquent global scope
│   ├── OwnerScopeKey       # Stable owner scope hashing
│   ├── OwnerQuery          # Query builder helpers
│   ├── OwnerWriteGuard     # Write validation
│   └── OwnerRouteBinding   # Route model binding
├── Middleware/             # Request-time owner identification helpers
├── Targeting/              # Rule evaluation engine
│   ├── TargetingEngine     # Main evaluation engine
│   ├── TargetingContext    # Context object
│   ├── Evaluators/         # 19 built-in evaluators
│   ├── Contracts/          # Evaluator interfaces
│   └── Enums/              # Mode and rule types
├── Webhooks/               # Webhook base classes
├── Health/                 # Health check base
├── Exceptions/             # Shared exceptions
└── Commands/               # Artisan commands
```

## Installation

```bash
composer require aiarmada/commerce-support
```

The service provider auto-registers via Laravel package discovery.

## Quick Start

### Isolation Primitives

```php
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerFilesystem;

$summary = OwnerCache::remember($owner, 'cart.summary', now()->addMinutes(30), function () use ($cart) {
    return $cart->computeSummary();
});

OwnerFilesystem::put($owner, 'exports/orders.csv', $csv);
```

### Multi-tenancy

```php
use AIArmada\CommerceSupport\Traits\HasOwner;

class Product extends Model
{
    use HasOwner;
}

// Query products for current tenant
Product::forOwner($tenant)->get();

// Include global products
Product::forOwner($tenant, includeGlobal: true)->get();

// Global-only products
Product::globalOnly()->get();
```

### Payment Gateway

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;

class ChipGateway implements PaymentGatewayInterface
{
    public function createPayment(
        CheckoutableInterface $cart,
        ?CustomerInterface $customer = null,
        array $options = []
    ): PaymentIntentInterface {
        // Implement gateway-specific logic
    }
}
```

### Targeting Rules

```php
use AIArmada\CommerceSupport\Targeting\TargetingEngine;
use AIArmada\CommerceSupport\Targeting\TargetingContext;

$engine = app(TargetingEngineInterface::class);

$targeting = [
    'mode' => 'all',
    'rules' => [
        ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
        ['type' => 'user_segment', 'operator' => 'in', 'values' => ['vip']],
    ],
];

$context = TargetingContext::fromCart($cart);
$eligible = $engine->evaluate($targeting, $context);
```

## Requirements

- PHP 8.4+
- Laravel 12+
