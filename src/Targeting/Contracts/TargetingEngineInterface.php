<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Contracts;

/**
 * Contract for the targeting evaluation engine.
 */
interface TargetingEngineInterface
{
    /**
     * Register a targeting rule evaluator.
     */
    public function registerEvaluator(TargetingRuleEvaluator $evaluator): self;

    /**
     * Get a registered evaluator by type.
     */
    public function getEvaluator(string $type): ?TargetingRuleEvaluator;

    /**
     * Get all registered evaluators.
     *
     * @return array<string, TargetingRuleEvaluator>
     */
    public function getEvaluators(): array;

    /**
     * Evaluate targeting configuration against context.
     *
     * @param  array<string, mixed>  $targeting
     */
    public function evaluate(array $targeting, TargetingContextInterface $context): bool;

    /**
     * Validate a targeting configuration.
     *
     * @param  array<string, mixed>  $targeting
     * @return array<string> List of validation errors
     */
    public function validate(array $targeting): array;
}
