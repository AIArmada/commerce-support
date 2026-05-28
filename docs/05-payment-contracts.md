---
title: Payment Contracts
---

# Payment Contracts

Commerce Support defines two layers for payments:

- gateway contracts for creating, reading, refunding, and reconciling payments
- payment-subject contracts for resolving which local model and customer payload should be sent to a gateway

This page documents the current contracts used by `checkout`, `chip`, `cashier`, `cashier-chip`, and any custom gateway integrations.

## Architecture overview

```text
CheckoutableInterface + LineItemInterface
    │
    ├── PaymentSubjectContext
    │       │
    │       └── PaymentSubjectResolverInterface
    │               ├── customers driver (if installed)
    │               └── guest fallback driver
    │
    └── PaymentGatewayInterface
            ├── createPayment(...)
            ├── getPayment(...)
            ├── refundPayment(...)
            ├── capturePayment(...)
            └── getWebhookHandler()
                    └── WebhookPayload
```

## Payment subject resolution

The payment-subject layer sits between checkout state and the gateway. Its job is to answer two questions before a payment is created:

1. which local model should be treated as the payment subject
2. which normalized customer data should be passed to the gateway

That resolution is gateway-aware, owner-aware, and package-extensible.

### PaymentSubjectContext

`PaymentSubjectContext` is the input object passed to subject drivers.

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectContext;

$context = new PaymentSubjectContext(
    gateway: 'chip',
    actor: $user,
    subject: $explicitSubject,
    sessionCustomer: $checkoutSession->customer,
    sessionBillable: $checkoutSession->billable,
    billingData: $checkoutSession->billing_data ?? [],
    shippingData: $checkoutSession->shipping_data ?? [],
    metadata: [
        'checkout_session_id' => $checkoutSession->id,
    ],
    owner: $owner,
    source: 'checkout',
);
```

### PaymentSubjectDriverInterface

Drivers participate in subject resolution in descending priority order.

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectContext;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectDriverInterface;
use AIArmada\CommerceSupport\Contracts\Payment\ResolvedPaymentSubject;

interface PaymentSubjectDriverInterface
{
    public function getIdentifier(): string;

    public function getPriority(): int;

    public function supports(PaymentSubjectContext $context): bool;

    public function resolve(PaymentSubjectContext $context): ?ResolvedPaymentSubject;
}
```

Use a higher priority for domain-aware drivers. For example, `aiarmada/customers` registers a customer-aware driver, while Commerce Support registers the guest fallback driver at a low priority.

### PaymentSubjectResolverInterface

The resolver owns driver registration and executes them in sorted priority order until one returns a result.

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectDriverInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectResolverInterface;
use AIArmada\CommerceSupport\Contracts\Payment\ResolvedPaymentSubject;

interface PaymentSubjectResolverInterface
{
    public function register(PaymentSubjectDriverInterface $driver): void;

    public function resolve(PaymentSubjectContext $context): ?ResolvedPaymentSubject;

    /** @return array<int, PaymentSubjectDriverInterface> */
    public function all(): array;
}
```

Commerce Support binds the resolver as a singleton and registers `GuestPaymentSubjectDriver` by default. Other packages may register additional drivers during boot.

### ResolvedPaymentSubject

`ResolvedPaymentSubject` is the normalized result of subject resolution.

```php
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\ResolvedPaymentSubject;
use Illuminate\Database\Eloquent\Model;

final readonly class ResolvedPaymentSubject
{
    public function __construct(
        public ?Model $subject,
        public ?CustomerInterface $paymentCustomer,
        public bool $isGuest,
        public string $resolvedBy,
        public array $metadata = [],
    ) {}
}
```

The important fields in practice are:

- `subject` — the resolved local model, often a `Customer` when `aiarmada/customers` is installed
- `paymentCustomer` — normalized customer details to send to the gateway
- `isGuest` — whether the flow resolved a guest customer
- `resolvedBy` — the driver identifier that produced the result

### PaymentCustomerData

`PaymentCustomerData` is the stock immutable implementation of `CustomerInterface` used by the built-in drivers.

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentCustomerData;

$customer = new PaymentCustomerData(
    email: 'guest@example.com',
    name: 'Guest Customer',
    phone: '+60123456789',
    country: 'MY',
    billingStreetAddress: '123 Example Street',
    billingCity: 'Kuala Lumpur',
    billingState: 'Kuala Lumpur',
    billingPostalCode: '50000',
    billingCountry: 'MY',
    shippingStreetAddress: '123 Example Street',
    shippingCity: 'Kuala Lumpur',
    shippingState: 'Kuala Lumpur',
    shippingPostalCode: '50000',
    shippingCountry: 'MY',
    gatewayCustomerId: null,
    metadata: ['checkout_session_id' => '...'],
);
```

