---
title: Overview
---

# Commerce Support

The foundational package for the AIArmada Commerce ecosystem. It provides the shared contracts, traits, utilities, and safety primitives used across Commerce packages.

## Purpose

Commerce Support serves as the **single source of truth** for the cross-package seams the rest of the monorepo depends on.

## What this package owns

- Owner scoping primitives and explicit global-context semantics
- Isolation helpers for cache, filesystem, queue, and request middleware
- Payment gateway and checkout contracts shared by payment-facing packages
- The targeting engine, evaluator contracts, and shared rule infrastructure
- Base webhook validators/processors and health-check abstractions
- Shared auditing, logging, money, install, and testing utilities

## What this package does not own

- Domain business rules for packages like `cart`, `checkout`, `orders`, `pricing`, or `vouchers`
- Filament resources, pages, widgets, or panel-specific UI behavior
- Concrete payment gateway implementations such as `chip` or `cashier-chip`
- Package-specific models, tables, or config outside the shared primitives it exposes
- Full SaaS tenant provisioning, domain routing, or multi-database tenancy orchestration

## Related packages

- Every Commerce domain package depends on `commerce-support` directly or indirectly for shared primitives
- `filament-*` packages consume its owner-scoping and helper conventions, but do not replace them
- Payment packages such as `chip`, `cashier`, and `cashier-chip` build on its contracts and money helpers
- Root guides and AI retrieval docs explain how its tenancy model fits the wider ecosystem

## Main contracts services or surfaces

Commerce Support provides these major surfaces:

- **Multi-tenancy** - Owner scoping primitives and enforcement
- **Isolation Primitives** - Cache, filesystem, queue, and middleware helpers for single-database multitenancy
- **Payment Gateway Contracts** - Universal interfaces for any payment provider
- **Targeting Engine** - Advanced rule-based eligibility evaluation
- **Auditing & Logging** - Compliance-grade tracking with Spatie packages
- **Webhook Processing** - Base classes for webhook handling
- **Health Checks** - Service health monitoring
- **Money Normalization** - Consistent currency handling

## Key dependencies

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

## Owner scoping and security notes

- The owner tuple is security-sensitive. Missing owner context is not the same as explicit global access.
- Eloquent owner safety comes from `HasOwner`, `OwnerScope`, and explicit helpers like `forOwner()` and `globalOnly()`.
- Raw query builder paths must use `OwnerQuery::applyToQueryBuilder()` because `DB::table()` bypasses Eloquent scopes.
- Write paths should revalidate submitted IDs with `OwnerWriteGuard`.
- Route model binding should use `OwnerRouteBinding` or another owner-safe resolution path.
- Non-request surfaces such as jobs, commands, exports, and webhooks should use `OwnerContext::withOwner(...)` explicitly.

## Quick start

```bash
composer require aiarmada/commerce-support
```

The service provider auto-registers via Laravel package discovery. After installation, read the package-specific guides for configuration, owner scoping, and the shared helper surfaces you plan to use.

### Isolation primitives

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

### Payment gateway contracts

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

### Targeting rules

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

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Multi-tenancy](04-multi-tenancy.md)
- [Payment Contracts](05-payment-contracts.md)
- [Targeting Engine](06-targeting-engine.md)
- [Webhooks](08-webhooks.md)
- [Health Checks](09-health-checks.md)
- [Traits & Utilities](10-traits-utilities.md)
- [Isolation Primitives](11-isolation-primitives.md)
- [Troubleshooting](99-troubleshooting.md)
