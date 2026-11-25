<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support\Payment;

use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectContext;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectDriverInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectResolverInterface;
use AIArmada\CommerceSupport\Contracts\Payment\ResolvedPaymentSubject;

final class PaymentSubjectResolver implements PaymentSubjectResolverInterface
{
    /** @var array<string, PaymentSubjectDriverInterface> */
    private array $drivers = [];

    /** @var list<PaymentSubjectDriverInterface>|null */
    private ?array $sorted = null;

    public function register(PaymentSubjectDriverInterface $driver): void
    {
        $this->drivers[$driver->getIdentifier()] = $driver;
        $this->sorted = null;
    }

    public function resolve(PaymentSubjectContext $context): ?ResolvedPaymentSubject
    {
        foreach ($this->sortedDrivers() as $driver) {
            if (! $driver->supports($context)) {
                continue;
            }

            $resolved = $driver->resolve($context);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    public function all(): array
    {
        return $this->sortedDrivers();
    }

    /**
     * @return array<int, PaymentSubjectDriverInterface>
     */
    private function sortedDrivers(): array
    {
        if ($this->sorted !== null) {
            return $this->sorted;
        }

        $drivers = array_values($this->drivers);

        usort($drivers, static function (PaymentSubjectDriverInterface $left, PaymentSubjectDriverInterface $right): int {
            return $right->getPriority() <=> $left->getPriority();
        });

        return $this->sorted = $drivers;
    }
}
