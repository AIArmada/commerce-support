<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;
use AIArmada\CommerceSupport\Targeting\TargetingContext;

/**
 * Evaluates item attribute targeting rules.
 */
class ItemAttributeEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::ItemAttribute->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $attribute = $rule['attribute'] ?? '';
        if ($attribute === '') {
            return false;
        }

        if (! $context instanceof TargetingContext) {
            return false;
        }

        $items = $context->getCartItems();
        if ($items->isEmpty()) {
            return false;
        }

        $operator = $rule['operator'] ?? '=';
        $value = $rule['value'] ?? null;
        $values = (array) ($rule['values'] ?? []);

        $matchingItems = $items->filter(function ($item) use ($attribute, $operator, $value, $values): bool {
            $itemValue = $this->getItemAttribute($item, $attribute);

            return match ($operator) {
                '=' => $itemValue === $value,
                '!=' => $itemValue !== $value,
                'contains' => is_string($itemValue) && is_string($value) && str_contains($itemValue, $value),
                'starts_with' => is_string($itemValue) && is_string($value) && str_starts_with($itemValue, $value),
                'ends_with' => is_string($itemValue) && is_string($value) && str_ends_with($itemValue, $value),
                'in' => in_array($itemValue, $values, true),
                default => false,
            };
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
        return TargetingRuleType::ItemAttribute->value;
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

    private function getItemAttribute(mixed $item, string $attribute): mixed
    {
        if (method_exists($item, 'getAttribute')) {
            $value = $item->getAttribute($attribute);
            if ($value !== null) {
                return $value;
            }
        }

        if (property_exists($item, 'attributes') && isset($item->attributes)) {
            if (method_exists($item->attributes, 'get')) {
                return $item->attributes->get($attribute);
            }
            if (is_array($item->attributes)) {
                return $item->attributes[$attribute] ?? null;
            }
        }

        return $item->{$attribute} ?? null;
    }
}
