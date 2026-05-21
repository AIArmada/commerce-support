---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions when working with Commerce Support.

## Multi-tenancy Issues

### Owner Scope Not Applied

**Symptoms:** Queries return data from all tenants.

**Cause 1:** Owner resolver returns null.

```php
// Check resolver
$resolver = app(OwnerResolverInterface::class);
$owner = $resolver->resolve();
dd($owner); // Should not be null
```

**Solution:** Ensure your resolver returns the correct owner:

```php
class TenantOwnerResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        // Debug: Log what's happening
        $tenant = Filament::getTenant();
        Log::debug('Resolving owner', ['tenant' => $tenant?->id]);
        return $tenant;
    }
}
```

**Cause 2:** Owner mode disabled in config.

```php
// Check config
dd(config('package.owner.enabled')); // Should be true
```

**Cause 3:** Model missing trait.

```php
class Product extends Model
{
    use HasOwner;           // Required
    use HasOwnerScopeConfig; // If using config-based setup
}
```

### Cross-tenant Data Leakage

**Symptoms:** Users can see other tenants' data.

**Cause:** Manual queries bypassing Eloquent.

```php
// ❌ Unsafe - bypasses global scope
DB::table('products')->get();

// ✅ Safe - use Eloquent
Product::all();

// ✅ Or apply manually
use AIArmada\CommerceSupport\Support\OwnerQuery;
$query = DB::table('products');
OwnerQuery::applyToQueryBuilder($query, $owner);
```

### "Trying to get property of non-object"

**Cause:** Owner resolver returning null unexpectedly.

```php
// Add null check in resolver
public function resolve(): ?Model
{
    $tenant = Filament::getTenant();

    if (! $tenant) {
        Log::warning('No tenant in context');
        return null;
    }

    return $tenant;
}
```

## Payment Gateway Issues

### "Gateway not registered"

**Cause:** Gateway not bound in container.

```php
// In service provider
$this->app->bind(PaymentGatewayInterface::class, function ($app) {
    return new StripeGateway(
        new \Stripe\StripeClient(config('services.stripe.secret'))
    );
});
```

### "Invalid payment amount"

**Cause:** Amount not in cents.

```php
// ❌ Wrong - dollars
$gateway->createPayment($cart, ['amount' => 99.99]);

// ✅ Correct - cents
$gateway->createPayment($cart, ['amount' => 9999]);

// ✅ Use normalizer
use AIArmada\CommerceSupport\Support\MoneyNormalizer;
$amount = MoneyNormalizer::toCents($input);
```

### PaymentStatus Not Updating

**Cause:** Status transitions not validated.

```php
$currentStatus = PaymentStatus::PENDING;
$newStatus = PaymentStatus::REFUNDED;

if (! $currentStatus->canTransitionTo($newStatus)) {
    // Can't refund a pending payment
    throw new \Exception('Invalid status transition');
}
```

## Targeting Engine Issues

### Rules Not Matching

**Debug:** Log evaluation details.

```php
$engine = app(TargetingEngine::class);
$context = TargetingContext::fromCart($cart);

// Check context values
Log::debug('Targeting context', [
    'cart_value' => $context->getCartValue(),
    'user_segments' => $context->getUserSegments(),
    'channel' => $context->getChannel(),
]);

// Evaluate with logging
foreach ($rules as $index => $rule) {
    $evaluator = $engine->getEvaluator($rule['type']);
    $result = $evaluator->evaluate($rule, $context);
    Log::debug("Rule {$index} ({$rule['type']})", ['result' => $result]);
}
```

### "Unknown rule type: xyz"

**Cause:** Evaluator not registered.

```php
// Check available evaluators
$engine = app(TargetingEngine::class);
dd($engine->getRegisteredTypes());

// Register custom evaluator
$engine->registerEvaluator(new MyCustomEvaluator());
```

### Custom Expression Parse Error

**Cause:** Invalid boolean expression syntax.

```php
// ❌ Invalid
'rule1 && rule2' // Use AND, not &&
'(rule1 OR rule2' // Missing closing paren

// ✅ Valid
'rule1 AND rule2'
'(rule1 OR rule2)'
'rule1 AND (rule2 OR rule3)'
'NOT rule1 AND rule2'
```

## Webhook Issues

