---
title: Webhooks
---

# Webhooks

Commerce Support provides base classes for secure webhook handling, built on `spatie/laravel-webhook-client`. These utilities ensure consistent signature validation, idempotent processing, and proper error handling across all commerce packages.

## Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                     Webhook Flow                                  │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│   Incoming Request                                                │
│        │                                                          │
│        ▼                                                          │
│   CommerceWebhookProfile ──► Should process?                      │
│        │                                                          │
│        ▼                                                          │
│   CommerceSignatureValidator ──► Valid signature?                 │
│        │                                                          │
│        ▼                                                          │
│   WebhookCall (stored) ──► Queued for processing                  │
│        │                                                          │
│        ▼                                                          │
│   CommerceWebhookProcessor ──► Your processing logic              │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

## Quick Start

### 1. Configure Webhook Client

```php
// config/webhook-client.php
return [
    'configs' => [
        [
            'name' => 'chip',
            'signing_secret' => env('CHIP_WEBHOOK_SECRET'),
            'signature_header_name' => 'X-Signature',
            'signature_validator' => \AIArmada\CommerceSupport\Webhooks\CommerceSignatureValidator::class,
            'webhook_profile' => \AIArmada\CommerceSupport\Webhooks\CommerceWebhookProfile::class,
            'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
            'process_webhook_job' => \App\Jobs\ProcessChipWebhook::class,
        ],
    ],
];
```

### 2. Create Processor

```php
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use Spatie\WebhookClient\Models\WebhookCall;

class ProcessChipWebhook extends CommerceWebhookProcessor
{
    public function process(WebhookCall $webhookCall): void
    {
        $payload = $webhookCall->payload;
        $eventType = $payload['event'] ?? null;

        match ($eventType) {
            'payment.completed' => $this->handlePaymentCompleted($payload),
            'payment.failed' => $this->handlePaymentFailed($payload),
            'refund.created' => $this->handleRefundCreated($payload),
            default => null,
        };
    }

    private function handlePaymentCompleted(array $payload): void
    {
        $order = Order::where('payment_reference', $payload['id'])->first();

        if ($order && $order->status !== 'paid') {
            $order->markAsPaid();
        }
    }
}
```

### 3. Define Route

```php
// routes/web.php
Route::webhooks('webhooks/chip', 'chip');
```

## Signature Validation

### CommerceSignatureValidator

The default validator uses HMAC-SHA256:

```php
use AIArmada\CommerceSupport\Webhooks\CommerceSignatureValidator;

class CommerceSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $signature = $request->header($config->signatureHeaderName);
        $payload = $request->getContent();
        $secret = $config->signingSecret;

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
```

### Custom Validator

For gateways with different signature schemes:

```php
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;
use Illuminate\Http\Request;

class StripeSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $signature = $request->header('Stripe-Signature');
        $payload = $request->getContent();

        try {
            \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $config->signingSecret
            );
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
```

## Webhook Profile

### CommerceWebhookProfile

Controls which requests should be processed:

```php
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProfile;

class CommerceWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        // Always process if validation passes
        return true;
    }
}
```

### Custom Profile

Filter by event type or other criteria:

```php
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;
use Illuminate\Http\Request;

class PaymentWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        $payload = $request->all();
        $eventType = $payload['event'] ?? $payload['type'] ?? null;

        // Only process payment-related events
        return str_starts_with($eventType, 'payment.');
    }
}
```

## Processing Webhooks

### Base Processor

Extend `CommerceWebhookProcessor` for common behavior:

```php
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use Spatie\WebhookClient\Models\WebhookCall;

abstract class CommerceWebhookProcessor extends Job implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    abstract public function process(WebhookCall $webhookCall): void;

    public function handle(): void
    {
        $this->process($this->webhookCall);
    }
}
```

### Idempotent Processing

Always check for duplicate processing:

```php
class ProcessPaymentWebhook extends CommerceWebhookProcessor
{
    public function process(WebhookCall $webhookCall): void
    {
        $paymentId = $webhookCall->payload['payment_id'];

        // Idempotency check
        $order = Order::where('payment_id', $paymentId)->first();

        if (! $order) {
            return; // Order not found
        }

        if ($order->status === 'paid') {
            return; // Already processed
        }

        // Safe to process
        $order->markAsPaid();
    }
}
```

### Error Handling

```php
use AIArmada\CommerceSupport\Exceptions\WebhookVerificationException;

class ProcessPaymentWebhook extends CommerceWebhookProcessor
{
    public function process(WebhookCall $webhookCall): void
    {
        $payload = $webhookCall->payload;

        if (! isset($payload['payment_id'])) {
            throw WebhookVerificationException::invalidPayload(
                'Missing payment_id in webhook payload'
            );
        }

        // Process...
    }

    public function failed(\Throwable $exception): void
    {
        // Log failure
        Log::error('Webhook processing failed', [
            'webhook_id' => $this->webhookCall->id,
            'error' => $exception->getMessage(),
        ]);

        // Notify if needed
        if ($exception instanceof WebhookVerificationException) {
            // Alert security team
        }
    }
}
```

## WebhookPayload DTO

For structured payload handling in payment contexts:

```php
use AIArmada\CommerceSupport\Contracts\Payment\WebhookPayload;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;

$payload = new WebhookPayload(
    eventType: 'payment.completed',
    eventId: 'evt_abc123',
    paymentId: 'pi_xyz789',
    status: PaymentStatus::PAID,
    amount: 10000,
    currency: 'USD',
    rawPayload: $webhookCall->payload
);

// Use in handler
if ($payload->status->isSuccessful()) {
    $this->completeOrder($payload->paymentId);
}
```

## Testing Webhooks

### Simulating Webhook Calls

```php
use Spatie\WebhookClient\Models\WebhookCall;

it('processes payment completed webhook', function () {
    $payload = [
        'event' => 'payment.completed',
        'payment_id' => 'pi_123',
        'amount' => 10000,
    ];

    $webhookCall = WebhookCall::create([
        'name' => 'chip',
        'payload' => $payload,
    ]);

    $job = new ProcessChipWebhook($webhookCall);
    $job->handle();

    expect(Order::where('payment_id', 'pi_123')->first())
        ->status->toBe('paid');
});
```

### Testing Signature Validation

```php
it('validates webhook signature', function () {
    $payload = json_encode(['event' => 'test']);
    $secret = 'webhook_secret';
    $signature = hash_hmac('sha256', $payload, $secret);

    $response = $this
        ->withHeaders([
            'X-Signature' => $signature,
        ])
        ->postJson('/webhooks/chip', json_decode($payload, true));

    $response->assertOk();
});

it('rejects invalid signature', function () {
    $response = $this
        ->withHeaders([
            'X-Signature' => 'invalid',
        ])
        ->postJson('/webhooks/chip', ['event' => 'test']);

    $response->assertStatus(500);
});
```

## Best Practices

1. **Always validate signatures** - Never skip signature validation in production
2. **Implement idempotency** - Webhooks may be delivered multiple times
3. **Use queued jobs** - Process webhooks asynchronously
4. **Log everything** - Store raw payloads for debugging
5. **Set appropriate retries** - Configure sensible retry policies
6. **Monitor failures** - Alert on repeated webhook failures
7. **Respond quickly** - Return 200 before heavy processing
