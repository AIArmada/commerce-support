<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;

/**
 * Evaluates cart value targeting rules.
 */
class CartValueEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::CartValue->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $cartValue = $context->getCartValue();
        $operator = $rule['operator'] ?? '>=';

        return match ($operator) {
            '=' => $cartValue === (int) ($rule['value'] ?? 0),
            '!=' => $cartValue !== (int) ($rule['value'] ?? 0),
            '>' => $cartValue > (int) ($rule['value'] ?? 0),
            '>=' => $cartValue >= (int) ($rule['value'] ?? 0),
            '<' => $cartValue < (int) ($rule['value'] ?? 0),
            '<=' => $cartValue <= (int) ($rule['value'] ?? 0),
            'between' => $cartValue >= (int) ($rule['min'] ?? 0) && $cartValue <= (int) ($rule['max'] ?? PHP_INT_MAX),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::CartValue->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];
        $operator = $rule['operator'] ?? '>=';

        if ($operator === 'between') {
            if (! isset($rule['min']) || ! is_numeric($rule['min'])) {
                $errors[] = 'Min value is required for between operator';
            }
            if (! isset($rule['max']) || ! is_numeric($rule['max'])) {
                $errors[] = 'Max value is required for between operator';
            }
        } else {
            if (! isset($rule['value']) || ! is_numeric($rule['value'])) {
                $errors[] = 'Value must be a number';
            }
        }

        return $errors;
    }
}