### Signature Validation Failed

**Cause 1:** Wrong secret.

```php
// Verify secret matches provider
dd(config('webhook-client.configs.0.signing_secret'));
```

**Cause 2:** Payload modified before validation.

```php
// Ensure raw content used for signature
$signature = hash_hmac('sha256', $request->getContent(), $secret);
// NOT: hash_hmac('sha256', json_encode($request->all()), $secret)
```

**Cause 3:** Different signature algorithm.

```php
// Check provider's algorithm
// Stripe uses HMAC-SHA256 with special format
// CHIP might use different approach
```

### Webhooks Not Processing

**Cause 1:** Queue not running.

```bash
php artisan queue:work
```

**Cause 2:** Job failing silently.

```php
// Check failed jobs
php artisan queue:failed

// Add failed() method to job
public function failed(\Throwable $exception): void
{
    Log::error('Webhook failed', [
        'webhook_id' => $this->webhookCall->id,
        'error' => $exception->getMessage(),
    ]);
}
```

### Duplicate Webhook Processing

**Solution:** Implement idempotency.

```php
protected function processEvent(string $eventType, array $payload): void
{
    $eventId = $payload['event_id'];

    // Check if already processed
    $processed = Cache::has("webhook:processed:{$eventId}");
    if ($processed) {
        return;
    }

    // Process...

    // Mark as processed
    Cache::put("webhook:processed:{$eventId}", true, now()->addDays(7));
}
```

## Isolation Primitive Issues

### OwnerCache bulk cleanup does nothing

**Cause:** The active cache driver does not support tags.

`OwnerCache::forgetOwner()` only bulk-clears owner groups on tag-capable stores such as Redis or Memcached. On file/array/database stores it is intentionally a no-op.

**Solution:**

- use explicit `OwnerCache::forget($owner, $logicalKey)` when you know the keys to clear
- or move owner-sensitive caching to a tag-capable store if owner-wide invalidation is required

### Non-Eloquent owner object rejected by OwnerCache / OwnerFilesystem

**Cause:** The helper received an arbitrary object instead of a model or supported owner reference.

**Solution:** implement `OwnerScopeIdentifiable` on your adapter/reference object:

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

### OwnerContextJob cannot resolve explicit owner payload

**Cause:** The job payload does not expose owner data through either:

- a public owner-bearing model property, or
- public `owner_type` + `owner_id` fields

**Solution:** expose one of those patterns publicly so the trait can resolve the owner before `performJob()` runs.

## Health Check Issues

### Health Checks Not Showing

**Cause:** Not registered.

```php
// In service provider boot()
use Spatie\Health\Facades\Health;

Health::checks([
    CartHealthCheck::new(),
]);
```

### Health Check Timing Out

**Cause:** Slow queries.

```php
// Add timeout
protected function performCheck(): Result
{
    return Cache::remember('health:cart', 60, function () {
        // Expensive check cached for 60 seconds
        return Result::make()->ok('Cart health cached');
    });
}
```

## Migration Issues

### "Column already exists"

**Cause:** Migration run twice.

```bash
# Check migrations table
php artisan migrate:status

# Reset specific migration
php artisan migrate:rollback --step=1
```

### Wrong JSON Column Type

**Cause:** SQLite doesn't support native JSON.

```php
// In config
'database' => [
    'json_column_type' => env('DB_CONNECTION') === 'sqlite' ? 'text' : 'json',
],
```

## Performance Issues

### Slow Owner Scope Queries

**Solution:** Add database index.

```php
// In migration
Schema::table('products', function (Blueprint $table) {
    $table->index(['owner_type', 'owner_id']);
});
```

### Computed Values Recalculating

**Cause:** Cache cleared unexpectedly.

```php
// Check if something is triggering saves
$cart->getTotal(); // Cached

// This clears cache
$cart->touch(); // Triggers save
$cart->update(['notes' => 'test']); // Triggers save

$cart->getTotal(); // Recalculated
```

## Getting Help

1. **Check logs:** `storage/logs/laravel.log`
2. **Enable debug mode:** `APP_DEBUG=true`
3. **Use Tinker:** `php artisan tinker`
4. **Search docs:** Use `search-docs` tool for specific issues
5. **Check tests:** Look at test files for usage examples
