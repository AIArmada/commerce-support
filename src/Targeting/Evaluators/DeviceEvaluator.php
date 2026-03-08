<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;

/**
 * Evaluates device targeting rules.
 */
class DeviceEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::Device->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $device = $context->getDevice();
        $operator = $rule['operator'] ?? '=';
        $value = $rule['value'] ?? null;
        $values = (array) ($rule['values'] ?? []);

        return match ($operator) {
            '=' => $device === $value,
            '!=' => $device !== $value,
            'in' => in_array($device, $values, true),
            'not_in' => ! in_array($device, $values, true),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::Device->value;
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
                $errors[] = 'Value is required';
            }
        }

        return $errors;
    }
}
