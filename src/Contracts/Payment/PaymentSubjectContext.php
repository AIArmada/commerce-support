<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

use Illuminate\Database\Eloquent\Model;

final readonly class PaymentSubjectContext
{
    /**
     * @param  array<string, mixed>  $billingData
     * @param  array<string, mixed>  $shippingData
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $gateway,
        public ?Model $actor = null,
        public ?Model $subject = null,
        public ?Model $sessionCustomer = null,
        public ?Model $sessionBillable = null,
        public array $billingData = [],
        public array $shippingData = [],
        public array $metadata = [],
        public ?Model $owner = null,
        public string $source = 'checkout',
    ) {}
}
