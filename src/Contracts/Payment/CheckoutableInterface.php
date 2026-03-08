<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

use Akaunting\Money\Money;

/**
 * Represents a collection of items that can be checked out via a payment gateway.
 *
 * This interface is typically implemented by Cart, Order, or Invoice classes
 * to provide a standardized way to extract checkout data for any payment gateway.
 */
interface CheckoutableInterface
{
    /**
     * Get all line items for checkout.
     *
     * @return iterable<LineItemInterface>
     */
    public function getCheckoutLineItems(): iterable;

    /**
     * Get the subtotal before discounts and fees.
     */
    public function getCheckoutSubtotal(): Money;

    /**
     * Get the total discount amount.
     */
    public function getCheckoutDiscount(): Money;

    /**
     * Get the total tax amount.
     */
    public function getCheckoutTax(): Money;

    /**
     * Get the final total amount to be charged.
     */
    public function getCheckoutTotal(): Money;

    /**
     * Get the currency code (ISO 4217).
     */
    public function getCheckoutCurrency(): string;

    /**
     * Get the unique reference for this checkout.
     *
     * This should be a unique identifier that can be used to link
     * the payment back to the original cart/order (e.g., Cart UUID).
     */
    public function getCheckoutReference(): string;

    /**
     * Get optional notes or description for the payment.
     */
    public function getCheckoutNotes(): ?string;

    /**
     * Get additional metadata to be sent with the payment.
     *
     * @return array<string, mixed>
     */
    public function getCheckoutMetadata(): array;
}
