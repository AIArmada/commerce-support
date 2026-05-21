---
title: Payment Contracts
---

# Payment Contracts

Commerce Support defines universal payment contracts that allow any payment gateway to integrate with the commerce ecosystem. These contracts provide a consistent interface regardless of whether you're using Stripe, CHIP, PayPal, or a custom provider.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Payment Flow                              │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  CheckoutableInterface ──► PaymentGatewayInterface           │
│         │                         │                          │
│         ▼                         ▼                          │
│  CustomerInterface         PaymentIntentInterface            │
│  LineItemInterface         PaymentStatus (enum)              │
│                                   │                          │
│                                   ▼                          │
│                        WebhookHandlerInterface               │
│                        WebhookPayload (DTO)                  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

## PaymentGatewayInterface

The core contract that all payment gateways must implement:

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookHandlerInterface;
use Akaunting\Money\Money;

interface PaymentGatewayInterface
{
    public function getName(): string;

    public function getDisplayName(): string;

    public function isTestMode(): bool;

    public function createPayment(
        CheckoutableInterface $checkoutable,
        ?CustomerInterface $customer = null,
        array $options = []
    ): PaymentIntentInterface;

    public function getPayment(string $paymentId): PaymentIntentInterface;

    public function cancelPayment(string $paymentId): PaymentIntentInterface;

    public function refundPayment(
        string $paymentId,
        ?Money $amount = null
    ): PaymentIntentInterface;

    public function capturePayment(
        string $paymentId,
        ?Money $amount = null
    ): PaymentIntentInterface;

    public function getPaymentMethods(array $filters = []): array;

    public function supports(string $feature): bool;

    public function getWebhookHandler(): WebhookHandlerInterface;
}
```

### Implementing a Gateway

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;

class StripeGateway implements PaymentGatewayInterface
{
    public function __construct(
        private StripeClient $stripe
    ) {}

    public function createPayment(
        CheckoutableInterface $checkoutable,
        ?CustomerInterface $customer = null,
        array $options = []
    ): PaymentIntentInterface {
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $checkoutable->getTotalInCents(),
            'currency' => strtolower($checkoutable->getCurrency()),
            'customer' => $customer?->getGatewayId(),
            'metadata' => $checkoutable->getMetadata(),
        ]);

        return new StripePaymentIntent($intent);
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, [
            'partial_refund',
            'delayed_capture',
            'payment_methods',
        ]);
    }

    // ... other methods
}
```

## PaymentIntentInterface

Represents the result of a payment operation:

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;

interface PaymentIntentInterface
{
    public function getId(): string;

    public function getStatus(): PaymentStatus;

    public function getAmount(): int;

    public function getCurrency(): string;

    public function getGatewayReference(): ?string;

    public function getClientSecret(): ?string;

    public function getCheckoutUrl(): ?string;

    public function getMetadata(): array;

    public function getRawResponse(): array;
}
```

### Example Implementation

```php
class StripePaymentIntent implements PaymentIntentInterface
{
    public function __construct(
        private \Stripe\PaymentIntent $intent
    ) {}

    public function getId(): string
    {
        return $this->intent->id;
    }

    public function getStatus(): PaymentStatus
    {
        return match ($this->intent->status) {
            'succeeded' => PaymentStatus::PAID,
            'processing' => PaymentStatus::PROCESSING,
            'requires_payment_method' => PaymentStatus::REQUIRES_ACTION,
            'requires_action' => PaymentStatus::REQUIRES_ACTION,
            'canceled' => PaymentStatus::CANCELLED,
            default => PaymentStatus::PENDING,
        };
    }

    public function getClientSecret(): ?string
    {
        return $this->intent->client_secret;
    }

    // ... other methods
}
```

## PaymentStatus Enum

A comprehensive enum covering all payment states with transition enforcement:

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;

enum PaymentStatus: string
{
    case CREATED = 'created';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case REQUIRES_ACTION = 'requires_action';
    case AUTHORIZED = 'authorized';
    case PAID = 'paid';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case REFUNDED = 'refunded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case DISPUTED = 'disputed';

    // Status checks
    public function isSuccessful(): bool;
    public function isPending(): bool;
    public function isTerminal(): bool;
    public function isRefundable(): bool;
    
    // Transition enforcement
    public function canTransitionTo(PaymentStatus $target): bool;
    public function getAllowedTransitions(): array;
    public function transitionTo(PaymentStatus $target): PaymentStatus;
}
```

### Status Flow

```
CREATED ──► PENDING ──► PROCESSING ──► AUTHORIZED ──► PAID
                │              │              │          │
                │              │              │          ▼
                │              │              │    PARTIALLY_REFUNDED
                │              │              │          │
                │              │              │          ▼
                ▼              ▼              ▼       REFUNDED
             EXPIRED        FAILED       CANCELLED

                    ┌────────────────┐
                    │    DISPUTED    │ (can occur after PAID)
                    └────────────────┘
```

### Using Status Methods

```php
$status = $intent->getStatus();

if ($status->isSuccessful()) {
    // PAID, PARTIALLY_REFUNDED
}

if ($status->isPending()) {
    // CREATED, PENDING, PROCESSING, REQUIRES_ACTION, AUTHORIZED
}

if ($status->isTerminal()) {
    // PAID, REFUNDED, FAILED, CANCELLED, EXPIRED
}

if ($status->isRefundable()) {
    // PAID, PARTIALLY_REFUNDED
}
```

### Transition Enforcement

PaymentStatus enforces valid state transitions to prevent invalid payment flows:

