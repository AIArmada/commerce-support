<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;

/**
 * Evaluates user attribute targeting rules.
 */
class UserAttributeEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::UserAttribute->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $attribute = $rule['attribute'] ?? '';
        if ($attribute === '') {
            return false;
        }

        $value = $context->getUserAttribute($attribute);
        $operator = $rule['operator'] ?? '=';
        $expected = $rule['value'] ?? null;

        if ($value === null && $operator !== '!=') {
            return false;
        }

        return match ($operator) {
            '=' => $value === $expected,
            '!=' => $value !== $expected,
            'contains' => is_string($value) && is_string($expected) && str_contains($value, $expected),
            'starts_with' => is_string($value) && is_string($expected) && str_starts_with($value, $expected),
            'ends_with' => is_string($value) && is_string($expected) && str_ends_with($value, $expected),
            'in' => is_array($rule['values'] ?? null) && in_array($value, $rule['values'], true),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::UserAttribute->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        if (! isset($rule['attribute']) || $rule['attribute'] === '') {
            $errors[] = 'Attribute name is required';
        }

        $operator = $rule['operator'] ?? '=';
        if ($operator === 'in') {
            if (! isset($rule['values']) || ! is_array($rule['values'])) {
                $errors[] = 'Values array is required for "in" operator';
            }
        }

        return $errors;
    }
}
