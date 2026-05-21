---
title: Multi-tenancy
---

# Multi-tenancy

Commerce Support provides the owner-scoping contract used by commerce packages to implement shared-database multitenancy safely.

## Core contract

### Owner terminology

An **owner** is the model that defines the tenant boundary for a record. In different applications that owner may be a:

- `Store`
- `Merchant`
- `Team`
- `Organization`
- any other Eloquent model

`commerce-support` intentionally uses **owner** rather than **tenant** because the boundary is polymorphic.

### Record states

| State | Owner tuple | Meaning |
|---|---|---|
| Owner-scoped | `owner_type` + `owner_id` populated | Visible only to that owner |
| Explicit global | `owner_type = null` and `owner_id = null` | Ownerless row; may be included only when the call site opts in |
| Invalid | one column populated, the other missing | Malformed data; fail closed in normal code paths |

The owner tuple is security-sensitive. Missing owner context is **not** the same thing as explicit global access.

## Owner context rules

### Request surfaces

Use an `OwnerResolverInterface` implementation plus request middleware to identify the current owner early in the HTTP lifecycle.

```php
<?php

namespace App\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;

final class TenantOwnerResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        return \Filament\Facades\Filament::getTenant();
    }
}
```

Register it in configuration:

```php
'owner' => [
    'enabled' => true,
    'resolver' => App\Support\TenantOwnerResolver::class,
],
```

### Non-request surfaces

Jobs, commands, listeners, schedules, exports, and webhooks must not rely on ambient request state.

Use explicit owner context:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

OwnerContext::withOwner($store, function (): void {
    // owner-safe work here
});
```

Use explicit global context only when global rows are intentionally allowed:

```php
OwnerContext::withOwner(null, function (): void {
    // intentional global-only work
});
```

`OwnerContext::setForRequest()` is reserved for HTTP/framework integration points and throws outside an active request.

## Model integration

Apply owner scoping to tenant-owned models with `HasOwner` and `HasOwnerScopeConfig`.

```php
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;

final class Product extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;

    protected static string $ownerScopeConfigKey = 'products.owner';
}
```

Tenant-owned tables should include nullable morph owner columns:

```php
Schema::create('products', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->nullableMorphs('owner');
    $table->timestamps();
});
```

## Query behavior

### Eloquent

When owner scoping is enabled, the `OwnerScope` global scope protects reads by default.

```php
Product::all();
Product::forOwner($store)->get();
Product::forOwner($store, includeGlobal: true)->get();
Product::globalOnly()->get();
Product::withoutOwnerScope()->get(); // explicit system escape hatch
```

Any `withoutOwnerScope()` usage should be intentional and greppable.

### Query builder

`DB::table()` does not receive Eloquent global scopes. Use `OwnerQuery` for raw query paths.

```php
use AIArmada\CommerceSupport\Support\OwnerQuery;

OwnerQuery::applyToQueryBuilder(
    DB::table('products'),
    $store,
    includeGlobal: false,
);
```

## Owner tuple utility

`commerce-support` now provides shared owner tuple primitives under `Support/OwnerTuple` for code paths that work with raw rows, payloads, or configurable owner columns.

### Column resolution

```php
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;

$columns = OwnerTupleColumns::forModelClass(Product::class);

$columns->ownerTypeColumn; // owner_type by default
$columns->ownerIdColumn;   // owner_id by default
```

Models may override the physical column names through `HasOwnerScopeConfig`, even though the default remains `owner_type` / `owner_id`.

### Tuple parsing

```php
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;

$parsed = OwnerTupleParser::fromRow(
    row: $row,
    columns: $columns,
);

if ($parsed->isOwner()) {
    $owner = $parsed->toOwnerModel();
}

if ($parsed->isExplicitGlobal()) {
    // intentional global tuple
}
```

The parser is tri-state:

- owner tuple present
- explicit global tuple
- unresolved / malformed

Normal security-sensitive code paths should fail closed. Batch commands may opt into soft handling and skip malformed rows deliberately.

## Write protection

Use `OwnerWriteGuard` when resolving submitted IDs before mutation.

Write-path standard:

- Call `OwnerWriteGuard::findOrFailForOwner(...)` directly inside action handlers, bulk handlers, relation managers, and form submit handlers.
- Default write revalidation to `includeGlobal: false`.
- Do not add package-local wrapper guards for normal write revalidation paths.
- If global rows must be mutated, enter explicit global context (`OwnerContext::withOwner(null, ...)`) and keep it intentional and greppable.

```php
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;

