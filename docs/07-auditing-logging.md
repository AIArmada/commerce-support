---
title: Auditing & Logging
---

# Auditing & Logging

Commerce Support provides two complementary systems for tracking changes and activities:

1. **Auditing** - Compliance-focused, immutable record of data changes (via `owen-it/laravel-auditing`)
2. **Activity Logging** - Business event tracking with human-readable descriptions (via `spatie/laravel-activitylog`)

## When to Use Each

| Use Case | Auditing | Activity Logging |
|----------|----------|------------------|
| Who changed a record | ✅ | |
| What fields changed | ✅ | |
| PCI/SOX compliance | ✅ | |
| Order placed | | ✅ |
| User logged in | | ✅ |
| Payment processed | | ✅ |
| Admin actions | | ✅ |

## Auditing (Compliance)

### Setup

Add the concern to models requiring audit trails. The model must also implement
`Auditable`: owen-it's `AuditableObserver` type-hints
`OwenIt\Auditing\Contracts\Auditable`, so a model without it never gets audited.

```php
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Contracts\Auditable;

class Order extends Model implements Auditable
{
    use HasCommerceAudit;
}
```

### How It Works

The trait extends `owen-it/laravel-auditing` with commerce-specific defaults:

```php
// Every change is automatically logged
$order = Order::create([
    'total' => 10000,
    'status' => 'pending',
]);

// Creates audit record:
// - event: 'created'
// - auditable: Order
// - old_values: []
// - new_values: ['total' => 10000, 'status' => 'pending']
// - user: Current authenticated user

$order->update(['status' => 'paid']);

// Creates audit record:
// - event: 'updated'
// - old_values: ['status' => 'pending']
// - new_values: ['status' => 'paid']
```

### Excluding Fields

```php
class Order extends Model implements Auditable
{
    use HasCommerceAudit;

    // HasCommerceAudit declares $auditExclude untyped, so redeclare it untyped too.
    // Adding an `array` type here is a fatal error.
    protected $auditExclude = [
        'remember_token',
        'internal_notes',
    ];
}
```

Credentials and PII (passwords, tokens, secrets, payment data, names,
emails, phones, addresses, dates of birth — see
`SensitiveAttributes::list()`) are excluded from audits and redacted from
audit payloads by default. Likewise, `LogsCommerceActivity` logs the
fillable list minus those sensitive attributes unless the model defines an
explicit allowlist via `getLoggableAttributes()`.

### Custom Audit Events

```php
class Order extends Model implements Auditable
{
    use HasCommerceAudit;

    // String keys are event-name patterns; the value is the attribute-getter
    // method that supplies old_values/new_values for that event.
    protected $auditEvents = [
        'created',
        'updated',
        'deleted',
        'refunded' => 'getRefundedAuditAttributes',
    ];

    protected function getRefundedAuditAttributes(): array
    {
        return [
            'old_values' => ['refunded' => false],
            'new_values' => ['refunded' => true],
        ];
    }
}
```

> **warning**
> Assigning `$model->auditEvent` and calling `save()` does **not** record a custom audit. owen-it has no `saved` hook, and the `updated` observer overwrites `auditEvent`. Use `recordCustomAudit()` below instead.

```php
$order->recordCustomAudit('refunded', ['refunded' => false], ['refunded' => true]);
```

### Retrieving Audit History

```php
// Get all audits for a model
$audits = $order->audits;

// Get audits with user
$audits = $order->audits()
    ->with('user')
    ->latest()
    ->get();

// Filter by event
$updates = $order->audits()
    ->where('event', 'updated')
    ->get();

// Check what changed
foreach ($audits as $audit) {
    echo "Changed by: " . $audit->user?->name;
    echo "Old values: " . json_encode($audit->old_values);
    echo "New values: " . json_encode($audit->new_values);
}
```

### Custom audits

Model-level and relation-level changes that Eloquent events never see (related-state
edits from admin pages, sync operations) audit through explicit custom events:

```php
$order->recordCustomAudit('notes_merged', ['notes' => $before], ['notes' => $after]);

$order->recordCustomAuditDifferences('items_synced', $beforeSnapshot, $afterSnapshot);
```

`recordCustomAuditDifferences()` diffs two snapshots and records only the changes,
comparing through `PayloadDiff` so enums, dates, and identifier representations match
by meaning rather than PHP type. Empty diffs record nothing.

Redact sensitive attributes with `FixedValueRedactor` through owen-it's
`$attributeModifiers`:

```php
use AIArmada\CommerceSupport\Support\FixedValueRedactor;

protected array $attributeModifiers = [
    'password' => FixedValueRedactor::class,
    'token' => FixedValueRedactor::class,
];
```

## Activity Logging (Business Events)

### Setup

Add the concern to models:

```php
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

class Order extends Model
{
    use LogsCommerceActivity;
}
```

### Basic Logging

```php
// Simple log
activity()
    ->performedOn($order)
    ->log('Order was placed');

// With causer (who did it)
activity()
    ->performedOn($order)
    ->causedBy($user)
    ->log('Order was placed');

// With properties (additional data)
activity()
    ->performedOn($order)
    ->causedBy($user)
    ->withProperties([
        'total' => $order->total,
        'items_count' => $order->items->count(),
        'payment_method' => 'credit_card',
    ])
    ->log('Order was placed');
```

