---
title: Usage
---

# Usage

This page is the canonical entry point for `aiarmada/commerce-support`. Read it first when you need
to choose the right primitive, contract, or helper before diving into the deeper foundation docs.

## 1. Pick the right support surface

- [Multi-tenancy](04-multi-tenancy.md) — owner scoping, explicit global context, route binding, and write guards
- [Payment Contracts](05-payment-contracts.md) — common payment abstractions for gateway packages
- [Targeting Engine](06-targeting-engine.md) — rule evaluation and eligibility checks
- [Auditing & Logging](07-auditing-logging.md) — shared business logging and compliance auditing
- [Webhooks](08-webhooks.md) — base webhook validation and processing patterns
- [Health Checks](09-health-checks.md) — health-reporting primitives and widget support
- [Traits & Utilities](10-traits-utilities.md) — reusable traits, helpers, and common support utilities
- [Isolation Primitives](11-isolation-primitives.md) — owner-scoped cache, filesystem, queue, and middleware helpers
- [Actions](12-actions.md) — reusable action classes for owner-safe and package-safe orchestration

## 2. Use the owner-safety primitives by default

When a package stores owner-aware data, start with the owner primitives here instead of inventing a
package-local pattern:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;

OwnerContext::withOwner($owner, function () use ($payload): void {
    OwnerWriteGuard::findOrFailForOwner(Location::class, $payload['location_id']);
});
```

## 3. Reuse shared helpers instead of hand-rolling infrastructure

```php
use AIArmada\CommerceSupport\Support\MoneyNormalizer;
use AIArmada\CommerceSupport\Support\OwnerCache;

$amount = MoneyNormalizer::toMinorUnits('99.90', 'MYR');

$summary = OwnerCache::remember($owner, 'cart.summary', now()->addMinutes(30), function () use ($cart) {
    return $cart->computeSummary();
});
```

`MoneyNormalizer::format()` now defaults to `MYR` when you omit the currency code. Pass an explicit code whenever a downstream package or UI contract needs a different currency.

## 4. Treat the deep docs as task guides

Use the linked pages above as task-focused guides. `commerce-support` is intentionally broad; the
canonical usage path here helps AI and humans decide which foundation document to read next.