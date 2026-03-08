<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Contracts;

/**
 * Contract for targeting rule evaluators.
 */
interface TargetingRuleEvaluator
{
    /**
     * Check if this evaluator supports the given rule type.
     */
    public function supports(string $type): bool;

    /**
     * Evaluate the rule against the targeting context.
     *
     * @param  array<string, mixed>  $rule  The rule configuration
     * @param  TargetingContextInterface  $context  The targeting context
     */
    public function evaluate(array $rule, TargetingContextInterface $context): bool;

    /**
     * Get the rule type this evaluator handles.
     */
    public function getType(): string;

    /**
     * Validate the rule configuration.
     *
     * @param  array<string, mixed>  $rule
     * @return array<string> List of validation errors (empty if valid)
     */
    public function validate(array $rule): array;
}
