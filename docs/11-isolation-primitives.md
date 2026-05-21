---
title: Isolation Primitives for Single-Database Multitenancy
---

This guide covers the core isolation primitives in `commerce-support` that prevent cross-tenant data access in shared-database multitenancy.

These primitives live in `commerce-support` even before broad package adoption because they define the standard isolation contract the rest of the ecosystem can converge on.

## Overview

When using single-database multitenancy (all tenants in one database with owner-scoped queries), you need to enforce owner boundaries **outside** of database queries: in caches, filesystems, job queues, and request-level context.

The main primitives are:

1. **`OwnerCache`** — Scoped cache key builder and accessor
2. **`OwnerFilesystem`** — Scoped file path builder and storage helper
3. **`OwnerContextJob`** — Trait for queued jobs that auto-enter owner context
4. **`OwnerIdentificationMiddleware`** — Base middleware for tenant identification
5. **`NeedsOwner`** — Middleware to enforce owner context on tenant-required routes
6. **`OwnerTuple` utilities** — Shared owner tuple column and parsing helpers for raw rows/payloads

All owner-taking helpers accept either:

- an Eloquent model, or
- an implementation of `AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable`

Use the contract for lightweight adapters instead of raw duck-typing.

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

## OwnerCache

Prevent cache bleed across tenants by building owner-scoped cache keys in the format `owner:{ownerScopeKey}:{logicalKey}`.

### Basic Usage

```php
use AIArmada\CommerceSupport\Support\OwnerCache;

// Get the current tenant/owner (resolved via OwnerContext)
$owner = auth()->user(); // or \Auth::user()

// Build and store an owner-scoped cache key
OwnerCache::put($owner, 'user.preferences', ['theme' => 'dark'], now()->addHour());

// Retrieve cached value
$prefs = OwnerCache::get($owner, 'user.preferences');

// Retrieve or compute with callback
$summary = OwnerCache::remember($owner, 'cart.summary', now()->addHour(), function () {
    return Cart::where('user_id', auth()->id())->compute();
});

// Forget a key
OwnerCache::forget($owner, 'user.preferences');

// Forget all keys for an owner (by prefix)
OwnerCache::forgetOwner($owner);
```

### Key Features

- **Automatic scope key generation**: Uses `OwnerScopeKey::forOwner($owner)` to create a hash unique to each owner
- **Global context support**: Pass `null` as owner for global/unauthenticated caches (key: `owner:global:logicalKey`)
- **Tag-aware operations**: On drivers that support tags (Redis, Memcached), cache reads and writes are grouped under an owner tag for efficient `forgetOwner()` cleanup
- **Fallback for file/array drivers**: Uses normal cache operations when tags are unavailable; in that case `forgetOwner()` is a no-op and callers should use explicit `forget()` instead

### What OwnerCache Is For

`OwnerCache` standardizes one simple rule: any owner-sensitive cache entry should be scoped before it ever touches the cache store.

Typical use cases:

- cached cart summaries
- owner-specific dashboard counters
- per-owner API response caches
- export/import progress snapshots
- expensive owner-scoped calculations

It is currently a **foundation primitive**: tested, documented, and ready for adoption, but not yet broadly consumed by other packages. That is intentional for this rollout phase.

### When to Use

- Store computed user/store preferences
- Cache query results by owner
- Cache API responses per tenant
- Store session-like data outside Laravel's session driver
- Any multi-step calculation that's reusable and owner-specific

## OwnerFilesystem

Prevent file access across tenants by building owner-scoped storage paths in the format `owners/{ownerScopeKey}/{relativePath}`.

### Basic Usage

```php
use AIArmada\CommerceSupport\Support\OwnerFilesystem;

$owner = auth()->user();

// Store a file for an owner
OwnerFilesystem::put($owner, 'invoices/2025-01.pdf', $pdfContent);

// Check if file exists
if (OwnerFilesystem::exists($owner, 'invoices/2025-01.pdf')) {
    // Retrieve it
    $content = OwnerFilesystem::get($owner, 'invoices/2025-01.pdf');
}

// Get public URL (if driver supports it)
$url = OwnerFilesystem::url($owner, 'invoices/2025-01.pdf');

// Get temporary URL (e.g., for S3)
$tempUrl = OwnerFilesystem::temporaryUrl($owner, 'invoices/2025-01.pdf', now()->addHours(2));

// Copy within owner scope
OwnerFilesystem::copy($owner, 'invoices/2025-01.pdf', 'invoices/2025-01-backup.pdf');

// Move within owner scope
OwnerFilesystem::move($owner, 'invoices/draft.pdf', 'invoices/final.pdf');

// Delete
OwnerFilesystem::delete($owner, 'invoices/2025-01.pdf');
```

### Safety Features

- **Path traversal prevention**: Rejects paths with `..` or leading `/`
- **Owner scope enforcement**: All operations happen within `owners/{ownerScopeKey}/` prefix
- **Consistent scoping**: Same owner-scoped key across all methods ensures no cross-tenant access

