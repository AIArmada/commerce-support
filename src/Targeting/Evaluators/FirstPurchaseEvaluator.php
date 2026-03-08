<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;

/**
 * Evaluates first purchase targeting rules.
 */
class FirstPurchaseEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::FirstPurchase->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $isFirstPurchase = $context->isFirstPurchase();
        $expected = (bool) ($rule['value'] ?? true);

        return $isFirstPurchase === $expected;
    }

    public function getType(): string
    {
        return TargetingRuleType::FirstPurchase->value;
    }

    public function validate(array $rule): array
    {
        return [];
    }
}
