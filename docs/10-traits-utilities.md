---
title: Traits & Utilities
---

# Traits & Utilities

Commerce Support provides several utility traits and helper classes for common patterns across commerce packages.

## String similarity

`StringSimilarity` centralizes generic text normalization and edit-distance/similar-text scoring:

```php
use AIArmada\CommerceSupport\Support\StringSimilarity;

$normalized = StringSimilarity::normalize('Ál-MuTtaqīn');
$score = StringSimilarity::score($normalized, 'al muttaqin');
```

Callers retain their own candidate fields, comparability rules, minimum scores, maximum distances, and search-index policies; this utility only supplies the reusable mechanics.

## LikeSearch

`LikeSearch` builds LIKE predicates for user-supplied search input with explicit, driver-aware escaping. Never interpolate `%{$search}%` by hand: unescaped input lets `%` and `_` act as wildcards, hand-rolled backslash escaping matches nothing on SQLite (no default escape character), and a literal `ESCAPE '\'` is a MySQL syntax error. These helpers keep the escaping and the `ESCAPE` declaration in one place:

```php
use AIArmada\CommerceSupport\Support\LikeSearch;

// On an Eloquent or query builder (adds the pattern binding + ESCAPE clause)
$query = LikeSearch::whereLike(User::query(), 'name', LikeSearch::contains($search));
$query = LikeSearch::orWhereLike($query, 'email', LikeSearch::contains($search));

// Pattern builders escape \, %, and _ for you
LikeSearch::contains($search);   // %escaped%
LikeSearch::startsWith($search); // escaped%
LikeSearch::endsWith($search);   // %escaped
```

For raw expressions, use `LikeSearch::escape($value)` for the pattern and append `LikeSearch::escapeClause($source)` so MySQL gets `ESCAPE '\\'` while SQLite, Postgres, and SQL Server get `ESCAPE '\'`:

```php
$escape = LikeSearch::escapeClause($query);
$query->whereRaw("LOWER(name) LIKE ? {$escape}", [mb_strtolower(LikeSearch::contains($search))]);
```

On Postgres the builder helpers emit `ILIKE`, so search is case-insensitive on every driver.

## Contract Test Traits

Three test traits to verify that implementing packages comply with core contracts.

### PaymentGatewayContractTests

Verify gateway implementations:

```php
use AIArmada\CommerceSupport\Testing\PaymentGatewayContractTests;
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use Tests\TestCase;

class StripeGatewayTest extends TestCase
{
    use PaymentGatewayContractTests;

    protected function getGateway(): PaymentGatewayInterface
    {
        return new StripeGateway(
            config('cashier.stripe.secret')
        );
    }

    // Both abstract methods are required
    protected function createCheckoutable(int $amount = 10000): CheckoutableInterface
    {
        return Cart::factory()->create(['total_minor' => $amount]);
    }

    // Optional overrides:
    // - protected function createCustomer(): ?CustomerInterface
    // - protected function shouldSkipApiTests(): bool

    // All contract tests run automatically:
    // - test_gateway_has_name()
    // - test_gateway_has_display_name()
    // - test_gateway_reports_test_mode()
    // - test_gateway_supports_returns_boolean()
    // - test_gateway_has_webhook_handler()
    // - test_create_payment_returns_payment_intent()
    // - test_get_payment_returns_payment_intent()
    // - test_get_payment_throws_for_invalid_id()
    // - test_cancel_payment_returns_cancelled_status()
    // - test_refund_payment_returns_refunded_status()
    // - test_partial_refund_returns_partially_refunded_status()
    // - test_get_payment_methods_returns_array()
}
```

### CheckoutableContractTests

Verify Cart/Order implementations:

```php
use AIArmada\CommerceSupport\Testing\CheckoutableContractTests;

class CartTest extends TestCase
{
    use CheckoutableContractTests;

    protected function createCheckoutable(): CheckoutableInterface
    {
        $cart = Cart::factory()->create();
        $cart->addItem(Product::factory()->create(), 2);
        return $cart;
    }

    // Runs contract tests:
    // - test_checkoutable_has_line_items()
    // - test_checkoutable_has_subtotal()
    // - test_checkoutable_has_discount()
    // - test_checkoutable_has_tax()
    // - test_checkoutable_has_total()
    // - test_checkoutable_total_is_consistent()
    // - test_checkoutable_has_currency()
    // - test_checkoutable_has_reference()
    // - test_checkoutable_notes_is_nullable_string()
    // - test_checkoutable_metadata_is_array()
}
```

### OwnerScopingContractTests

Verify multi-tenancy enforcement:

