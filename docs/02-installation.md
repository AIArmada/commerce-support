---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 12+
- One of: MySQL 8+, PostgreSQL 13+, SQLite 3.38+

## Composer Installation

```bash
composer require aiarmada/commerce-support
```

The service provider auto-registers via Laravel package discovery.

## Publish Configuration

```bash
php artisan vendor:publish --tag=commerce-support-config
```

This creates `config/commerce-support.php`.

## Environment Variables

```env
# Morph key type for polymorphic relations (uuid, ulid, or int)
COMMERCE_MORPH_KEY_TYPE=uuid

# JSON column type (json or jsonb for PostgreSQL)
COMMERCE_JSON_COLUMN_TYPE=json

# Owner resolver class (for multi-tenancy)
COMMERCE_OWNER_RESOLVER=App\Support\TenantOwnerResolver
```

## Owner Resolver Setup

For multi-tenancy, create an owner resolver:

```php
<?php

namespace App\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;

class TenantOwnerResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        // Option 1: Filament tenancy
        return \Filament\Facades\Filament::getTenant();

        // Option 2: Authenticated user's store
        return auth()->user()?->currentStore;

        // Option 3: Spatie multitenancy
        return \Spatie\Multitenancy\Models\Tenant::current();
    }
}
```

Register in config:

```php
// config/commerce-support.php
'owner' => [
    'resolver' => App\Support\TenantOwnerResolver::class,
],
```

## Verify Installation

```bash
php artisan about
```

Look for "AIArmada Commerce Support" in the output.

## Interactive Setup

For a guided configuration wizard:

```bash
php artisan commerce:setup
```

This prompts for:
- CHIP payment gateway credentials
- J&T Express shipping credentials
- Database JSON column type preferences

## Installing Other Commerce Packages

Use the unified installer for all commerce packages:

```bash
# List available packages
php artisan commerce:install --list

# Install all migrations
php artisan commerce:install --all

# Install specific packages
php artisan commerce:install --tags=cart-migrations,vouchers-migrations

# Include config files
php artisan commerce:install --all --with-config
```