### Commerce-specific Helpers

`LogsCommerceActivity` supplies defaults through four overridable methods:
`getLoggableAttributes()`, `getActivityLogName()`, `getActivitylogOptions()`,
and `getDescriptionForEvent()`. There is no per-model `logCommerceActivity()`
method — log through the `activity()` helper:

```php
class Order extends Model
{
    use LogsCommerceActivity;

    protected function getActivityLogName(): string
    {
        return 'commerce:orders';
    }

    public function markAsPaid(PaymentIntentInterface $intent): void
    {
        $this->update(['status' => 'paid']);

        activity($this->getActivityLogName())
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties([
                'payment_id' => $intent->getPaymentId(),
                'amount' => $intent->getAmount(),
            ])
            ->log('paid');
    }
}
```

### Using Log Names

Organize activities by category:

```php
activity('commerce:orders')
    ->performedOn($order)
    ->log('Order placed');

activity('commerce:payments')
    ->performedOn($order)
    ->withProperties(['payment_id' => $paymentId])
    ->log('Payment received');

// Retrieve by log name
Activity::inLog('commerce:orders')
    ->forSubject($order)
    ->get();
```

### Custom Activity Events

```php
class Order extends Model
{
    use LogsCommerceActivity;

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            activity('commerce:orders')
                ->performedOn($order)
                ->causedBy(auth()->user())
                ->withProperties([
                    'total' => $order->total,
                    'currency' => $order->currency,
                ])
                ->log('created');
        });

        static::updated(function (Order $order) {
            if ($order->wasChanged('status')) {
                activity('commerce:orders')
                    ->performedOn($order)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'from_status' => $order->getOriginal('status'),
                        'to_status' => $order->status,
                    ])
                    ->log('status_changed');
            }
        });
    }
}
```

### Retrieving Activity

```php
use Spatie\Activitylog\Models\Activity;

// All activities for a model
$activities = Activity::forSubject($order)->get();

// Recent activities
$activities = Activity::inLog('commerce:orders')
    ->latest()
    ->limit(50)
    ->get();

// Activities by a user
$activities = Activity::causedBy($user)->get();

// Display activity
foreach ($activities as $activity) {
    echo $activity->description;  // 'Order was placed'
    echo $activity->causer?->name; // User who did it
    echo $activity->properties;    // Additional data
}
```

## Best Practices

### Separate Concerns

```php
class Order extends Model
{
    use HasCommerceAudit;       // Tracks data changes (compliance)
    use LogsCommerceActivity;   // Tracks business events (operational)
}
```

`HasCommerceAudit` supplies `getAuditInclude()`, `getAuditExclude()`,
`getAuditThreshold()` (default `100` records per model), `transformAudit()`,
`isAuditableAttribute()`, `restoreToAuditState()`, `recordCustomAudit()`, and
`recordCustomAuditDifferences()`. `LogsCommerceActivity` supplies
`getActivitylogOptions()`, `getDescriptionForEvent()`, `getLoggableAttributes()`,
and `getActivityLogName()`.

### Log Meaningful Events

```php
// ❌ Don't log low-value events
activity()->log('Order model accessed');

// ✅ Log meaningful business events
activity('commerce:orders')
    ->performedOn($order)
    ->withProperties(['reason' => $reason])
    ->log('Order cancelled by customer');
```

### Include Relevant Context

```php
// ❌ Missing context
activity()->log('Payment failed');

// ✅ Useful context
activity('commerce:payments')
    ->performedOn($order)
    ->withProperties([
        'gateway' => 'stripe',
        'error_code' => $exception->getCode(),
        'error_message' => $exception->getMessage(),
        'payment_method' => 'card_****4242',
    ])
    ->log('Payment failed');
```

### Audit Sensitive Operations

```php
class Refund extends Model implements Auditable
{
    use HasCommerceAudit;

    // Untyped: HasCommerceAudit declares $auditInclude untyped. Adding an
    // `array` type here is a fatal error.
    protected $auditInclude = [
        'order_id',
        'amount',
        'reason',
        'status',
        'processed_by',
    ];
}
```

## Integration Example

Complete order lifecycle tracking:

```php
class Order extends Model implements Auditable
{
    use HasCommerceAudit;
    use LogsCommerceActivity;

    public function place(): void
    {
        $this->update(['status' => 'pending']);
        // HasCommerceAudit: Records status change

        activity('commerce:orders')
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties([
                'total' => $this->total,
                'items' => $this->items->count(),
            ])
            ->log('Order placed');
    }

    public function pay(PaymentIntentInterface $intent): void
    {
        $this->update([
            'status' => 'paid',
            'payment_id' => $intent->getPaymentId(),
        ]);
        // HasCommerceAudit: Records status + payment_id change

        activity('commerce:payments')
            ->performedOn($this)
            ->withProperties([
                'gateway' => $intent->getGatewayName(),
                'amount' => $intent->getAmount(),
            ])
            ->log('Payment received');
    }

    public function refund(int $amount, string $reason): void
    {
        $this->update([
            'status' => 'refunded',
            'refunded_amount' => $amount,
        ]);
        // HasCommerceAudit: Records refund details

        activity('commerce:refunds')
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties([
                'amount' => $amount,
                'reason' => $reason,
            ])
            ->log('Order refunded');
    }
}
```