### When to Use

- Store uploads per tenant (invoices, reports, user files)
- Generate and serve exports per owner
- Store temporary processing files
- Backup or archive tenant data
- Any file operation that should be per-tenant

## OwnerContextJob

Automatically enter owner context for queued jobs, preventing queries from running outside tenant boundaries.

### Basic Usage

```php
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;

class ProcessOrderJob implements ShouldQueue
{
    use Queueable;
    use OwnerContextJob;
    use SerializesModels;

    public function __construct(public Order $order) {}

    /**
     * Implement performJob() instead of handle().
     */
    public function performJob(): void
    {
        // Owner context is automatically set
        // All queries are scoped to $this->order->owner
        
        $this->order->markAsProcessed();
        
        // Email the store owner
        Mail::to($this->order->store->email)->send(new OrderNotification($this->order));
    }
}
```

### How It Works

1. The trait overrides `handle()` (final, do not override)
2. It reflects on public properties to find an owner model
3. It calls `OwnerContext::withOwner($owner, ...)` before `performJob()`
4. Queries run within owner scope; context is restored after the job

### Owner Resolution

The trait looks for public properties that are Eloquent models:

```php
public function __construct(
    public Order $order,        // ← Trait checks this
    public Store $store,        // ← And this
    private $config,            // ← Ignores private properties
) {}
```

If a public model property has `owner_type` and `owner_id` attributes, it uses those to resolve the owner. If the job instead carries explicit owner payload fields, the trait resolves from those. Otherwise, it treats the model itself as the owner (useful for jobs where the model itself is the owner).

Best-practice job shape is explicit and typed:

- Implement `OwnerScopedJob`
- Return `OwnerJobContext`
- Use camelCase PHP fields (`ownerType`, `ownerId`, `ownerIsGlobal`)

The trait keeps a compatibility fallback for snake_case payload field names where needed.

## OwnerTuple utilities

Use the `Support/OwnerTuple` helpers when code needs to reason about owner tuples outside Eloquent model APIs.

### `OwnerTupleColumns`

```php
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;

$columns = OwnerTupleColumns::forModelClass(Product::class);
```

This resolves the physical owner tuple column names, including model-specific overrides supplied through `HasOwnerScopeConfig`.

### `OwnerTupleParser`

```php
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;

$parsed = OwnerTupleParser::fromRow($row, $columns);
```

The parser distinguishes between:

- a real owner tuple
- an explicit global tuple
- an unresolved / malformed tuple

That distinction is important because missing owner data must never be silently treated as explicit global access.

### When to Use

- Queued email/notification jobs
- Async data processing (exports, imports, reports)
- Scheduled/cron tasks that are per-tenant
- Webhook processing jobs
- Any long-running operation that should respect tenant boundaries

## OwnerIdentificationMiddleware

Base middleware for identifying tenant/owner from incoming requests (domain, header, auth context, etc.).

### Example: Subdomain-Based Tenancy

```php
namespace App\Http\Middleware;

use AIArmada\CommerceSupport\Middleware\OwnerIdentificationMiddleware;
use Illuminate\Http\Request;

class IdentifyOwnerFromSubdomain extends OwnerIdentificationMiddleware
{
    /**
     * Resolve the owner from the incoming request.
     */
    protected function resolveOwnerFromRequest(Request $request)
    {
        $subdomain = explode('.', $request->getHost())[0];

        // Global admin area
        if (in_array($subdomain, ['app', 'www', 'admin'])) {
            return null;
        }

        // Find store by subdomain
        $store = Store::where('subdomain', $subdomain)->first();

        if (! $store) {
            abort(404, "Store '$subdomain' not found.");
        }

        return $store;
    }
}
```

### Example: Auth Context

```php
class IdentifyOwnerFromAuth extends OwnerIdentificationMiddleware
{
    protected function resolveOwnerFromRequest(Request $request)
    {
        // Authenticated users are their own owner
        return $request->user();
    }
}
```

### Example: Header-Based

```php
class IdentifyOwnerFromHeader extends OwnerIdentificationMiddleware
{
    protected function resolveOwnerFromRequest(Request $request)
    {
        $ownerId = $request->header('X-Owner-ID');

        if (! $ownerId) {
            return null;
        }

        return Owner::find($ownerId);
    }
}
```

### Registration

Add your middleware to the HTTP middleware stack:

```php
// app/Http/Kernel.php
protected $middleware = [
    // Global middleware (runs on every request)
    IdentifyOwnerFromSubdomain::class,
    
    // ... other middleware
];

// Or in route groups:
Route::middleware(IdentifyOwnerFromAuth::class)->group(function () {
    // Routes here have owner context
});
```

### Features

- **Request-scoped**: Owner context is stored in request attributes (automatic cleanup per request)
- **Octane-safe**: No process-wide state pollution
- **Framework integration**: Works with Filament multitenancy, Spatie permissions team resolver, etc.

