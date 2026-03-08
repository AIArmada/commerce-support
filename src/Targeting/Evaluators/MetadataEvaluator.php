<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;
use AIArmada\CommerceSupport\Targeting\TargetingContext;

/**
 * Evaluates cart metadata targeting rules.
 */
class MetadataEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::Metadata->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $key = $rule['key'] ?? '';
        if ($key === '') {
            return false;
        }

        $operator = $rule['operator'] ?? '=';

        if (! $context instanceof TargetingContext) {
            return false;
        }

        return match ($operator) {
            'exists' => $context->hasCartMetadata($key),
            '=' => $context->getCartMetadata($key) === ($rule['value'] ?? null),
            '!=' => $context->getCartMetadata($key) !== ($rule['value'] ?? null),
            'contains' => $this->evaluateContains($context->getCartMetadata($key), $rule['value'] ?? null),
            'in' => $this->evaluateIn($context->getCartMetadata($key), $rule['values'] ?? []),
            'flag' => (bool) $context->getCartMetadata($key) === true,
            default => false,
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::Metadata->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        if (! isset($rule['key']) || $rule['key'] === '') {
            $errors[] = 'Metadata key is required';
        }

        $operator = $rule['operator'] ?? '=';

        if ($operator === 'in') {
            if (! isset($rule['values']) || ! is_array($rule['values'])) {
                $errors[] = 'Values array is required for "in" operator';
            }
        }

        return $errors;
    }

    private function evaluateContains(mixed $metadata, mixed $needle): bool
    {
        if (is_array($metadata)) {
            return in_array($needle, $metadata, true);
        }

        if (is_string($metadata) && is_string($needle)) {
            return str_contains($metadata, $needle);
        }

        return false;
    }

    /**
     * @param  array<mixed>  $values
     */
    private function evaluateIn(mixed $metadata, array $values): bool
    {
        return in_array($metadata, $values, true);
    }
}
