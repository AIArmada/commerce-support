<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use AIArmada\CommerceSupport\Exceptions\PaymentGatewayException;

/**
 * Trait for models with PaymentStatus to enforce valid transitions.
 *
 * Add this trait to Order, Payment, or Transaction models to prevent
 * invalid status transitions at the model level.
 *
 * @example
 * ```php
 * class Order extends Model
 * {
 *     use HasPaymentStatus;
 *
 *     protected function casts(): array
 *     {
 *         return [
 *             'payment_status' => PaymentStatus::class,
 *         ];
 *     }
 * }
 *
 * // Now transitions are enforced:
 * $order->transitionPaymentStatus(PaymentStatus::PAID); // Works if current is PENDING
 * $order->transitionPaymentStatus(PaymentStatus::REFUNDED); // Throws if current is PENDING
 * ```
 */
trait HasPaymentStatus
{
    /**
     * Get the payment status attribute name.
     */
    protected function getPaymentStatusAttribute(): string
    {
        return 'payment_status';
    }

    /**
     * Get the current payment status.
     */
    public function getPaymentStatus(): PaymentStatus
    {
        $attribute = $this->getPaymentStatusAttribute();
        $status = $this->getAttribute($attribute);

        if ($status instanceof PaymentStatus) {
            return $status;
        }

        return PaymentStatus::from($status ?? PaymentStatus::CREATED->value);
    }

    /**
     * Transition to a new payment status with validation.
     *
     * @throws PaymentGatewayException If transition is invalid
     */
    public function transitionPaymentStatus(PaymentStatus $newStatus, bool $save = true): static
    {
        $currentStatus = $this->getPaymentStatus();

        if (! $currentStatus->canTransitionTo($newStatus)) {
            throw PaymentGatewayException::invalidStatusTransition(
                $currentStatus,
                $newStatus,
                $currentStatus->getAllowedTransitions()
            );
        }

        $attribute = $this->getPaymentStatusAttribute();
        $this->setAttribute($attribute, $newStatus);

        if ($save) {
            $this->save();
        }

        return $this;
    }

    /**
     * Check if transition to status is allowed.
     */
    public function canTransitionTo(PaymentStatus $status): bool
    {
        return $this->getPaymentStatus()->canTransitionTo($status);
    }

    /**
     * Get allowed transitions from current status.
     *
     * @return array<PaymentStatus>
     */
    public function getAllowedTransitions(): array
    {
        return $this->getPaymentStatus()->getAllowedTransitions();
    }

    /**
     * Boot the trait - enforce transitions on save.
     */
    protected static function bootHasPaymentStatus(): void
    {
        static::saving(function ($model): void {
            $attribute = $model->getPaymentStatusAttribute();

            // Only validate if status is being changed
            if (! $model->isDirty($attribute)) {
                return;
            }

            $original = $model->getOriginal($attribute);
            $new = $model->getAttribute($attribute);

            // Skip validation for new models
            if ($original === null) {
                return;
            }

            $originalStatus = $original instanceof PaymentStatus
                ? $original
                : PaymentStatus::tryFrom($original);

            $newStatus = $new instanceof PaymentStatus
                ? $new
                : PaymentStatus::tryFrom($new);

            if ($originalStatus === null || $newStatus === null) {
                return;
            }

            if (! $originalStatus->canTransitionTo($newStatus)) {
                throw PaymentGatewayException::invalidStatusTransition(
                    $originalStatus,
                    $newStatus,
                    $originalStatus->getAllowedTransitions()
                );
            }
        });
    }

    /**
     * Mark payment as paid (convenience method).
     *
     * @throws PaymentGatewayException
     */
    public function markAsPaid(): static
    {
        return $this->transitionPaymentStatus(PaymentStatus::PAID);
    }

    /**
     * Mark payment as failed (convenience method).
     *
     * @throws PaymentGatewayException
     */
    public function markAsFailed(): static
    {
        return $this->transitionPaymentStatus(PaymentStatus::FAILED);
    }

    /**
     * Mark payment as cancelled (convenience method).
     *
     * @throws PaymentGatewayException
     */
    public function markAsCancelled(): static
    {
        return $this->transitionPaymentStatus(PaymentStatus::CANCELLED);
    }

    /**
     * Mark payment as refunded (convenience method).
     *
     * @throws PaymentGatewayException
     */
    public function markAsRefunded(): static
    {
        return $this->transitionPaymentStatus(PaymentStatus::REFUNDED);
    }

    /**
     * Check if payment is successful.
     */
    public function isPaid(): bool
    {
        return $this->getPaymentStatus()->isSuccessful();
    }

    /**
     * Check if payment is pending.
     */
    public function isPaymentPending(): bool
    {
        return $this->getPaymentStatus()->isPending();
    }

    /**
     * Check if payment failed.
     */
    public function isPaymentFailed(): bool
    {
        return $this->getPaymentStatus() === PaymentStatus::FAILED;
    }

    /**
     * Check if payment is refundable.
     */
    public function isRefundable(): bool
    {
        return $this->getPaymentStatus()->isRefundable();
    }
}