### Resolving a subject before payment

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectContext;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectResolverInterface;

$resolved = app(PaymentSubjectResolverInterface::class)->resolve(
    new PaymentSubjectContext(
        gateway: 'chip',
        actor: $user,
        sessionCustomer: $checkoutSession->customer,
        sessionBillable: $checkoutSession->billable,
        billingData: $checkoutSession->billing_data ?? [],
        shippingData: $checkoutSession->shipping_data ?? [],
        metadata: ['checkout_session_id' => $checkoutSession->id],
        owner: $owner,
        source: 'checkout',
    )
);

if ($resolved === null || $resolved->paymentCustomer === null) {
    throw new RuntimeException('Unable to resolve payment customer.');
}

$paymentCustomer = $resolved->paymentCustomer;
$subject = $resolved->subject;
```

## PaymentGatewayInterface

Every gateway implementation must satisfy `PaymentGatewayInterface`.

```php
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
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

    public function refundPayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface;

    public function capturePayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface;

    /** @return array<string, mixed> */
    public function getPaymentMethods(array $filters = []): array;

    public function supports(string $feature): bool;

    public function getWebhookHandler(): WebhookHandlerInterface;
}
```

### Common gateway features

`supports()` is intentionally string-based so packages can negotiate capabilities without sharing concrete implementations. Common feature flags include:

- `refunds`
- `partial_refunds`
- `pre_authorization`
- `recurring`
- `webhooks`
- `hosted_checkout`
- `embedded_checkout`

## PaymentIntentInterface

`PaymentIntentInterface` normalizes the gateway response after a payment is created or retrieved.

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use Akaunting\Money\Money;
use DateTimeInterface;

interface PaymentIntentInterface
{
    public function getPaymentId(): string;

    public function getReference(): ?string;

    public function getAmount(): Money;

    public function getStatus(): PaymentStatus;

    public function getCheckoutUrl(): ?string;

    public function getSuccessUrl(): ?string;

    public function getFailureUrl(): ?string;

    public function isPaid(): bool;

    public function isPending(): bool;

    public function isFailed(): bool;

    public function isCancelled(): bool;

    public function isRefunded(): bool;

    public function getRefundableAmount(): Money;

    public function isTest(): bool;

    public function getGatewayName(): string;

    public function getCreatedAt(): DateTimeInterface;

    public function getUpdatedAt(): DateTimeInterface;

    public function getPaidAt(): ?DateTimeInterface;

    /** @return array<string, mixed> */
    public function getMetadata(): array;

    /** @return array<string, mixed> */
    public function getRawResponse(): array;
}
```

### Using a normalized payment intent

```php
$payment = $gateway->createPayment($checkoutable, $paymentCustomer, [
    'success_url' => route('checkout.payment.success'),
    'failure_url' => route('checkout.payment.failure'),
]);

if ($payment->getCheckoutUrl() !== null) {
    return redirect()->away($payment->getCheckoutUrl());
}

if ($payment->isPaid()) {
    // Mark the local payment or order as paid.
}
```

## PaymentStatus

`PaymentStatus` is the normalized state machine for payment lifecycles.

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
}
```

### Status helpers

The enum exposes helpers for common control flow:

- `isSuccessful()` — `AUTHORIZED`, `PAID`, `PARTIALLY_REFUNDED`
- `isPending()` — `CREATED`, `PENDING`, `PROCESSING`, `REQUIRES_ACTION`
- `isTerminal()` — `PAID`, `REFUNDED`, `FAILED`, `CANCELLED`, `EXPIRED`
- `isRefundable()` — `PAID`, `PARTIALLY_REFUNDED`
- `isCancellable()` — `CREATED`, `PENDING`, `AUTHORIZED`

### Transition enforcement

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;

if ($currentStatus->canTransitionTo(PaymentStatus::PAID)) {
    $currentStatus = $currentStatus->transitionTo(PaymentStatus::PAID);
}

$allowed = PaymentStatus::PENDING->getAllowedTransitions();
```

`transitionTo()` throws an `InvalidArgumentException` when a transition is not allowed.

## CheckoutableInterface

`CheckoutableInterface` describes the chargeable aggregate sent to a gateway.

