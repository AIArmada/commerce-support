---
title: Traits & Utilities
---

# Traits & Utilities

Commerce Support provides several utility traits and helper classes for common patterns across commerce packages.

## Contract Test Traits

Three test traits to verify that implementing packages comply with core contracts.

### PaymentGatewayContractTests

Verify gateway implementations:

```php
use AIArmada\CommerceSupport\Testing\PaymentGatewayContractTests;
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

    // All contract tests run automatically:
    // - test_gateway_has_name()
    // - test_create_payment_returns_payment_intent()
    // - test_get_payment_returns_intent()
    // - test_get_payment_throws_for_invalid_id()
    // - test_cancel_payment_works()
    // - test_refund_payment_works()
}
```

### CheckoutableContractTests

Verify Cart/Order implementations:

```php
use AIArmada\CommerceSupport\Testing\CheckoutableContractTests;

class CartTest extends TestCase
{
    use CheckoutableContractTests;

    protected function getCheckoutable(): CheckoutableInterface
    {
        $cart = Cart::factory()->create();
        $cart->addItem(Product::factory()->create(), 2);
        return $cart;
    }

    // Runs contract tests:
    // - test_checkoutable_has_checkout_id()
    // - test_checkoutable_has_customer()
    // - test_checkoutable_has_line_items()
    // - test_checkoutable_total_is_consistent()
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

    protected function createOwnedModel($owner): Model
    {
        return Product::factory()->create([
            'owner_type' => $owner::class,
            'owner_id' => $owner->id,
        ]);
    }

    // Runs security tests:
    // - test_model_uses_has_owner_trait()
    // - test_cross_tenant_access_prevented()
    // - test_for_owner_scope_filters_by_owner()
    // - test_global_records_handled_correctly()
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

Validate package configuration at boot time:

```php
use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;

class CartServiceProvider extends ServiceProvider
{
    use ValidatesConfiguration;

    public function boot(): void
    {
        $this->validateConfiguration('cart', [
            'database.table_prefix' => ['required', 'string'],
            'defaults.currency' => ['required', 'string', 'size:3'],
            'owner.enabled' => ['boolean'],
        ]);
    }
}
```

### Validation Rules

Uses Laravel's validator, so all standard rules work:

```php
$this->validateConfiguration('package', [
    // Required string
    'api_key' => ['required', 'string'],

    // Optional with default type
    'timeout' => ['nullable', 'integer', 'min:1', 'max:300'],

    // Enum values
    'mode' => ['required', 'in:sandbox,production'],

    // Nested validation
    'database.tables.orders' => ['required', 'string'],
]);
```

### Handling Failures

```php
$this->validateConfiguration('cart', $rules, throwOnFailure: true);
// Throws InvalidArgumentException on failure

$this->validateConfiguration('cart', $rules, throwOnFailure: false);
// Returns false on failure, logs warning
```

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

Consistent money handling:

```php
use AIArmada\CommerceSupport\Support\MoneyNormalizer;

// Convert various inputs to cents
MoneyNormalizer::toCents(99.99);       // 9999
MoneyNormalizer::toCents('99.99');     // 9999
MoneyNormalizer::toCents('$99.99');    // 9999
MoneyNormalizer::toCents('€99.99');    // 9999
MoneyNormalizer::toCents('RM 99.99');  // 9999
MoneyNormalizer::toCents(9999);        // 9999 (assumes cents)
MoneyNormalizer::toCents(null);        // 0

// Convert cents to decimal
MoneyNormalizer::toDollars(9999);      // 99.99

// Format for display (uses akaunting/laravel-money)
MoneyNormalizer::format(9999, 'USD');  // $99.99
MoneyNormalizer::format(9999, 'MYR');  // RM99.99
```

### Supported Currency Symbols

Automatically stripped during normalization:

| Symbol | Currency |
|--------|----------|
| `$` | USD, etc. |
| `€` | EUR |
| `£` | GBP |
| `¥` | JPY/CNY |
| `₹` | INR |
| `RM` | MYR |
| `₱` | PHP |
| `₩` | KRW |
| `฿` | THB |
| `₫` | VND |
| `₪` | ILS |
| `₨` | PKR/NPR |
| `R$` | BRL |
| `kr` | SEK/NOK/DKK |
| `zł` | PLN |

## JSON Column Helper

Get the appropriate JSON column type for your database:

```php
use function AIArmada\CommerceSupport\commerce_json_column_type;

// In migrations
Schema::create('products', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->{commerce_json_column_type()}('metadata');
});
```

### Configuration

```php
// config/commerce-support.php
return [
    'database' => [
        'json_column_type' => 'json', // or 'text' for SQLite
    ],
];

// Or per-package override
// config/products.php
return [
    'database' => [
        'json_column_type' => 'jsonb', // PostgreSQL
    ],
];
```

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

## Exception Classes

### CommerceException

Base exception for all commerce errors:

```php
use AIArmada\CommerceSupport\Exceptions\CommerceException;

throw new CommerceException('Something went wrong');
throw CommerceException::operationFailed('create', 'order');
```

### CommerceApiException

API-related errors with HTTP context:

```php
use AIArmada\CommerceSupport\Exceptions\CommerceApiException;

throw CommerceApiException::unauthorized('Invalid API key');
throw CommerceApiException::rateLimited(60); // Retry after 60 seconds
throw CommerceApiException::serviceUnavailable('Payment gateway down');
```

### PaymentGatewayException

Payment-specific errors:

```php
use AIArmada\CommerceSupport\Exceptions\PaymentGatewayException;

throw PaymentGatewayException::cardDeclined('card_declined');
throw PaymentGatewayException::insufficientFunds();
throw PaymentGatewayException::invalidAmount(-100);
throw PaymentGatewayException::gatewayError('stripe', 'Connection timeout');
```

### WebhookVerificationException

Webhook handling errors:

```php
use AIArmada\CommerceSupport\Exceptions\WebhookVerificationException;

throw WebhookVerificationException::invalidSignature();
throw WebhookVerificationException::invalidPayload('Missing event_id');
throw WebhookVerificationException::expiredTimestamp();
```
