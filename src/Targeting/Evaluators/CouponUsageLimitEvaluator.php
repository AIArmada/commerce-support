<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;

/**
 * Evaluates targeting rules based on coupon/voucher usage limits.
 *
 * @example
 * ```php
 * // Limit coupon to 3 uses per customer
 * ['type' => 'coupon_usage_limit', 'code' => 'SAVE20', 'max_uses' => 3]
 *
 * // First-time use only
 * ['type' => 'coupon_usage_limit', 'code' => 'WELCOME', 'max_uses' => 1]
 * ```
 */
final readonly class CouponUsageLimitEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === $this->getType();
    }

    public function getType(): string
    {
        return 'coupon_usage_limit';
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $couponCode = $rule['code'] ?? $this->getCurrentCouponCode($context);

        if ($couponCode === null) {
            // No coupon code to check - pass (no restriction)
            return true;
        }

        $maxUses = (int) ($rule['max_uses'] ?? 1);
        $currentUsage = $this->getUsageCount($couponCode, $context);

        return $currentUsage < $maxUses;
    }

    /**
     * @return array<string>
     */
    public function validate(array $rule): array
    {
        if (! isset($rule['max_uses'])) {
            return ["Rule must have 'max_uses' key"];
        }

        if (! is_numeric($rule['max_uses'])) {
            return ["'max_uses' must be numeric"];
        }

        return [];
    }

    private function getCurrentCouponCode(TargetingContextInterface $context): ?string
    {
        if (method_exists($context, 'getCouponCode')) {
            return $context->getCouponCode();
        }

        if (method_exists($context, 'getMetadata')) {
            return $context->getMetadata('coupon_code');
        }

        if (method_exists($context, 'getCartMetadata')) {
            return $context->getCartMetadata('coupon_code');
        }

        return null;
    }

    private function getUsageCount(string $couponCode, TargetingContextInterface $context): int
    {
        // First check if context has a dedicated method
        if (method_exists($context, 'getCouponUsageCount')) {
            return $context->getCouponUsageCount($couponCode);
        }

        // Check metadata
        $usageKey = "coupon_usage_{$couponCode}";

        if (method_exists($context, 'getMetadata')) {
            $usage = $context->getMetadata($usageKey);
            if ($usage !== null) {
                return (int) $usage;
            }
        }

        // Check user attribute
        if (method_exists($context, 'getUserAttribute')) {
            $usage = $context->getUserAttribute($usageKey);
            if ($usage !== null) {
                return (int) $usage;
            }
        }

        return 0;
    }
}
