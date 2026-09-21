---
title: Configuration
---

# Configuration

## Configuration File

After publishing, `config/commerce-support.php` contains:

```php
<?php

use AIArmada\CommerceSupport\Support\NullOwnerResolver;

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        // Morph key type: 'uuid', 'ulid', or 'int'
        'morph_key_type' => env('COMMERCE_MORPH_KEY_TYPE', 'uuid'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'default' => env('COMMERCE_DEFAULT_CURRENCY', 'MYR'),
        'exchange_rates' => [
            'base' => env('COMMERCE_EXCHANGE_RATE_BASE', 'USD'),
            'rates' => [
                // Units per one base unit: 1 USD = 4.70 MYR.
                // 'MYR' => 4.7,
            ],
            'history' => [
                // Dated snapshots for historical reporting, keyed by Y-m-d.
                // '2026-01-01' => ['MYR' => 4.2],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'owner' => [
        // Global safety switch for owner resolver enforcement
        'enabled' => env('COMMERCE_OWNER_ENABLED', false),

        // Class implementing OwnerResolverInterface
        'resolver' => env('COMMERCE_OWNER_RESOLVER', NullOwnerResolver::class),
    ],

    'health' => [
        // Gate ability required to view CommerceHealthWidget
        'view_ability' => 'viewCommerceHealth',
    ],

    'filament' => [
        'navigation' => [
            'enabled' => true,
            'groups' => [],
            'packages' => [],
            'items' => [],
        ],
    ],
];
```

## Configuration Options

### Database Settings

#### `morph_key_type`

Controls the Schema default morph key type for polymorphic relationships. It applies only to owner morphs declared with the guideline-literal `nullableMorphs('owner')`; migrations that declare an explicit `nullableUuidMorphs('owner')` ignore this setting, as do non-owner holder morphs with explicit string or uuid columns (seating hosts and holders, pass registrations), which stay independent so they keep accepting both integer-like and uuid keys whatever the owner key shape is.

| Value | Description |
|-------|-------------|
| `uuid` | UUIDs (default, recommended) |
| `ulid` | ULIDs |
| `int` | Auto-incrementing integers (unsupported — see below) |

> **Warning**: `morph_key_type=int` is effectively unsupported. Only some owner migrations use `nullableMorphs('owner')`; the rest pin `nullableUuidMorphs('owner')`, so `int` produces a mixed schema (bigint owner columns next to uuid ones) and uuid-keyed owners become unwritable on strict drivers. Use uuid (or ulid) owner keys. Apps that must keep integer owners should plan a migration to uuid keys instead of setting `int`.

```php
'database' => [
    'morph_key_type' => 'uuid',
],
```

### Owner Settings

#### `enabled`

Global safety switch for owner-aware applications.

**Default:** `false`

When `true`, Commerce Support fails closed during boot if `OwnerResolverInterface` resolves to `NullOwnerResolver`. This prevents an application from enabling owner mode while silently running without tenant isolation.

```php
'owner' => [
    'enabled' => true,
    'resolver' => App\Support\TenantOwnerResolver::class,
    'team_type' => App\Models\Team::class,
],
```

This is not a replacement for package-level owner flags such as `cart.owner.enabled` or `products.features.owner.enabled`; those flags decide whether individual package models apply owner scopes.

#### `team_type`

Optional morph type used by team-aware integrations when the owner is represented by a dedicated team model. Leave it `null` unless the application needs an explicit team morph class.

**Default:** `null`

### Currency Settings

#### `exchange_rates`

Static exchange rates for reporting-only conversion. Rates are units per one base unit, so with base `USD`, `['MYR' => 4.7]` prices one USD at 4.70 MYR. The base implies `1.0` when unlisted; unknown, zero, and negative rates resolve to null so callers fail soft.

```php
'currency' => [
    'default' => 'MYR',
    'exchange_rates' => [
        'base' => 'USD',
        'rates' => ['MYR' => 4.7, 'EUR' => 0.92],
        'history' => ['2026-01-01' => ['MYR' => 4.2]],
    ],
],
```

Pass `$asOf` to `rate()`, `convertMinor()`, or `totalMinor()` to convert with the rates effective at that moment: every history snapshot on or before the date overlays the current table, oldest first, so partial snapshots compose. Reports pass the period end automatically, and conversions stamp the effective rate at record time so history never shifts when current rates move.

To feed rates from a database table or a live provider, bind your own `AIArmada\CommerceSupport\Contracts\ExchangeRateProvider` singleton — `CurrencyConverter` resolves it from the container. Custom providers must honor `$asOf` the same way.

The default provider reads the `commerce-exchange-rates` settings group first and falls back to this config when settings are unmigrated, so config-driven deployments keep working unchanged. Manage settings at runtime through the filament-commerce-support Exchange Rates page: edit the base and current rates, and snapshot them to append a dated history entry for historical reporting.

### Targeting Settings

#### `trust_proxy_headers`

Whether proxy/CDN-style request headers (`X-Channel`, `CF-IPCountry`,
`X-Region`, `X-City`, `X-Timezone`, ...) may steer targeting channel, geo,
and timezone resolution.

**Default:** `false`