## NeedsOwner

Use `NeedsOwner` to fail closed on routes that require an owner context.

```php
use AIArmada\CommerceSupport\Middleware\NeedsOwner;

Route::middleware([
    IdentifyOwnerFromSubdomain::class,
    NeedsOwner::class,
])->group(function () {
    Route::get('/tenant/orders', TenantOrderController::class);
});
```

When no owner is resolved, the middleware dispatches `OwnerNotResolvedForRequestEvent` and throws `NoCurrentOwnerException`.

Keep global/admin/public routes outside this middleware when owner context is intentionally optional.

## Owner Lifecycle Events

`OwnerContext` dispatches lifecycle events during owner transitions:

- `MakingOwnerCurrentEvent`
- `MadeOwnerCurrentEvent`
- `ForgettingCurrentOwnerEvent`
- `ForgotCurrentOwnerEvent`

These hooks are intended for observability, auditing, and integration glue.

## Integration Example: Full Workflow

Here's a complete example using all four primitives:

```php
// Middleware identifies owner from subdomain
class IdentifyOwnerFromSubdomain extends OwnerIdentificationMiddleware { ... }

// Controller action
class OrderController {
    public function show(Order $order)
    {
        // $order is already scoped to current owner (query-level)
        
        // Get cached summary
        $summary = OwnerCache::remember(
            auth()->user(),
            "order.{$order->id}.summary",
            now()->addHour(),
            fn () => $order->computeSummary()
        );

        // Generate invoice PDF (stored per-owner)
        $pdf = $order->generateInvoice();
        OwnerFilesystem::put(auth()->user(), "invoices/{$order->id}.pdf", $pdf);

        return view('orders.show', ['order' => $order, 'summary' => $summary]);
    }

    public function export()
    {
        // Queue a job that exports all orders for this owner
        ExportOrdersJob::dispatch(auth()->user());

        return back()->with('message', 'Export started');
    }
}

// Job automatically enters owner context
class ExportOrdersJob implements ShouldQueue
{
    use OwnerContextJob, Queueable, SerializesModels;

    public function __construct(public User $owner) {}

    public function performJob(): void
    {
        // All queries are scoped to $this->owner
        $orders = Order::all(); // Only this owner's orders

        $csv = $this->generateCSV($orders);

        // Store per-owner
        OwnerFilesystem::put($this->owner, "exports/orders.csv", $csv);

        // Clear cached order count
        OwnerCache::forget($this->owner, "orders.count");

        Mail::to($this->owner->email)->send(
            new ExportReady(OwnerFilesystem::url($this->owner, "exports/orders.csv"))
        );
    }
}
```

## Best Practices

### Cache Keys

- Use **logical** keys (e.g., `cart.summary`), not IDs
- Use **dots** or **dashes** in keys, not colons (colons are scoped key separators)
- Forget keys explicitly or rely on TTL for cleanup
- For Redis/Memcached, use `forgetOwner()` for bulk cleanup

### Filesystem Paths

- Use **descriptive relative paths** within owner scope (e.g., `invoices/2025-01.pdf`)
- Never include owner ID in the filename; the path already scopes it
- Use **nested directories** for organization
- Validate file size and MIME type before storing

### Jobs

- Always include an **owner-bearing model** as a job property
- Use `SerializesModels` to serialize owner models safely
- Don't pass sensitive data in job payloads; query it inside `performJob()`
- For scheduled tasks, manually iterate owners and dispatch jobs per-owner

### Middleware

- Register **early** in the middleware stack (before auth/tenancy middleware)
- Return `null` for global contexts (e.g., login, public pages)
- Throw `RuntimeException` or `abort(404)` for invalid owners
- Don't query the database on every request; cache tenant lookups if possible

## Troubleshooting

### "Owner context is required" error

**Problem**: Job or operation runs without owner context.

**Solution**:
- Ensure `commerce-support.owner.enabled` is `true` in config
- For jobs: add a public owner-bearing model property
- For middleware: register it in the global middleware stack
- For console commands: manually set context with `OwnerContext::withOwner(...)`

### Cache bleed between tenants

**Problem**: One owner's cached data visible to another.

**Solution**:
- Always use `OwnerCache` instead of `Cache::*` directly
- Verify middleware is setting owner context on every request
- Remember that `forgetOwner()` bulk-clears only on tag-capable drivers such as Redis/Memcached

### Files appearing in wrong location

**Problem**: Files stored outside owner scope.

**Solution**:
- Always use `OwnerFilesystem` instead of `Storage::*` directly
- Don't build paths manually; use `OwnerFilesystem::path($owner, $relative)`
- Verify `$owner` is correct before storing

## Related Documentation

- [Multi-Tenancy Overview](./04-multi-tenancy.md)
- [OwnerContext and Query Scoping](./04-multi-tenancy.md#ownercontext-and-query-scoping)
- [Traits & Utilities](./10-traits-utilities.md)
- [Troubleshooting](./99-troubleshooting.md)
