<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

interface PaymentSubjectResolverInterface
{
    public function register(PaymentSubjectDriverInterface $driver): void;

    public function resolve(PaymentSubjectContext $context): ?ResolvedPaymentSubject;

    /**
     * @return array<int, PaymentSubjectDriverInterface>
     */
    public function all(): array;
}
