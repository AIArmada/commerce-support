<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;
use AIArmada\CommerceSupport\Targeting\TargetingContext;

/**
 * Evaluates currency targeting rules.
 */
class CurrencyEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::Currency->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        if (! $context instanceof TargetingContext) {
            return false;
        }

        $currency = mb_strtoupper($context->getCurrency());
        $operator = $rule['operator'] ?? '=';
        $value = isset($rule['value']) ? mb_strtoupper((string) $rule['value']) : null;
        $values = array_map('mb_strtoupper', (array) ($rule['values'] ?? []));

        return match ($operator) {
            '=' => $currency === $value,
            '!=' => $currency !== $value,
            'in' => in_array($currency, $values, true),
            'not_in' => ! in_array($currency, $values, true),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::Currency->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];
        $operator = $rule['operator'] ?? '=';

        if ($operator === 'in' || $operator === 'not_in') {
            if (! isset($rule['values']) || ! is_array($rule['values']) || empty($rule['values'])) {
                $errors[] = 'Values array is required for "in" operators';
            }
        } else {
            if (! isset($rule['value'])) {
                $errors[] = 'Currency value is required';
            }
        }

        return $errors;
    }
}
