<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

use DateTimeInterface;

/**
 * Standardized webhook payload from any payment gateway.
 */
final readonly class WebhookPayload
{
    /**
     * @param  array<string, mixed>  $rawData
     */
    public function __construct(
        public string $eventType,
        public string $paymentId,
        public PaymentStatus $status,
        public ?string $reference,
        public string $gatewayName,
        public DateTimeInterface $occurredAt,
        public array $rawData = [],
    ) {}

    /**
     * Check if this is a successful payment webhook.
     */
    public function isPaymentSuccess(): bool
    {
        return $this->status === PaymentStatus::PAID;
    }

    /**
     * Check if this is a failed payment webhook.
     */
    public function isPaymentFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }

    /**
     * Check if this is a refund webhook.
     */
    public function isRefund(): bool
    {
        return in_array($this->status, [
            PaymentStatus::REFUNDED,
            PaymentStatus::PARTIALLY_REFUNDED,
        ], true);
    }

    /**
     * Check if this is a cancellation webhook.
     */
    public function isCancellation(): bool
    {
        return $this->status === PaymentStatus::CANCELLED;
    }

    /**
     * Get a value from the raw webhook data.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->rawData, $key, $default);
    }
}