```php
use AIArmada\CommerceSupport\Testing\OwnerScopingContractTests;

class ProductTest extends TestCase
{
    use OwnerScopingContractTests;

    protected function getModelClass(): string
    {
        return Product::class;
    }

    protected function createOwner(): Model
    {
        return User::factory()->create();
    }

    protected function createModelForOwner(Model $owner): Model
    {
        return Product::factory()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);
    }

    // Optional override: protected function createGlobalModel(): Model

    // Runs security tests:
    // - test_model_uses_has_owner_trait()
    // - test_model_can_be_assigned_owner()
    // - test_model_can_be_global()
    // - test_for_owner_scope_filters_by_owner()
    // - test_for_owner_with_include_global_includes_global_records()
    // - test_global_only_scope_returns_only_global_records()
    // - test_owner_context_with_owner_scopes_queries()
    // - test_assign_owner_sets_owner()
    // - test_remove_owner_throws_on_persisted_owned_record()
    // - test_remove_owner_allowed_on_unsaved_model()
    // - test_cross_tenant_access_prevented()
}
```

## HasPaymentStatus

Automatic payment status transition validation:

```php
use AIArmada\CommerceSupport\Traits\HasPaymentStatus;

class Order extends Model
{
    use HasPaymentStatus;

    protected function casts(): array
    {
        return [
            'payment_status' => PaymentStatus::class,
        ];
    }
}

// Usage
$order = Order::find($id);

// Safe transitions
$order->transitionPaymentStatus(PaymentStatus::PAID); // Validates and saves
$order->markAsPaid();      // Convenience
$order->markAsRefunded();  // Convenience
$order->markAsFailed();    // Convenience

// Check before transitioning
if ($order->canTransitionTo(PaymentStatus::REFUNDED)) {
    $order->transitionPaymentStatus(PaymentStatus::REFUNDED);
}

// Get allowed next states
$allowed = $order->getAllowedTransitions();

// Status checks
$order->isPaid();           // bool
$order->isPaymentPending(); // bool
$order->isPaymentFailed();  // bool
$order->isRefundable();     // bool
```

## Request-Level Memoization (`once()`)

Use Laravel's `once()` helper for memoization inside a single request lifecycle. This is Octane-safe and avoids long-lived static cache leakage.

```php
class Cart extends Model
{
    public function getTotal(): int
    {
        return once(function (): int {
            return $this->items->sum(
                fn ($item) => $item->quantity * $item->unit_price
            );
        });
    }

    public function getFormattedTotal(): string
    {
        return once(function (): string {
            return money($this->getTotal(), $this->currency)->format();
        });
    }
}
```

## ValidatesConfiguration

Assert that required config keys are set at boot time. This does **not** use
Laravel's validator and accepts no rules — the second argument is a flat list of
dot-notation key paths, each of which must resolve to a non-`null` value:

```php
use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;

class CartServiceProvider extends ServiceProvider
{
    use ValidatesConfiguration;

    public function boot(): void
    {
        $this->validateConfiguration('cart', [
            'database.table_prefix',
            'defaults.currency',
            'owner.enabled',
        ]);
    }
}
```

Each key is resolved in dot notation against the named config file. A missing
(`null`) value throws a `RuntimeException` naming the full key and the publish
tag that provides it.

Validation is skipped outside production unless the package opts in with a
`<package>.validate_config` flag, and it is skipped for console runs unless
that flag is set.

## HasOwnerScopeConfig

Config-based owner scope setup:

```php
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;

class Product extends Model
{
    use HasOwner, HasOwnerScopeConfig;

    protected static string $ownerScopeConfigKey = 'products.owner';
}
```

Reads from config:

```php
// config/products.php
return [
    'owner' => [
        'enabled' => true,
        'include_global' => false,
    ],
];
```

### How It Works

```php
// In your model
public static function ownerScopeConfig(): OwnerScopeConfig
{
    return OwnerScopeConfig::fromConfig(
        'products.owner',
        enabledDefault: false,
        includeGlobalDefault: false,
    );
}

// HasOwner::bootHasOwner() reads ownerScopeConfig()
// and applies OwnerScope automatically when enabled.
```

## MoneyNormalizer

Money values enter shared helpers as integer minor units. Convert major-unit
input at the boundary that receives it, choosing and documenting the rounding
policy there.

