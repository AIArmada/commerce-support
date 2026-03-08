<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;

/**
 * Evaluates referrer targeting rules.
 */
class ReferrerEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::Referrer->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $referrer = $context->getReferrer();
        $operator = $rule['operator'] ?? '=';
        $value = $rule['value'] ?? null;
        $values = (array) ($rule['values'] ?? []);

        if ($referrer === null) {
            return $operator === '!=' || $operator === 'not_in';
        }

        return match ($operator) {
            '=' => $this->matchesValue($referrer, $value),
            '!=' => ! $this->matchesValue($referrer, $value),
            'in' => $this->matchesAnyValue($referrer, $values),
            'not_in' => ! $this->matchesAnyValue($referrer, $values),
            'contains' => is_string($value) && str_contains($referrer, $value),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::Referrer->value;
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

    private function matchesValue(string $referrer, mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        if ($referrer === $value) {
            return true;
        }

        $referrerHost = parse_url($referrer, PHP_URL_HOST);
        $valueHost = parse_url($value, PHP_URL_HOST) ?? $value;

        return $referrerHost === $valueHost;
    }

    /**
     * @param  array<mixed>  $values
     */
    private function matchesAnyValue(string $referrer, array $values): bool
    {
        foreach ($values as $value) {
            if ($this->matchesValue($referrer, $value)) {
                return true;
            }
        }

        return false;
    }
}
