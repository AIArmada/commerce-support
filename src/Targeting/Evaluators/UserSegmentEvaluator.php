<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;

/**
 * Evaluates user segment targeting rules.
 */
class UserSegmentEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::UserSegment->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $userSegments = $context->getUserSegments();
        $operator = $rule['operator'] ?? 'in';
        $values = (array) ($rule['values'] ?? $rule['value'] ?? []);

        return match ($operator) {
            'in' => ! empty(array_intersect($values, $userSegments)),
            'not_in' => empty(array_intersect($values, $userSegments)),
            'contains_any' => ! empty(array_intersect($values, $userSegments)),
            'contains_all' => empty(array_diff($values, $userSegments)),
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::UserSegment->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        $values = $rule['values'] ?? $rule['value'] ?? null;
        if ($values === null || (is_array($values) && empty($values))) {
            $errors[] = 'Segment values are required';
        }

        return $errors;
    }
}
