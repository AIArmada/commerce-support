<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;

/**
 * Evaluates channel targeting rules.
 */
class ChannelEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::Channel->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $channel = $context->getChannel();
        $operator = $rule['operator'] ?? '=';
        $value = $rule['value'] ?? null;
        $values = (array) ($rule['values'] ?? []);

        return match ($operator) {
            '=' => $channel === $value,
            '!=' => $channel !== $value,
            'in' => in_array($channel, $values, true),
            'not_in' => ! in_array($channel, $values, true),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::Channel->value;
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
