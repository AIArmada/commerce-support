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
    | Owner (Multi-tenancy)
    |--------------------------------------------------------------------------
    */
    'owner' => [
        // Class implementing OwnerResolverInterface
        'resolver' => env('COMMERCE_OWNER_RESOLVER', NullOwnerResolver::class),
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

#### `resolver`

The class responsible for resolving the current tenant/owner context.

**Default:** `NullOwnerResolver::class` (disables multi-tenancy)

```php
'owner' => [
    'resolver' => App\Support\TenantOwnerResolver::class,
],
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `COMMERCE_MORPH_KEY_TYPE` | `uuid` | Polymorphic key type |
| `COMMERCE_JSON_COLUMN_TYPE` | `json` | JSON column type (json/jsonb) |
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