```php
use AIArmada\CommerceSupport\Support\MoneyNormalizer;

// Assert an already-normalized integer minor-unit value
MoneyNormalizer::toCents(9999);        // 9999

// Convert a decimal major-unit boundary explicitly before normalization.
$majorAmount = '99.995';
$minorAmount = (int) round((float) $majorAmount * 100, 0, PHP_ROUND_HALF_UP); // 10000
$minorAmount = MoneyNormalizer::toCents($minorAmount);

// Convert cents to decimal
MoneyNormalizer::toDollars(9999);      // 99.99

// Format for display (uses akaunting/laravel-money)
MoneyNormalizer::format(9999, 'USD');  // $99.99
MoneyNormalizer::format(9999, 'MYR');  // RM99.99
```

`MoneyNormalizer::toCents()` does not parse currency symbols or decimal input.
Normalize those external values at their integration boundary first, then pass
the resulting integer minor units to the shared helper.

## JSON Column Helper

Get the appropriate JSON column type for your database:

```php
use function AIArmada\CommerceSupport\commerce_json_column_type;

// In migrations
Schema::create('products', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->{commerce_json_column_type('products')}('metadata');
});
```

### Configuration

Pass the package config key as the first argument. Resolution order:

1. env `{PACKAGE}_JSON_COLUMN_TYPE` (for example `PRODUCTS_JSON_COLUMN_TYPE`)
2. env `COMMERCE_JSON_COLUMN_TYPE`
3. `config('{package}.database.json_column_type')`
4. the `$default` argument (`'jsonb'` when omitted)

```php
// config/products.php
return [
    'database' => [
        'json_column_type' => 'json', // or 'text' for SQLite
    ],
];
```

> **warning**
> The helper does not read `commerce-support.database.json_column_type`. That key configures this package's own migrations only; pass your own package key, or set the `COMMERCE_JSON_COLUMN_TYPE` env var for a global override.

## OwnerContext

Static tenant context management:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

// Resolve current owner
$owner = OwnerContext::resolve();

// Override temporarily
$result = OwnerContext::withOwner($store, function () {
    // All queries scoped to $store
    return Product::all();
});

// Explicit global context
OwnerContext::withOwner(null, function () {
    return Product::globalOnly()->get();
});

// Reconstruct from database values
$owner = OwnerContext::fromTypeAndId(
    'App\\Models\\Store',
    'store-uuid-here'
);
```

`OwnerContext::setForRequest()` is reserved for middleware/framework integrations during active HTTP requests. It throws outside HTTP request lifecycle; use `OwnerContext::withOwner(...)` in jobs/commands and other non-HTTP surfaces.

When using `OwnerContextJob`, prefer an explicit `OwnerScopedJob` implementation that returns `OwnerJobContext`.

Laravel convention guidance:

- Use camelCase for PHP fields (`ownerType`, `ownerId`, `ownerIsGlobal`)
- Keep snake_case for persistence/wire payload keys (`owner_type`, `owner_id`)

`ownerIsGlobal=true` is mutually exclusive with owner-bearing payload data (`ownerType`/`ownerId` or owner-bearing model payloads). Contradictory payloads fail closed.

## OwnerScopeIdentifiable

If you need to use owner-scoped helpers with non-Eloquent objects, implement `OwnerScopeIdentifiable`.

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

## OwnerTuple utilities

Use the `Support/OwnerTuple` helpers when code works with raw rows, queue payloads, event payloads, or configurable owner columns.

### `OwnerTupleColumns`

Resolves the physical owner tuple column names for a model or config key.

```php
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;

$columns = OwnerTupleColumns::forModelClass(Product::class);
```

### `OwnerTupleParser`

Parses owner tuple data into a tri-state result:

- owner tuple
- explicit global tuple
- unresolved/malformed tuple

```php
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;

$parsed = OwnerTupleParser::fromRow($row, $columns);

if ($parsed->isOwner()) {
    $owner = $parsed->toOwnerModel();
}
```

For security-sensitive paths, malformed tuples should throw. Batch tooling may opt into unresolved results and skip malformed rows deliberately.

This is the supported alternative to raw duck-typing for `OwnerScopeKey`, `OwnerCache`, and `OwnerFilesystem`.

## Isolation Primitives

`commerce-support` includes non-query isolation helpers for shared-database tenancy:

- `OwnerCache` — owner-scoped cache keys and tagged owner groups when the driver supports tags
- `OwnerFilesystem` — owner-scoped filesystem paths and access helpers
- `OwnerContextJob` — queued-job helper that enters owner context automatically
- `OwnerIdentificationMiddleware` — base middleware for request-time owner identification

See [`11-isolation-primitives.md`](./11-isolation-primitives.md) for end-to-end usage patterns.

## OwnerWriteGuard

Secure record access with owner validation:

```php
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;

