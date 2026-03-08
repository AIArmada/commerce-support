<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

/**
 * Represents customer information for payment gateway integration.
 *
 * This interface abstracts customer details that can be sent to any
 * payment gateway (Stripe, PayPal, CHIP, SenangPay, eGHL, etc.)
 */
interface CustomerInterface
{
    /**
     * Get the customer's email address.
     */
    public function getCustomerEmail(): string;

    /**
     * Get the customer's full name.
     */
    public function getCustomerName(): ?string;

    /**
     * Get the customer's phone number.
     */
    public function getCustomerPhone(): ?string;

    /**
     * Get the customer's country code (ISO 3166-1 alpha-2).
     */
    public function getCustomerCountry(): ?string;

    /**
     * Get the billing street address.
     */
    public function getBillingStreetAddress(): ?string;

    /**
     * Get the billing city.
     */
    public function getBillingCity(): ?string;

    /**
     * Get the billing state/province.
     */
    public function getBillingState(): ?string;

    /**
     * Get the billing postal/zip code.
     */
    public function getBillingPostalCode(): ?string;

    /**
     * Get the billing country code (ISO 3166-1 alpha-2).
     */
    public function getBillingCountry(): ?string;

    /**
     * Check if shipping address differs from billing.
     */
    public function hasShippingAddress(): bool;

    /**
     * Get the shipping street address.
     */
    public function getShippingStreetAddress(): ?string;

    /**
     * Get the shipping city.
     */
    public function getShippingCity(): ?string;

    /**
     * Get the shipping state/province.
     */
    public function getShippingState(): ?string;

    /**
     * Get the shipping postal/zip code.
     */
    public function getShippingPostalCode(): ?string;

    /**
     * Get the shipping country code (ISO 3166-1 alpha-2).
     */
    public function getShippingCountry(): ?string;

    /**
     * Get the external customer ID from the payment gateway (if exists).
     */
    public function getGatewayCustomerId(): ?string;

    /**
     * Get additional customer metadata.
     *
     * @return array<string, mixed>
     */
    public function getCustomerMetadata(): array;
}