Keep this disabled unless the deployment guarantees these headers originate
from trusted infrastructure. Shoppers can otherwise set them directly and
steer discount-gating rules. Prefer passing server-resolved values through
targeting `metadata`. See [Targeting Engine](./06-targeting-engine.md).

```php
'targeting' => [
    'trust_proxy_headers' => false,
],
```

### Filesystem Settings

#### `disk`

Optional disk name used by `OwnerFilesystem` for all owner-scoped file
operations. Set it to a private disk to guarantee tenant files never land
on a public default disk.

**Default:** `null` (uses the application default disk)

```php
'filesystem' => [
    'disk' => 'local',
],
```

#### `resolver`

The class responsible for resolving the current tenant/owner context.

**Default:** `NullOwnerResolver::class` (single-tenant/no-owner mode)

```php
'owner' => [
    'resolver' => App\Support\TenantOwnerResolver::class,
],
```

### Health Settings

#### `view_ability`

Gate ability required by `CommerceHealthWidget::canView()`.

**Default:** `viewCommerceHealth`

```php
'health' => [
    'view_ability' => 'viewCommerceHealth',
],
```

Define the ability in your application's authorization layer:

```php
Gate::define('viewCommerceHealth', fn (User $user): bool => $user->isAdmin());
```

### Filament Navigation Settings

#### `enabled`

Enables the central Commerce navigation builder when `CommerceNavigationPlugin` is registered on a panel.

**Default:** `true`

#### `groups`

Defines the preferred group order and group presentation.

```php
'filament' => [
    'navigation' => [
        'groups' => [
            'Catalog' => ['label' => 'Catalog', 'sort' => 10],
            'Sales' => ['label' => 'Sales', 'sort' => 20],
            'Operations' => ['label' => 'Operations', 'sort' => 30, 'collapsed' => true],
        ],
    ],
],
```

#### `packages`

Sets defaults for all navigation items from a Commerce Filament package. Package-specific item keys can hide individual menu entries or override their group/sort.

```php
'filament' => [
    'navigation' => [
        'packages' => [
            'filament-products' => [
                'group' => 'Catalog',
                'items' => [
                    'products' => ['sort' => 10],
                    'attributes' => ['visible' => false],
                ],
            ],
            'filament-orders' => [
                'group' => 'Sales',
            ],
        ],
    ],
],
```

#### `items`

Overrides a single resource or page by class string. This is the most precise option and works even when a package item key is not obvious.

```php
'filament' => [
    'navigation' => [
        'items' => [
            AIArmada\FilamentProducts\Resources\AttributeResource::class => [
                'visible' => false,
            ],
            AIArmada\FilamentGrowth\Pages\GrowthDashboard::class => [
                'group' => 'Insights',
                'sort' => 5,
            ],
        ],
    ],
],
```

Supported item keys are `visible`, `hidden`, `group`, `parent_item`, and `sort`. Hiding navigation does not authorize or block direct URL access; use policies and owner scoping for security.

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `COMMERCE_MORPH_KEY_TYPE` | `uuid` | Polymorphic key type |
| `COMMERCE_SUPPORT_JSON_COLUMN_TYPE` | `jsonb` | Package-specific JSON column type override for `commerce-support` |
| `COMMERCE_JSON_COLUMN_TYPE` | `jsonb` | Shared fallback JSON column type used across commerce packages |
| `COMMERCE_SUPPORT_TABLE_LANGUAGES` | `languages` | Shared language reference table |
| `COMMERCE_SUPPORT_TABLE_CURRENCIES` | `currencies` | Shared currency reference table |
| `COMMERCE_SUPPORT_TABLE_TIMEZONES` | `timezones` | Shared timezone reference table |
| `COMMERCE_DEFAULT_CURRENCY` | `MYR` | Default currency code used by `MoneyNormalizer::format()`, `FormatsMoney`, and `currency_symbol()` |
| `COMMERCE_EXCHANGE_RATE_BASE` | `USD` | Reporting base currency for static exchange rates |
| `COMMERCE_OWNER_ENABLED` | `false` | Fail closed unless a concrete owner resolver is configured |
| `COMMERCE_OWNER_RESOLVER` | `NullOwnerResolver::class` | Owner resolver class |

## JSON Column Type Helper

Use the global helper for consistent JSON column types:

```php
// In migrations
$table->addColumn(
    commerce_json_column_type('cart'), // Uses CART_JSON_COLUMN_TYPE or COMMERCE_JSON_COLUMN_TYPE
    'items'
);

// Package-specific override
// Set CART_JSON_COLUMN_TYPE=jsonb for just the cart package
// Set COMMERCE_JSON_COLUMN_TYPE=jsonb for all packages
```

## Per-Package Configuration

Each commerce package can define its own owner scope configuration:

```php
// In package config (e.g., config/cart.php)
'owner' => [
    'enabled' => env('CART_OWNER_ENABLED', false),
    'include_global' => env('CART_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('CART_OWNER_AUTO_ASSIGN_ON_CREATE', true),
],
```

Models use `HasOwnerScopeConfig` to read from their package's config:

```php
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;

class CartModel extends Model
{
    use HasOwner, HasOwnerScopeConfig;

    protected static string $ownerScopeConfigKey = 'cart.owner';
    protected static bool $ownerScopeEnabledByDefault = false;
    protected static bool $ownerScopeIncludeGlobalByDefault = false;
}
```
