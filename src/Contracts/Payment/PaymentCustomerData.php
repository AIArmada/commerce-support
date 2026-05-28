<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

final readonly class PaymentCustomerData implements CustomerInterface
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private string $email,
        private ?string $name = null,
        private ?string $phone = null,
        private ?string $country = null,
        private ?string $billingStreetAddress = null,
        private ?string $billingCity = null,
        private ?string $billingState = null,
        private ?string $billingPostalCode = null,
        private ?string $billingCountry = null,
        private ?string $shippingStreetAddress = null,
        private ?string $shippingCity = null,
        private ?string $shippingState = null,
        private ?string $shippingPostalCode = null,
        private ?string $shippingCountry = null,
        private ?string $gatewayCustomerId = null,
        private array $metadata = [],
    ) {}

    public function getCustomerEmail(): string
    {
        return $this->email;
    }

    public function getCustomerName(): ?string
    {
        return $this->name;
    }

    public function getCustomerPhone(): ?string
    {
        return $this->phone;
    }

    public function getCustomerCountry(): ?string
    {
        return $this->country;
    }

    public function getBillingStreetAddress(): ?string
    {
        return $this->billingStreetAddress;
    }

    public function getBillingCity(): ?string
    {
        return $this->billingCity;
    }

    public function getBillingState(): ?string
    {
        return $this->billingState;
    }

    public function getBillingPostalCode(): ?string
    {
        return $this->billingPostalCode;
    }

    public function getBillingCountry(): ?string
    {
        return $this->billingCountry;
    }

    public function hasShippingAddress(): bool
    {
        return $this->shippingStreetAddress !== null
            || $this->shippingCity !== null
            || $this->shippingPostalCode !== null
            || $this->shippingCountry !== null;
    }

    public function getShippingStreetAddress(): ?string
    {
        return $this->shippingStreetAddress;
    }

    public function getShippingCity(): ?string
    {
        return $this->shippingCity;
    }

    public function getShippingState(): ?string
    {
        return $this->shippingState;
    }

    public function getShippingPostalCode(): ?string
    {
        return $this->shippingPostalCode;
    }

    public function getShippingCountry(): ?string
    {
        return $this->shippingCountry;
    }

    public function getGatewayCustomerId(): ?string
    {
        return $this->gatewayCustomerId;
    }

    public function getCustomerMetadata(): array
    {
        return $this->metadata;
    }
}
