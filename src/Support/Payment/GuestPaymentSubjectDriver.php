<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support\Payment;

use AIArmada\CommerceSupport\Contracts\Payment\PaymentCustomerData;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectContext;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectDriverInterface;
use AIArmada\CommerceSupport\Contracts\Payment\ResolvedPaymentSubject;

final class GuestPaymentSubjectDriver implements PaymentSubjectDriverInterface
{
    public function getIdentifier(): string
    {
        return 'guest';
    }

    public function getPriority(): int
    {
        return -100;
    }

    public function supports(PaymentSubjectContext $context): bool
    {
        return $this->resolveEmail($context) !== null;
    }

    public function resolve(PaymentSubjectContext $context): ?ResolvedPaymentSubject
    {
        $email = $this->resolveEmail($context);

        if ($email === null) {
            return null;
        }

        return new ResolvedPaymentSubject(
            subject: $context->subject ?? $context->sessionBillable,
            paymentCustomer: new PaymentCustomerData(
                email: $email,
                name: $this->cleanString($context->billingData['name'] ?? null)
                    ?? $this->fullNameFromParts($context->billingData)
                    ?? $this->cleanString($context->shippingData['name'] ?? null)
                    ?? $this->cleanString($context->actor?->getAttribute('name')),
                phone: $this->cleanString($context->billingData['phone'] ?? null)
                    ?? $this->cleanString($context->shippingData['phone'] ?? null)
                    ?? $this->cleanString($context->actor?->getAttribute('phone')),
                country: $this->cleanString($context->billingData['country'] ?? null)
                    ?? $this->cleanString($context->shippingData['country'] ?? null)
                    ?? 'MY',
                billingStreetAddress: $this->cleanString($context->billingData['line1'] ?? null),
                billingCity: $this->cleanString($context->billingData['city'] ?? null),
                billingState: $this->cleanString($context->billingData['state'] ?? null),
                billingPostalCode: $this->cleanString($context->billingData['postcode'] ?? null),
                billingCountry: $this->cleanString($context->billingData['country'] ?? null),
                shippingStreetAddress: $this->cleanString($context->shippingData['line1'] ?? null),
                shippingCity: $this->cleanString($context->shippingData['city'] ?? null),
                shippingState: $this->cleanString($context->shippingData['state'] ?? null),
                shippingPostalCode: $this->cleanString($context->shippingData['postcode'] ?? null),
                shippingCountry: $this->cleanString($context->shippingData['country'] ?? null),
                metadata: $context->metadata,
            ),
            isGuest: true,
            resolvedBy: $this->getIdentifier(),
            metadata: $context->metadata,
        );
    }

    private function resolveEmail(PaymentSubjectContext $context): ?string
    {
        return $this->cleanString($context->billingData['email'] ?? null)
            ?? $this->cleanString($context->shippingData['email'] ?? null)
            ?? $this->cleanString($context->actor?->getAttribute('email'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fullNameFromParts(array $data): ?string
    {
        $firstName = $this->cleanString($data['first_name'] ?? null);
        $lastName = $this->cleanString($data['last_name'] ?? null);

        if ($firstName === null && $lastName === null) {
            return null;
        }

        return mb_trim(mb_trim((string) ($firstName ?? '') . ' ' . (string) ($lastName ?? '')));
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null || ! is_scalar($value)) {
            return null;
        }

        $cleaned = mb_trim((string) $value);

        return $cleaned === '' ? null : $cleaned;
    }
}
