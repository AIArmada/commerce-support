<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;

/**
 * Evaluates category in cart targeting rules.
 */
class CategoryInCartEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::CategoryInCart->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $categories = $context->getProductCategories();
        $operator = $rule['operator'] ?? 'in';
        $values = (array) ($rule['values'] ?? $rule['value'] ?? []);

        return match ($operator) {
            'in' => ! empty(array_intersect($values, $categories)),
            'not_in' => empty(array_intersect($values, $categories)),
            'contains_any' => ! empty(array_intersect($values, $categories)),
            'contains_all' => empty(array_diff($values, $categories)),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::CategoryInCart->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        $values = $rule['values'] ?? $rule['value'] ?? null;
        if ($values === null || (is_array($values) && empty($values))) {
            $errors[] = 'Category values are required';
        }

        return $errors;
    }
}