$product = OwnerWriteGuard::findOrFailForOwner(
    Product::class,
    $productId,
    owner: OwnerContext::CURRENT,
    includeGlobal: false,
);
```

This prevents cross-owner primary-key lookups from slipping through action handlers or form submissions.

## Route hardening

### Tenant-required routes

Use `NeedsOwner` on route groups where owner context is mandatory.

```php
use AIArmada\CommerceSupport\Middleware\NeedsOwner;

Route::middleware([
    IdentifyOwnerFromSubdomain::class,
    NeedsOwner::class,
])->group(function (): void {
    Route::get('/orders', OrderController::class);
});
```

When no owner is resolved, `NeedsOwner` dispatches `OwnerNotResolvedForRequestEvent` and throws `NoCurrentOwnerException`.

### Route model binding

For route-bound models, use `OwnerRouteBinding` so resolved records are validated inside the current owner scope.

## Queue/job contract

Use `OwnerContextJob` for queued jobs that must re-enter owner context safely.

Prefer an explicit `OwnerScopedJob` contract that returns `OwnerJobContext`:

```php
use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;

final class ExportOrdersJob implements OwnerScopedJob, ShouldQueue
{
    use OwnerContextJob;

    public function __construct(
        public string $ownerType,
        public string $ownerId,
        public bool $ownerIsGlobal = false,
    ) {}

    public function ownerContext(): OwnerJobContext
    {
        return new OwnerJobContext(
            ownerType: $this->ownerType,
            ownerId: $this->ownerId,
            ownerIsGlobal: $this->ownerIsGlobal,
        );
    }

    protected function performJob(): void
    {
        // owner context already entered
    }
}
```

If a queue/event payload is serialized externally (wire format), use snake_case keys (`owner_type`, `owner_id`) and map them to camelCase PHP fields at the boundary.

## Owner lifecycle events

`OwnerContext` dispatches lifecycle events for observability:

- `MakingOwnerCurrentEvent`
- `MadeOwnerCurrentEvent`
- `ForgettingCurrentOwnerEvent`
- `ForgotCurrentOwnerEvent`

Use them for logging, auditing, and metrics rather than for core authorization logic.

## Best practices

1. Treat owner scoping as a security boundary.
2. Fail closed when owner context is missing.
3. Use explicit global context only when truly intended.
4. Revalidate submitted IDs server-side.
5. Use the shared owner tuple parser for raw rows and payloads.
6. Keep `withoutOwnerScope()` and cross-owner operations explicit and greppable.

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
    'enabled' => true,
    'resolver' => App\Support\TenantOwnerResolver::class,
],
```

When `enabled` is `true`, the application will not boot with `NullOwnerResolver`. Leave this global switch disabled only for single-tenant/no-owner installs.

### 3. Enable in Package Configs

```php
// config/cart.php
'owner' => [
    'enabled' => true,
    'include_global' => false,
    'auto_assign_on_create' => true,
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

// Bypass owner scope entirely (system-only escape hatch)
Product::withoutOwnerScope()->get();
```

Any `withoutOwnerScope()` usage should be intentional, greppable, and surrounded by explicit owner iteration or system context.

## Owner Context Management

### Resolve Current Owner

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

$owner = OwnerContext::resolve();
```

If an operation requires owner isolation, do not continue when this returns `null`. Either enter an explicit owner context or use `OwnerContext::withOwner(null, ...)` for intentional global records.

### Override Context Temporarily

```php
// Override for a callback
$result = OwnerContext::withOwner($differentOwner, function () {
    return Product::all(); // Scoped to $differentOwner
});

// Create or mutate global rows intentionally
OwnerContext::withOwner(null, function () {
    return Product::globalOnly()->create([
        'name' => 'Global product',
    ]);
});
```

`OwnerContext::setForRequest()` is reserved for framework-level integrations (for example, team resolvers/middleware). Application code should prefer `OwnerContext::withOwner(...)` so state is always restored safely.

`OwnerContext::setForRequest()` is HTTP-only and will throw outside an active request lifecycle. For jobs/commands/non-HTTP surfaces, always use `OwnerContext::withOwner(...)`.

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

`OwnerWriteGuard` now fails closed for unsupported models:
- throws when the model does not implement owner scoping
- throws when owner scoping is explicitly disabled for that model

This prevents accidental unscoped primary-key lookups through owner-safe helper APIs.

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

Raw query-builder paths must always be audited separately because Eloquent global scopes do not apply to `DB::table()`.

## Model Methods

The `HasOwner` trait provides:

```php
// Check ownership
$product->hasOwner();              // Has any owner
$product->isGlobal();              // No owner (global record)
$product->belongsToOwner($store);  // Belongs to specific owner

