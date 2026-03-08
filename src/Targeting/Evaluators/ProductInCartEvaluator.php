<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;

/**
 * Evaluates product in cart targeting rules.
 */
class ProductInCartEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::ProductInCart->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $productIds = $context->getProductIdentifiers();
        $operator = $rule['operator'] ?? 'in';
        $values = (array) ($rule['values'] ?? $rule['value'] ?? []);

        return match ($operator) {
            'in' => ! empty(array_intersect($values, $productIds)),
            'not_in' => empty(array_intersect($values, $productIds)),
            'contains_any' => ! empty(array_intersect($values, $productIds)),
            'contains_all' => empty(array_diff($values, $productIds)),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::ProductInCart->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        $values = $rule['values'] ?? $rule['value'] ?? null;
        if ($values === null || (is_array($values) && empty($values))) {
            $errors[] = 'Product values are required';
        }

        return $errors;
    }
}
