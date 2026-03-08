<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;
use AIArmada\CommerceSupport\Targeting\TargetingContext;

/**
 * Evaluates item constraint targeting rules (quantity, price, total).
 */
class ItemConstraintEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::ItemConstraint->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $constraint = $rule['constraint'] ?? 'quantity';
        $operator = $rule['operator'] ?? '>=';

        if (! $context instanceof TargetingContext) {
            return false;
        }

        $items = $context->getCartItems();
        if ($items->isEmpty()) {
            return false;
        }

        $matchingItems = $items->filter(function ($item) use ($constraint, $operator, $rule): bool {
            $value = match ($constraint) {
                'quantity' => $item->quantity ?? 1,
                'price' => $item->price ?? 0,
                'total' => ($item->price ?? 0) * ($item->quantity ?? 1),
                default => 0,
            };

            return $this->compare($value, $operator, $rule);
        });

        $matchMode = $rule['match'] ?? 'any';

        return match ($matchMode) {
            'all' => $matchingItems->count() === $items->count(),
            'none' => $matchingItems->isEmpty(),
            default => $matchingItems->isNotEmpty(),
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::ItemConstraint->value;
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

    /**
     * @param  array<string, mixed>  $rule
     */
    private function compare(int | float $value, string $operator, array $rule): bool
    {
        $target = (float) ($rule['value'] ?? 0);
        $min = (float) ($rule['min'] ?? 0);
        $max = (float) ($rule['max'] ?? PHP_INT_MAX);

        return match ($operator) {
            '=' => $value === $target,
            '!=' => $value !== $target,
            '>' => $value > $target,
            '>=' => $value >= $target,
            '<' => $value < $target,
            '<=' => $value <= $target,
            'between' => $value >= $min && $value <= $max,
            default => false,
        };
    }
}
