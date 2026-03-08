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

Add the concern to models requiring audit trails:

```php
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;

class Order extends Model
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
class Order extends Model
{
    use HasCommerceAudit;

    protected array $auditExclude = [
        'remember_token',
        'internal_notes',
    ];
}
```

### Custom Audit Events

```php
class Order extends Model
{
    use HasCommerceAudit;

    protected array $auditEvents = [
        'created',
        'updated',
        'deleted',
        'restored',
        'refunded' => 'handleRefundedAudit',  // Custom
    ];

    public function handleRefundedAudit(): array
    {
        return [
            'old_values' => ['refunded' => false],
            'new_values' => ['refunded' => true],
        ];
    }
}

// Trigger custom event
$order->auditEvent = 'refunded';
$order->save();
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

The trait provides convenience methods:

```php
class Order extends Model
{
    use LogsCommerceActivity;

    public function markAsPaid(PaymentIntent $intent): void
    {
        $this->update(['status' => 'paid']);

        $this->logCommerceActivity('paid', [
            'payment_id' => $intent->getId(),
            'amount' => $intent->getAmount(),
        ]);
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
class Refund extends Model
{
    use HasCommerceAudit;

    protected array $auditInclude = [
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
class Order extends Model
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

    public function pay(PaymentIntent $intent): void
    {
        $this->update([
            'status' => 'paid',
            'payment_id' => $intent->getId(),
        ]);
        // HasCommerceAudit: Records status + payment_id change

        activity('commerce:payments')
            ->performedOn($this)
            ->withProperties([
                'gateway' => $intent->getGatewayReference(),
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