```php
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use Akaunting\Money\Money;

interface CheckoutableInterface
{
    /** @return iterable<LineItemInterface> */
    public function getCheckoutLineItems(): iterable;

    public function getCheckoutSubtotal(): Money;

    public function getCheckoutDiscount(): Money;

    public function getCheckoutTax(): Money;

    public function getCheckoutTotal(): Money;

    public function getCheckoutCurrency(): string;

    public function getCheckoutReference(): string;

    public function getCheckoutNotes(): ?string;

    /** @return array<string, mixed> */
    public function getCheckoutMetadata(): array;
}
```

## LineItemInterface

Each line item is also normalized so gateways can build itemized purchases consistently.

```php
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use Akaunting\Money\Money;

interface LineItemInterface
{
    public function getLineItemId(): string;

    public function getLineItemName(): string;

    public function getLineItemPrice(): Money;

    public function getLineItemQuantity(): int | float;

    public function getLineItemDiscount(): Money;

    public function getLineItemTaxPercent(): float;

    public function getLineItemSubtotal(): Money;

    public function getLineItemCategory(): ?string;

    /** @return array<string, mixed> */
    public function getLineItemMetadata(): array;
}
```

## CustomerInterface

`CustomerInterface` is the normalized customer payload that gateways receive.

```php
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;

interface CustomerInterface
{
    public function getCustomerEmail(): string;

    public function getCustomerName(): ?string;

    public function getCustomerPhone(): ?string;

    public function getCustomerCountry(): ?string;

    public function getBillingStreetAddress(): ?string;

    public function getBillingCity(): ?string;

    public function getBillingState(): ?string;

    public function getBillingPostalCode(): ?string;

    public function getBillingCountry(): ?string;

    public function hasShippingAddress(): bool;

    public function getShippingStreetAddress(): ?string;

    public function getShippingCity(): ?string;

    public function getShippingState(): ?string;

    public function getShippingPostalCode(): ?string;

    public function getShippingCountry(): ?string;

    public function getGatewayCustomerId(): ?string;

    /** @return array<string, mixed> */
    public function getCustomerMetadata(): array;
}
```

## Webhook contracts

### WebhookHandlerInterface

Gateways normalize incoming webhook requests through `WebhookHandlerInterface`.

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookHandlerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookPayload;
use Illuminate\Http\Request;

interface WebhookHandlerInterface
{
    public function verifyWebhook(Request $request): bool;

    public function parseWebhook(Request $request): WebhookPayload;

    public function getEventType(Request $request): string;

    public function isPaymentEvent(Request $request): bool;

    public function getPaymentFromWebhook(Request $request): ?PaymentIntentInterface;
}
```

### WebhookPayload

`WebhookPayload` is the normalized DTO returned by `parseWebhook()`.

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookPayload;

$payload = new WebhookPayload(
    eventType: 'payment.completed',
    paymentId: 'purchase_123',
    status: PaymentStatus::PAID,
    reference: 'checkout-session-uuid',
    gatewayName: 'chip',
    occurredAt: now(),
    rawData: request()->all(),
);
```

Helper methods:

- `isPaymentSuccess()`
- `isPaymentFailed()`
- `isRefund()`
- `isCancellation()`
- `get($key, $default = null)` for nested raw payload access

## End-to-end example

```php
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectContext;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectResolverInterface;

final class CreateCheckoutPaymentAction
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private PaymentSubjectResolverInterface $subjectResolver,
    ) {}

    public function handle(object $checkoutSession, object $checkoutable, mixed $user = null): string
    {
        $resolved = $this->subjectResolver->resolve(new PaymentSubjectContext(
            gateway: $this->gateway->getName(),
            actor: $user,
            sessionCustomer: $checkoutSession->customer,
            sessionBillable: $checkoutSession->billable,
            billingData: $checkoutSession->billing_data ?? [],
            shippingData: $checkoutSession->shipping_data ?? [],
            metadata: [
                'checkout_session_id' => $checkoutSession->id,
            ],
            owner: $checkoutSession->owner,
            source: 'checkout',
        ));

        $payment = $this->gateway->createPayment(
            $checkoutable,
            $resolved?->paymentCustomer,
            [
                'success_url' => route('checkout.payment.success'),
                'failure_url' => route('checkout.payment.failure'),
            ],
        );

        return $payment->getCheckoutUrl() ?? route('checkout.payment.success');
    }
}
```

## Related docs

- [Usage](04-usage.md)
- [Webhooks](08-webhooks.md)
- [`aiarmada/customers` overview](../../customers/docs/01-overview.md)
- [`aiarmada/checkout` payment flow](../../checkout/docs/07-payment-flow.md)
