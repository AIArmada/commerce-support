<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Data;

final readonly class PaymentActorData
{
    public function __construct(
        public RecordReference $reference,
        public ?string $name,
        public ?string $email,
        public ?string $phone,
    ) {}
}
