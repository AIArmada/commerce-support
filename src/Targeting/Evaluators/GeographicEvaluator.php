<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;

/**
 * Evaluates geographic targeting rules.
 */
class GeographicEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::Geographic->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $country = $context->getCountry();

        if ($country === null) {
            return false;
        }

        $operator = $rule['operator'] ?? 'in';
        $values = (array) ($rule['values'] ?? $rule['countries'] ?? []);

        $normalizedCountry = mb_strtoupper($country);
        $normalizedValues = array_map('mb_strtoupper', $values);

        return match ($operator) {
            'in' => in_array($normalizedCountry, $normalizedValues, true),
            'not_in' => ! in_array($normalizedCountry, $normalizedValues, true),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::Geographic->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        $values = $rule['values'] ?? $rule['countries'] ?? null;
        if ($values === null || (is_array($values) && empty($values))) {
            $errors[] = 'Country values are required';
        }

        return $errors;
    }
}
