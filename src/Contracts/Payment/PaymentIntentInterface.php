<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

use Akaunting\Money\Money;
use DateTimeInterface;

/**
 * Represents a payment intent/purchase created by a payment gateway.
 *
 * This interface abstracts the response from payment gateways when
 * creating a payment. Different gateways call this differently:
 * - Stripe: PaymentIntent
 * - CHIP: Purchase
 * - PayPal: Order
 * - SenangPay: Transaction
 */
interface PaymentIntentInterface
{
    /**
     * Get the gateway-specific payment identifier.
     */
    public function getPaymentId(): string;

    /**
     * Get the merchant reference (your internal order/cart ID).
     */
    public function getReference(): ?string;

    /**
     * Get the payment amount.
     */
    public function getAmount(): Money;

    /**
     * Get the payment status.
     */
    public function getStatus(): PaymentStatus;

    /**
     * Get the URL to redirect the customer for payment.
     *
     * For hosted checkout flows (CHIP, PayPal, SenangPay).
     * Returns null for embedded/API-only flows.
     */
    public function getCheckoutUrl(): ?string;

    /**
     * Get the return URL after successful payment.
     */
    public function getSuccessUrl(): ?string;

    /**
     * Get the return URL after failed/cancelled payment.
     */
    public function getFailureUrl(): ?string;

    /**
     * Check if payment was successful.
     */
    public function isPaid(): bool;

    /**
     * Check if payment is pending/awaiting action.
     */
    public function isPending(): bool;

    /**
     * Check if payment failed.
     */
    public function isFailed(): bool;

    /**
     * Check if payment was cancelled.
     */
    public function isCancelled(): bool;

    /**
     * Check if payment was refunded (fully or partially).
     */
    public function isRefunded(): bool;

    /**
     * Get the refundable amount remaining.
     */
    public function getRefundableAmount(): Money;

    /**
     * Check if this is a test/sandbox payment.
     */
    public function isTest(): bool;

    /**
     * Get the gateway name (e.g., 'chip', 'stripe', 'paypal').
     */
    public function getGatewayName(): string;

    /**
     * Get the timestamp when payment was created.
     */
    public function getCreatedAt(): DateTimeInterface;

    /**
     * Get the timestamp when payment status was last updated.
     */
    public function getUpdatedAt(): DateTimeInterface;

    /**
     * Get the timestamp when payment was completed (if paid).
     */
    public function getPaidAt(): ?DateTimeInterface;

    /**
     * Get additional metadata from the gateway response.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    /**
     * Get the raw gateway response for advanced use cases.
     *
     * @return array<string, mixed>
     */
    public function getRawResponse(): array;
}
