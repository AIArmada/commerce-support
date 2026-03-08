<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;

/**
 * Evaluates targeting rules based on payment method.
 *
 * @example
 * ```php
 * // Only for credit card payments
 * ['type' => 'payment_method', 'methods' => ['credit_card', 'debit_card']]
 *
 * // Exclude cash on delivery
 * ['type' => 'payment_method', 'exclude' => ['cod', 'cash_on_delivery']]
 * ```
 */
final readonly class PaymentMethodEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === $this->getType();
    }

    public function getType(): string
    {
        return 'payment_method';
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $paymentMethod = $this->getPaymentMethod($context);

        if ($paymentMethod === null) {
            // No payment method set - fail if methods required, pass if only excluding
            return ! isset($rule['methods']) && isset($rule['exclude']);
        }

        $paymentMethod = mb_strtolower($paymentMethod);

        // Check exclusions first
        if (isset($rule['exclude'])) {
            $excludeMethods = array_map('strtolower', (array) $rule['exclude']);
            if (in_array($paymentMethod, $excludeMethods, true)) {
                return false;
            }
        }

        // Check inclusions
        if (isset($rule['methods'])) {
            $allowedMethods = array_map('strtolower', (array) $rule['methods']);

            return in_array($paymentMethod, $allowedMethods, true);
        }

        // Only exclusion specified and payment method not excluded
        return true;
    }

    /**
     * @return array<string>
     */
    public function validate(array $rule): array
    {
        if (! isset($rule['methods']) && ! isset($rule['exclude'])) {
            return ["Rule must have 'methods' or 'exclude' key"];
        }

        return [];
    }

    private function getPaymentMethod(TargetingContextInterface $context): ?string
    {
        // Check context method
        if (method_exists($context, 'getPaymentMethod')) {
            return $context->getPaymentMethod();
        }

        // Check metadata
        if (method_exists($context, 'getMetadata')) {
            return $context->getMetadata('payment_method');
        }

        return null;
    }
}
