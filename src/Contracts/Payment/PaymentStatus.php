<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

use InvalidArgumentException;

/**
 * Universal payment status enum for all payment gateways.
 *
 * This enum normalizes the various status values from different gateways
 * into a consistent set of statuses that can be used across the application.
 */
enum PaymentStatus: string
{
    /**
     * Payment has been created but not yet processed.
     */
    case CREATED = 'created';

    /**
     * Payment is pending/awaiting customer action or processing.
     */
    case PENDING = 'pending';

    /**
     * Payment is being processed by the gateway.
     */
    case PROCESSING = 'processing';

    /**
     * Payment requires additional authentication (3DS, OTP, etc.).
     */
    case REQUIRES_ACTION = 'requires_action';

    /**
     * Payment has been authorized but not captured.
     */
    case AUTHORIZED = 'authorized';

    /**
     * Payment has been captured/completed successfully.
     */
    case PAID = 'paid';

    /**
     * Payment has been partially refunded.
     */
    case PARTIALLY_REFUNDED = 'partially_refunded';

    /**
     * Payment has been fully refunded.
     */
    case REFUNDED = 'refunded';

    /**
     * Payment failed to process.
     */
    case FAILED = 'failed';

    /**
     * Payment was cancelled by user or merchant.
     */
    case CANCELLED = 'cancelled';

    /**
     * Payment expired before completion.
     */
    case EXPIRED = 'expired';

    /**
     * Payment was disputed/chargebacked.
     */
    case DISPUTED = 'disputed';

    /**
     * Check if status represents a successful payment.
     */
    public function isSuccessful(): bool
    {
        return in_array($this, [
            self::PAID,
            self::PARTIALLY_REFUNDED,
            self::AUTHORIZED,
        ], true);
    }

    /**
     * Check if status represents a pending state.
     */
    public function isPending(): bool
    {
        return in_array($this, [
            self::CREATED,
            self::PENDING,
            self::PROCESSING,
            self::REQUIRES_ACTION,
        ], true);
    }

    /**
     * Check if status represents a terminal/final state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::PAID,
            self::REFUNDED,
            self::FAILED,
            self::CANCELLED,
            self::EXPIRED,
        ], true);
    }

    /**
     * Check if payment can be refunded.
     */
    public function isRefundable(): bool
    {
        return in_array($this, [
            self::PAID,
            self::PARTIALLY_REFUNDED,
        ], true);
    }

    /**
     * Check if payment can be cancelled.
     */
    public function isCancellable(): bool
    {
        return in_array($this, [
            self::CREATED,
            self::PENDING,
            self::AUTHORIZED,
        ], true);
    }

    /**
     * Check if transition to target status is valid.
     */
    public function canTransitionTo(PaymentStatus $target): bool
    {
        return in_array($target, $this->getAllowedTransitions(), true);
    }

    /**
     * Get all allowed transitions from current status.
     *
     * @return array<PaymentStatus>
     */
    public function getAllowedTransitions(): array
    {
        return match ($this) {
            self::CREATED => [
                self::PENDING,
                self::PROCESSING,
                self::FAILED,
                self::CANCELLED,
                self::EXPIRED,
            ],
            self::PENDING => [
                self::PROCESSING,
                self::REQUIRES_ACTION,
                self::AUTHORIZED,
                self::PAID,
                self::FAILED,
                self::CANCELLED,
                self::EXPIRED,
            ],
            self::PROCESSING => [
                self::REQUIRES_ACTION,
                self::AUTHORIZED,
                self::PAID,
                self::FAILED,
                self::CANCELLED,
            ],
            self::REQUIRES_ACTION => [
                self::PROCESSING,
                self::AUTHORIZED,
                self::PAID,
                self::FAILED,
                self::CANCELLED,
                self::EXPIRED,
            ],
            self::AUTHORIZED => [
                self::PAID,
                self::CANCELLED,
                self::EXPIRED,
            ],
            self::PAID => [
                self::PARTIALLY_REFUNDED,
                self::REFUNDED,
                self::DISPUTED,
            ],
            self::PARTIALLY_REFUNDED => [
                self::REFUNDED,
                self::DISPUTED,
            ],
            self::REFUNDED => [], // Terminal
            self::FAILED => [], // Terminal
            self::CANCELLED => [], // Terminal
            self::EXPIRED => [], // Terminal
            self::DISPUTED => [
                self::PAID, // Dispute resolved in merchant's favor
                self::REFUNDED, // Dispute resolved in customer's favor
            ],
        };
    }

    /**
     * Transition to new status, throwing if invalid.
     *
     * @throws InvalidArgumentException
     */
    public function transitionTo(PaymentStatus $target): PaymentStatus
    {
        if (! $this->canTransitionTo($target)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot transition payment status from %s to %s. Allowed: %s',
                    $this->value,
                    $target->value,
                    implode(', ', array_map(fn ($s) => $s->value, $this->getAllowedTransitions()))
                )
            );
        }

        return $target;
    }

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::REQUIRES_ACTION => 'Requires Action',
            self::AUTHORIZED => 'Authorized',
            self::PAID => 'Paid',
            self::PARTIALLY_REFUNDED => 'Partially Refunded',
            self::REFUNDED => 'Refunded',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
            self::DISPUTED => 'Disputed',
        };
    }

    /**
     * Get an appropriate color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::CREATED, self::PENDING, self::PROCESSING => 'gray',
            self::REQUIRES_ACTION => 'warning',
            self::AUTHORIZED => 'info',
            self::PAID => 'success',
            self::PARTIALLY_REFUNDED => 'warning',
            self::REFUNDED => 'info',
            self::FAILED, self::DISPUTED => 'danger',
            self::CANCELLED, self::EXPIRED => 'gray',
        };
    }
}