// Modify ownership (on unsaved models only — owner columns are immutable after creation)
$product = new Product();
$product->assignOwner($store);     // Set owner before first save
$product->removeOwner();           // Clear owner before first save (makes it global)

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

Webhook processors, health checks, reports, exports, and imports follow the same rule: pass or iterate owners explicitly, then enter `OwnerContext::withOwner($owner, ...)` before touching tenant-owned data.

## Owner Scope Contract

Owner-scoped helper APIs accept either an Eloquent model or an `AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable` implementation.

```php
use AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable;

final readonly class OwnerReference implements OwnerScopeIdentifiable
{
    public function __construct(
        private string $ownerType,
        private string $ownerId,
    ) {}

    public function getMorphClass(): string
    {
        return $this->ownerType;
    }

    public function getKey(): string
    {
        return $this->ownerId;
    }
}
```

Use this contract for lightweight adapters, DTOs, or test doubles. Do not rely on arbitrary duck-typed objects.

## Isolation Helpers

For non-query isolation boundaries, `commerce-support` also provides:

- `OwnerCache` for owner-scoped cache keys and tagged groups on supported drivers
- `OwnerFilesystem` for owner-scoped paths under `owners/{ownerScopeKey}/...`
- `OwnerContextJob` for queued jobs that must enter owner context before running
- `OwnerIdentificationMiddleware` as an HTTP middleware base class for request-time owner identification

These helpers are optional primitives for package/app adoption; they do not alter owner scoping automatically unless you use them.

## Tenant-Required Routes (`NeedsOwner`)

Use `NeedsOwner` on route groups where owner context is mandatory.

```php
use AIArmada\CommerceSupport\Middleware\NeedsOwner;

Route::middleware([
    IdentifyOwnerFromSubdomain::class,
    NeedsOwner::class,
])->group(function () {
    Route::get('/orders', OrderController::class);
    Route::get('/products', ProductController::class);
});
```

If no owner can be resolved, `NeedsOwner` fails closed by:

- dispatching `AIArmada\CommerceSupport\Events\OwnerNotResolvedForRequestEvent`
- throwing `AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException`

Apply `NeedsOwner` only to tenant-required routes. Keep global/admin/public routes outside this middleware when owner context is intentionally optional.

## Owner Lifecycle Events

`OwnerContext` now dispatches lifecycle events for owner transitions:

- `MakingOwnerCurrentEvent`
- `MadeOwnerCurrentEvent`
- `ForgettingCurrentOwnerEvent`
- `ForgotCurrentOwnerEvent`

These events are useful for logging, auditing, integration hooks, and metrics.

```php
use AIArmada\CommerceSupport\Events\MadeOwnerCurrentEvent;
use AIArmada\CommerceSupport\Events\OwnerNotResolvedForRequestEvent;
use Illuminate\Support\Facades\Event;

Event::listen(MadeOwnerCurrentEvent::class, function (MadeOwnerCurrentEvent $event): void {
    // Track owner-scoped runtime transitions
});

Event::listen(OwnerNotResolvedForRequestEvent::class, function (OwnerNotResolvedForRequestEvent $event): void {
    // Alert on tenant-required requests without owner context
});
```

## Filament and Submitted IDs

Filament option lists are not authorization. Scope the options for good UX, then revalidate submitted IDs inside action handlers:

```php
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;

$category = OwnerWriteGuard::findOrFailForOwner(
    Category::class,
    $categoryId,
    includeGlobal: false,
);
```

Apply the same pattern to bulk actions, relation managers, imports, exports, and custom page actions.

## Best Practices

1. **Never trust UI scoping** - Always validate on the server
2. **Use `withoutOwnerScope()` sparingly** - It's an escape hatch, not a default
3. **Pass owner explicitly to jobs** - Don't rely on ambient auth
4. **Validate inbound IDs** - Foreign keys must belong to current owner
5. **Use `OwnerWriteGuard`** - For all record lookups before mutations
