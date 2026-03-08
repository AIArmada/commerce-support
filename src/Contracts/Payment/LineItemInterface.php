<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

use Akaunting\Money\Money;

/**
 * Represents a purchasable line item for payment gateway integration.
 *
 * This interface abstracts the concept of a product/item that can be
 * sent to any payment gateway (Stripe, PayPal, CHIP, SenangPay, eGHL, etc.)
 */
interface LineItemInterface
{
    /**
     * Get the unique identifier for this line item.
     */
    public function getLineItemId(): string;

    /**
     * Get the display name of the line item.
     */
    public function getLineItemName(): string;

    /**
     * Get the unit price as a Money object.
     *
     * The Money object contains both amount and currency,
     * ensuring type-safe currency handling across all gateways.
     */
    public function getLineItemPrice(): Money;

    /**
     * Get the quantity of this line item.
     */
    public function getLineItemQuantity(): int | float;

    /**
     * Get the discount amount as a Money object.
     *
     * Return a zero-value Money object if no discount applies.
     */
    public function getLineItemDiscount(): Money;

    /**
     * Get the tax percentage (e.g., 6.0 for 6% SST).
     *
     * Return 0.0 if no tax applies.
     */
    public function getLineItemTaxPercent(): float;

    /**
     * Get the line item subtotal (price × quantity - discount).
     */
    public function getLineItemSubtotal(): Money;

    /**
     * Get optional category/type for the line item.
     */
    public function getLineItemCategory(): ?string;

    /**
     * Get additional metadata as key-value pairs.
     *
     * @return array<string, mixed>
     */
    public function getLineItemMetadata(): array;
}