// Find or fail with owner check
$product = OwnerWriteGuard::findOrFailForOwner(
    Product::class,
    $productId,
    owner: OwnerContext::CURRENT
);

// With global record support
$product = OwnerWriteGuard::findOrFailForOwner(
    Product::class,
    $productId,
    owner: OwnerContext::CURRENT,
    includeGlobal: true
);
```

Write-path guidance:

- Prefer calling `OwnerWriteGuard::findOrFailForOwner(...)` directly at mutation boundaries.
- Keep `includeGlobal: false` as the default for tenant-context writes.
- Use `includeGlobal: true` only when business rules explicitly allow global-row writes and the call site is intentionally scoped.

## OwnerRouteBinding

Secure route model binding:

```php
use AIArmada\CommerceSupport\Support\OwnerRouteBinding;

// In RouteServiceProvider
public function boot(): void
{
    OwnerRouteBinding::bind('product', Product::class);
    OwnerRouteBinding::bind('order', Order::class);
}

// Now routes automatically validate owner
Route::get('/products/{product}', [ProductController::class, 'show']);
```

## OwnerQuery

Apply owner scoping to query builders:

```php
use AIArmada\CommerceSupport\Support\OwnerQuery;

// Eloquent Builder
$query = Product::query();
OwnerQuery::applyToEloquentBuilder($query, $owner, includeGlobal: false);

// Query Builder (DB::table)
$query = DB::table('products');
OwnerQuery::applyToQueryBuilder($query, $owner, includeGlobal: false);
```

## OwnerUniqueRule

Filament `TextInput::make(...)->unique(...)` checks are global by default: on owner-scoped models that both blocks owners from reusing each other's slugs/codes and leaks cross-owner existence through validation errors. `OwnerUniqueRule::scopeToOwner()` constrains a `Unique` rule to the model's owner scope, honoring the model's own scope config (enabled flag, include-global behavior, and any customized owner column names):

```php
use AIArmada\CommerceSupport\Support\OwnerUniqueRule;
use AIArmada\Docs\Models\Doc;
use Filament\Forms\Components\TextInput;
use Illuminate\Validation\Rules\Unique;

TextInput::make('slug')
    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => OwnerUniqueRule::scopeToOwner($rule, Doc::class));
```

The model must expose `::ownerScopeConfig()`, which comes from the `HasOwnerScopeConfig` trait (`HasOwner` alone does not declare it). When owner scoping is disabled for the model the rule is returned unchanged; with no resolved owner it constrains to global-only rows.

## UniqueSlug

`UniqueSlug::build()` generates a unique slug for any model with a `slug` column. It complements `spatie/laravel-sluggable` (model-event generation): use it for explicit programmatic flows such as bulk imports, console backfills, and save actions that assemble slugs from several parts:

```php
use AIArmada\CommerceSupport\Support\UniqueSlug;
use App\Models\Venue; // your model with a `slug` column

$slug = UniqueSlug::build(
    modelClass: Venue::class,
    baseSlug: 'grand-hall',
    middleSegments: ['kuala-lumpur'], // inserted before the numeric suffix
    trailingSuffix: 'my',             // appended last
    ignoreKey: $venue->getKey(),      // excluded on updates
);
```

Existing slugs preload in one query and resolve in memory; the lookup uses `LikeSearch`, and candidates truncate to 200 characters (bases longer than that still detect truncated collisions through a deterministic escape suffix). Global scopes apply to the collision check; pass `withoutGlobalScopes: true` when the slug feeds a global namespace such as pure-slug public URLs rather than an owner-scoped one.

## StableModelOrder

`StableModelOrder` orders any model collection deterministically by `created_at` ascending with the primary key as tiebreak, so batch sync passes assign stable outcomes regardless of load order:

```php
use AIArmada\CommerceSupport\Support\StableModelOrder;
use App\Models\Venue; // your model

$ordered = StableModelOrder::sort($venues);
$position = StableModelOrder::sequence($venues, $venue->getKey()); // 1-based, or null

$didChange = StableModelOrder::sync($venues, function (Venue $venue): bool {
    return $this->syncCanonicalSlug($venue);
});
```

## PayloadDiff

`PayloadDiff::changed()` returns the entries of a state array whose value differs from the original — the dirty-field computation behind edit forms, audit snapshots, and sync checks. Values normalize before comparison (enums, dates, `Arrayable`, key-sorted maps, numeric `*_id` strings), so Livewire state compares by meaning rather than PHP type:

```php
use AIArmada\CommerceSupport\Support\PayloadDiff;

