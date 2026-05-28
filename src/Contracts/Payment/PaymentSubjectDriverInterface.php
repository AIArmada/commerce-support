<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

interface PaymentSubjectDriverInterface
{
    public function getIdentifier(): string;

    public function getPriority(): int;

    public function supports(PaymentSubjectContext $context): bool;

    public function resolve(PaymentSubjectContext $context): ?ResolvedPaymentSubject;
}
