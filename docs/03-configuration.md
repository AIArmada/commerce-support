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
        'json_column_type' => env('COMMERCE_SUPPORT_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'jsonb')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'default' => env('COMMERCE_DEFAULT_CURRENCY', 'MYR'),
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

Controls the Schema default morph key type for polymorphic relationships.

| Value | Description |
|-------|-------------|
| `uuid` | UUIDs (default, recommended) |
| `ulid` | ULIDs |
| `int` | Auto-incrementing integers |

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
],
```

This is not a replacement for package-level owner flags such as `cart.owner.enabled` or `products.features.owner.enabled`; those flags decide whether individual package models apply owner scopes.

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
| `COMMERCE_DEFAULT_CURRENCY` | `MYR` | Default currency code used by `MoneyNormalizer::format()`, `FormatsMoney`, and `currency_symbol()` |
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
