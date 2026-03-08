<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Events;

/**
 * Interface for voucher-related events across commerce packages.
 *
 * Extends the base commerce event interface with voucher-specific
 * properties for event sourcing, analytics, and cross-package integration.
 */
interface VoucherEventInterface extends CommerceEventInterface
{
    /**
     * Get the voucher code.
     */
    public function getVoucherCode(): string;

    /**
     * Get the voucher ID (if available).
     */
    public function getVoucherId(): ?string;

    /**
     * Get the cart identifier this voucher event relates to.
     */
    public function getCartIdentifier(): ?string;

    /**
     * Get the cart instance name this voucher event relates to.
     */
    public function getCartInstance(): ?string;

    /**
     * Get the discount amount in cents (if applicable).
     */
    public function getDiscountAmountCents(): ?int;

    /**
     * Determine if this event should be persisted to the event store.
     */
    public function shouldPersist(): bool;
}