$changes = PayloadDiff::changed($state, $original); // ['name' => 'New name']
PayloadDiff::equal($state['owner_id'], $original['owner_id'], 'owner_id'); // '5' vs 5: true
```

Only keys present in the original are considered; new keys are ignored.

## RequestFingerprint

`RequestFingerprint::resolve()` returns a stable submitter identity for authenticated users and guests alike, for guest-capable submissions (reports, reviews, applications) and rate-limit keys:

```php
use AIArmada\CommerceSupport\Support\RequestFingerprint;

RequestFingerprint::resolve($request); // 'user:01H...' or 'guest:9f2c…'
```

Guests hash to `sha256(ip|user-agent)`: repeat submitters stay recognizable without storing their IP or user agent.

## CanonicalSlug

`CanonicalSlug::persist()` sets a model's canonical slug and records a redirect from the previous one through a host-provided `SlugRedirectRecorder`. Use it for pure-slug public URLs, where spatie self-healing URLs cannot apply (they need ID-bearing URLs) and old slugs must keep resolving:

```php
use AIArmada\CommerceSupport\Contracts\SlugRedirectRecorder;
use AIArmada\CommerceSupport\Support\CanonicalSlug;

$changed = CanonicalSlug::persist($venue, $slug, app(SlugRedirectRecorder::class));
```

Unsaved slug edits on the model are discarded in favor of the stored value first; the write itself is quiet and timestamp-preserving. `CanonicalSlug::syncChanged()` records a redirect for a slug the caller already changed.

## Exception Classes

### CommerceException

Base exception for all commerce errors. Extends `RuntimeException` and has no
static factories:

```php
use AIArmada\CommerceSupport\Exceptions\CommerceException;

throw new CommerceException('Something went wrong');

$exception = new CommerceException(
    message: 'Payment failed',
    errorCode: 'payment_failed',
    errorData: ['gateway' => 'stripe'],
);

$exception->getErrorCode();   // 'payment_failed'
$exception->getErrorData();   // ['gateway' => 'stripe']
$exception->getContext();     // message, code, error_code, data, file, line
```

### CommerceApiException

API-related errors with HTTP context. Constructor:
`(string $message, int $statusCode = 0, array $errorData = [], ?string $endpoint = null, mixed $apiResponse = null, ?string $errorCode = null, ?Throwable $previous = null)`.
Its only static factory is `fromResponse()`:

```php
use AIArmada\CommerceSupport\Exceptions\CommerceApiException;

throw CommerceApiException::fromResponse(
    ['error' => 'Invalid API key'],  // falls back through message/error/error_description
    401,
    '/v1/payments',
);
```

### PaymentGatewayException

Payment-specific errors. Static factories:

| Factory | Signature |
|---|---|
| `creationFailed` | `string $gatewayName, string $message, ?string $errorCode = null, ...` |
| `notFound` | `string $gatewayName, string $paymentId` |
| `refundFailed` | `string $gatewayName, string $paymentId, string $message, ...` |
| `captureFailed` | `string $gatewayName, string $paymentId, string $message, ...` |
| `cancellationFailed` | `string $gatewayName, string $paymentId, string $message, ...` |
| `invalidConfiguration` | `string $gatewayName, string $message` |
| `unsupportedOperation` | `string $gatewayName, string $operation` |
| `currencyMismatch` | `string $gatewayName, string $expected, string $actual` |
| `invalidStatusTransition` | `$from, $to, array $allowed = []` |

```php
use AIArmada\CommerceSupport\Exceptions\PaymentGatewayException;

throw PaymentGatewayException::creationFailed('stripe', 'Connection timeout');
throw PaymentGatewayException::notFound('stripe', $paymentId);
throw PaymentGatewayException::refundFailed('stripe', $paymentId, 'Already refunded');
throw PaymentGatewayException::invalidConfiguration('stripe', 'Missing API key');
throw PaymentGatewayException::currencyMismatch('stripe', 'MYR', 'USD');
```

### WebhookVerificationException

Webhook handling errors. Every factory takes a `$gatewayName` argument:

| Factory | Signature |
|---|---|
| `missingSignature` | `string $gatewayName` |
| `invalidSignature` | `string $gatewayName` |
| `missingPublicKey` | `string $gatewayName` |
| `invalidPayload` | `string $gatewayName, string $reason` |

```php
use AIArmada\CommerceSupport\Exceptions\WebhookVerificationException;

throw WebhookVerificationException::missingSignature('chip');
throw WebhookVerificationException::invalidSignature('chip');
throw WebhookVerificationException::invalidPayload('chip', 'Missing event_id');
```
