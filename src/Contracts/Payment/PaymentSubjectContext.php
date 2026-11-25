<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

use AIArmada\CommerceSupport\Data\PaymentActorData;
use AIArmada\CommerceSupport\Data\RecordReference;

final readonly class PaymentSubjectContext
{
    /**
     * @param  array<string, mixed>  $billingData
     * @param  array<string, mixed>  $shippingData
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $gateway,
        public ?PaymentActorData $actor = null,
        public ?RecordReference $subject = null,
        public ?RecordReference $sessionCustomer = null,
        public ?RecordReference $sessionBillable = null,
        public array $billingData = [],
        public array $shippingData = [],
        public array $metadata = [],
        public ?RecordReference $owner = null,
        public string $source = 'checkout',
    ) {}
}