```php
// Check if transition is allowed
if (PaymentStatus::PENDING->canTransitionTo(PaymentStatus::PAID)) {
    // Valid transition
}

// Get all allowed transitions from current status
$allowed = PaymentStatus::PENDING->getAllowedTransitions();
// [PROCESSING, REQUIRES_ACTION, AUTHORIZED, PAID, FAILED, CANCELLED, EXPIRED]

// Transition with validation (throws on invalid)
try {
    $newStatus = PaymentStatus::PENDING->transitionTo(PaymentStatus::PAID);
    // Success
} catch (InvalidArgumentException $e) {
    // Invalid transition
}

// Invalid transitions throw exceptions
PaymentStatus::REFUNDED->transitionTo(PaymentStatus::PAID);
// Throws: Cannot transition from 'refunded' to 'paid'
```

### HasPaymentStatus Trait

Use this trait on models to automatically enforce transitions:

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

// Transitions are automatically validated
$order = Order::find($id);
$order->payment_status = PaymentStatus::PENDING;
$order->save(); // OK

$order->payment_status = PaymentStatus::PAID;
$order->save(); // OK - valid transition

$order->payment_status = PaymentStatus::CREATED;
$order->save(); // Throws - invalid transition!

// Or use convenience methods
$order->transitionPaymentStatus(PaymentStatus::PAID); // Validates and saves
$order->markAsPaid();      // Convenience method
$order->markAsRefunded();  // Convenience method
$order->markAsFailed();    // Convenience method
```

## CheckoutableInterface

Defines what can be checked out:

```php
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;

interface CheckoutableInterface
{
    public function getCheckoutId(): string;

    public function getCustomer(): CustomerInterface;

    /** @return array<LineItemInterface> */
    public function getLineItems(): array;

    public function getTotalInCents(): int;

    public function getCurrency(): string;

    public function getMetadata(): array;

    public function getSuccessUrl(): ?string;

    public function getCancelUrl(): ?string;
}
```

### Implementing on Cart

```php
class Cart extends Model implements CheckoutableInterface
{
    public function getCheckoutId(): string
    {
        return $this->id;
    }

    public function getCustomer(): CustomerInterface
    {
        return new CartCustomer($this->user);
    }

    public function getLineItems(): array
    {
        return $this->items->map(
            fn (CartItem $item) => new CartLineItem($item)
        )->all();
    }

    public function getTotalInCents(): int
    {
        return $this->total; // Already in cents
    }

    public function getCurrency(): string
    {
        return $this->currency ?? config('commerce.defaults.currency');
    }
}
```

## CustomerInterface

Customer data for payment providers:

```php
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;

interface CustomerInterface
{
    public function getCustomerId(): string;

    public function getEmail(): ?string;

    public function getName(): ?string;

    public function getPhone(): ?string;

    public function getGatewayId(?string $gateway = null): ?string;

    public function getMetadata(): array;
}
```

## LineItemInterface

Individual line items in a checkout:

```php
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;

interface LineItemInterface
{
    public function getLineItemId(): string;

    public function getName(): string;

    public function getDescription(): ?string;

    public function getQuantity(): int;

    public function getUnitPriceInCents(): int;

    public function getTotalInCents(): int;

    public function getMetadata(): array;
}
```

## Webhook Handling

### WebhookHandlerInterface

```php
use AIArmada\CommerceSupport\Contracts\Payment\WebhookHandlerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookPayload;

interface WebhookHandlerInterface
{
    public function handle(WebhookPayload $payload): void;

    public function supports(string $eventType): bool;
}
```

### WebhookPayload DTO

```php
use AIArmada\CommerceSupport\Contracts\Payment\WebhookPayload;

$payload = new WebhookPayload(
    eventType: 'payment.completed',
    eventId: 'evt_123',
    paymentId: 'pi_abc',
    status: PaymentStatus::PAID,
    amount: 10000,
    currency: 'USD',
    rawPayload: $request->all()
);

// Usage
$payload->eventType;  // 'payment.completed'
$payload->status;     // PaymentStatus::PAID
$payload->rawPayload; // Original webhook data
```

## MoneyNormalizer

Helper for consistent money handling:

```php
use AIArmada\CommerceSupport\Support\MoneyNormalizer;

// Convert to cents
MoneyNormalizer::toCents(99.99);      // 9999
MoneyNormalizer::toCents('$99.99');   // 9999
MoneyNormalizer::toCents(9999);       // 9999 (already cents)
MoneyNormalizer::toCents(null);       // 0

// Convert to dollars
MoneyNormalizer::toDollars(9999);     // 99.99

// Format for display
MoneyNormalizer::format(9999, 'USD'); // Uses Money library
```

### Supported Currency Symbols

The normalizer strips these symbols: `$`, `€`, `£`, `¥`, `₹`, `RM`, `₱`, `₩`, `฿`, `₫`, `₪`, `₨`, `R$`, `kr`, `zł`

## Integration Example

Complete example using all contracts:

```php
class PaymentService
{
    public function __construct(
        private PaymentGatewayInterface $gateway
    ) {}

    public function checkout(Cart $cart): PaymentIntentInterface
    {
        // Cart implements CheckoutableInterface
        $intent = $this->gateway->createPayment($cart, null, [
            'capture_method' => 'automatic',
        ]);

        // In a controller, redirect using $intent->getCheckoutUrl() when pending.
        // This service returns the intent only.

        return $intent;
    }

    public function handleWebhook(WebhookPayload $payload): void
    {
        $intent = $this->gateway->getPayment($payload->paymentId);

        if ($intent->getStatus()->isSuccessful()) {
            // Complete order
            Order::where('payment_id', $payload->paymentId)
                ->first()
                ->markAsPaid();
        }
    }
}
```
