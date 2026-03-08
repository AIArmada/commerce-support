---
title: Multi-tenancy
---

# Multi-tenancy

Commerce Support provides a comprehensive multi-tenancy system based on owner scoping. This allows all commerce packages to isolate data by tenant (merchant, store, organization, etc.).

## Core Concepts

### Owner vs Tenant

In this system, "owner" is the polymorphic relationship that represents your tenant. This could be:
- A `Merchant` model
- A `Store` model
- A `Team` model
- An `Organization` model
- Any Eloquent model you choose

### Data Isolation

| Record Type | `owner_type` | `owner_id` | Behavior |
|-------------|--------------|------------|----------|
| Tenant-owned | `App\Models\Store` | `store-uuid` | Only visible to that store |
| Global | `null` | `null` | Visible to all tenants (when `include_global` enabled) |

## Setting Up Multi-tenancy

### 1. Create an Owner Resolver

```php
<?php

namespace App\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;

class TenantOwnerResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        // Example: Using Filament panel tenancy
        return \Filament\Facades\Filament::getTenant();
    }
}
```

### 2. Register the Resolver

```php
// config/commerce-support.php
'owner' => [
    'resolver' => App\Support\TenantOwnerResolver::class,
],
```

### 3. Enable in Package Configs

```php
// config/cart.php
'owner' => [
    'enabled' => true,
    'include_global' => false,
],
```

## Using HasOwner Trait

Add the trait to models that need tenant isolation:

```php
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;

class Product extends Model
{
    use HasOwner, HasOwnerScopeConfig;

    protected static string $ownerScopeConfigKey = 'products.owner';
}
```

### Required Migration Columns

```php
Schema::create('products', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->nullableMorphs('owner'); // Creates owner_type and owner_id
    // ... other columns
});
```

## Query Scopes

### Automatic Scoping (Global Scope)

When `enabled` is true, the `OwnerScope` global scope automatically filters all queries:

```php
// Automatically scoped to current owner
Product::all();

// Equivalent to:
Product::where('owner_type', $owner->getMorphClass())
    ->where('owner_id', $owner->getKey())
    ->get();
```

### Manual Scoping

```php
// Scope to specific owner
Product::forOwner($store)->get();

// Include global (ownerless) records
Product::forOwner($store, includeGlobal: true)->get();

// Global-only records
Product::globalOnly()->get();

// Bypass owner scope entirely (dangerous!)
Product::withoutOwnerScope()->get();
```

## Owner Context Management

### Resolve Current Owner

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

$owner = OwnerContext::resolve();
```

### Override Context Temporarily

```php
// Override for a callback
$result = OwnerContext::withOwner($differentOwner, function () {
    return Product::all(); // Scoped to $differentOwner
});

// Manual override (careful - persistent!)
OwnerContext::override($owner);
// ... operations ...
OwnerContext::clearOverride();
```

### Reconstruct Owner from Columns

```php
$owner = OwnerContext::fromTypeAndId(
    $row->owner_type,
    $row->owner_id
);
```

## Write Protection

### OwnerWriteGuard

Validates that records belong to the current owner before updates:

```php
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;

// Throws AuthorizationException if not accessible
$product = OwnerWriteGuard::findOrFailForOwner(
    Product::class,
    $productId,
    owner: OwnerContext::CURRENT, // Use current resolved owner
    includeGlobal: false
);
```

### Route Model Binding

Secure route model binding with owner validation:

```php
use AIArmada\CommerceSupport\Support\OwnerRouteBinding;

// In RouteServiceProvider or routes
OwnerRouteBinding::bind('product', Product::class);

// Now Route::get('/products/{product}') will validate owner
```

## Query Builder Support

For `DB::table()` queries (where Eloquent global scopes don't apply):

```php
use AIArmada\CommerceSupport\Support\OwnerQuery;

// Eloquent Builder
OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal);

// Query Builder
OwnerQuery::applyToQueryBuilder(
    DB::table('products'),
    $owner,
    includeGlobal: false
);
```

## Model Methods

The `HasOwner` trait provides:

```php
// Check ownership
$product->hasOwner();              // Has any owner
$product->isGlobal();              // No owner (global record)
$product->belongsToOwner($store);  // Belongs to specific owner

// Modify ownership
$product->assignOwner($store);     // Set owner
$product->removeOwner();           // Make global

// Display
$product->owner_display_name;      // Human-readable owner name
```

## Non-Request Contexts

For jobs, commands, and scheduled tasks that don't have HTTP context:

```php
// In a job
public function handle(): void
{
    OwnerContext::withOwner($this->owner, function () {
        // All queries scoped to $this->owner
        $products = Product::all();
    });
}

// Iterate all owners
Store::all()->each(function (Store $store) {
    OwnerContext::withOwner($store, function () {
        // Process for this store
    });
});
```

## Best Practices

1. **Never trust UI scoping** - Always validate on the server
2. **Use `withoutOwnerScope()` sparingly** - It's an escape hatch, not a default
3. **Pass owner explicitly to jobs** - Don't rely on ambient auth
4. **Validate inbound IDs** - Foreign keys must belong to current owner
5. **Use `OwnerWriteGuard`** - For all record lookups before mutations
