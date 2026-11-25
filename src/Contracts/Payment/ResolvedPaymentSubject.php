<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

use AIArmada\CommerceSupport\Data\RecordReference;

final readonly class ResolvedPaymentSubject
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?RecordReference $subject,
        public ?CustomerInterface $paymentCustomer,
        public bool $isGuest,
        public string $resolvedBy,
        public array $metadata = [],
    ) {}
}
